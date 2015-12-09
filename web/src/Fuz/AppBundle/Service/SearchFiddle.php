<?php

/*
 * This file is part of twigfiddle.com project.
 *
 * (c) Alain Tiemblo <alain@fuz.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fuz\AppBundle\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Fuz\AppBundle\Entity\BrowseFilters;
use Fuz\AppBundle\Entity\Fiddle;
use Fuz\AppBundle\Entity\User;
use Fuz\AppBundle\Util\Paginator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class SearchFiddle
{
    protected $logger;
    protected $em;
    protected $paginator;
    protected $webConfig;

    public function __construct(LoggerInterface $logger, EntityManager $em, Paginator $paginator, array $webConfig)
    {
        $this->logger = $logger;
        $this->em = $em;
        $this->paginator = $paginator;
        $this->webConfig = $webConfig;
    }

    /**
     * Searches for fiddles.
     *
     * @param BrowseFilters $criteria
     * @param User          $user
     */
    public function search(Request $request, BrowseFilters $criteria, User $user = null)
    {
        $count = $this->countResults($criteria, $user);

        $qbResult = $this->em->createQueryBuilder();
        $qbResult->select('f.id, f.hash, f.revision');
        $this->createSearchQueryBuilder($qbResult, $criteria, $user);

        $pagination = $this->paginator->paginate($request, $qbResult, $count, array('session_key' => 'browseFiddles'));
        $fiddles = $qbResult->getQuery()->getArrayResult();

        return array($pagination, $fiddles);
    }

    public function countResults(BrowseFilters $criteria, User $user = null)
    {
        $qbIn = $this->em->createQueryBuilder();
        $qbIn->select('f.id');
        $this->createSearchQueryBuilder($qbIn, $criteria, $user);

        $qb = $this->em->createQueryBuilder();

        $count = $qb
           ->select('COUNT(DISTINCT x.id)')
           ->from('Fuz\AppBundle\Entity\Fiddle', 'x')
           ->where(
              $qb->expr()->in('x.id', $qbIn->getDQL())
           )
           ->setParameters($qbIn->getParameters())
           ->getQuery()
           ->getSingleScalarResult()
        ;

        return $count;
    }

    public function createSearchQueryBuilder(QueryBuilder $qb, BrowseFilters $criteria, User $user = null)
    {
        $qb
           ->from('Fuz\AppBundle\Entity\Fiddle', 'f')
           ->leftJoin('Fuz\AppBundle\Entity\User', 'u',  Expr\Join::WITH, $qb->expr()->eq('f.user', ':user'))
           ->leftJoin('f.tags', 't')
           ->leftJoin('Fuz\AppBundle\Entity\UserBookmark', 'b', Expr\Join::WITH, $qb->expr()->andX(
              $qb->expr()->eq('b.fiddle', 'f.id'),
              $qb->expr()->eq('b.user', ':user')
           ))
           ->leftJoin('Fuz\AppBundle\Entity\UserBookmarkTag', 'bt', Expr\Join::WITH, $qb->expr()->eq('b.id', 'bt.userBookmark'))
           ->where(
               $qb->expr()->andX(
                   $qb->expr()->orX(
                       $qb->expr()->eq('f.visibility', ':public'),
                       $qb->expr()->eq('f.user', ':user')
                   ),
                  $this->applyBookmarkFilter($criteria, $qb, $user),
                  $this->applyMineFilter($criteria, $qb, $user),
                  $this->applyVisibilityFilter($criteria, $qb, $user)
               )
           )
           ->groupBy('f.id, b.id')
           ->having(
               $qb->expr()->andX(
                  $qb->expr()->eq(1, 1),
                  $this->applyKeywordsFilter($criteria, $qb),
                  $this->applyTagsFilter($criteria, $qb)
               )
           )
           ->orderBy('f.creationTm', 'DESC')
        ;

        $qb
           ->setParameter('public', Fiddle::VISIBILITY_PUBLIC)
           ->setParameter('user', $user)
        ;

        return $qb;
    }

    public function applyKeywordsFilter(BrowseFilters $criteria, QueryBuilder $qb)
    {
        $andF = $qb->expr()->andX();
        $andB = $qb->expr()->andX();
        foreach ($criteria->getKeywords() as $key => $keyword) {
            if (strlen($keyword) > 0) {
                $andF->add($qb->expr()->like("GROUP_CONCAT(f.title SEPARATOR ' ')", ":keyword_{$key}"));
                $andB->add($qb->expr()->like("GROUP_CONCAT(b.title SEPARATOR ' ')", ":keyword_{$key}"));
                $qb->setParameter(":keyword_{$key}", '%'.addcslashes($keyword, '_%').'%');
            }
        }

        return $qb->expr()->orX($andF, $andB);
    }

    public function applyTagsFilter(BrowseFilters $criteria, QueryBuilder $qb)
    {
        $andF = $qb->expr()->andX();
        $andB = $qb->expr()->andX();
        foreach ($criteria->getTags() as $key => $keyword) {
            if (strlen($keyword) > 0) {
                $andF->add($qb->expr()->gt("FIND_IN_SET(:tag_{$key}, GROUP_CONCAT(t.tag))", '0'));
                $andB->add($qb->expr()->gt("FIND_IN_SET(:tag_{$key}, GROUP_CONCAT(bt.tag))", '0'));
                $qb->setParameter(":tag_{$key}", $keyword);
            }
        }

        return $qb->expr()->orX($andF, $andB);
    }

    public function applyBookmarkFilter(BrowseFilters $criteria, QueryBuilder $qb, User $user = null)
    {
        if (!is_null($user) && $criteria->getBookmark()) {
            return $qb->expr()->isNotNull('b.id');
        }
    }

    public function applyMineFilter(BrowseFilters $criteria, QueryBuilder $qb, User $user = null)
    {
        if (!is_null($user) && $criteria->getMine()) {
            return $qb->expr()->eq('f.user', ':user');
        }
    }

    public function applyVisibilityFilter(BrowseFilters $criteria, QueryBuilder $qb, User $user = null)
    {
        if (!is_null($user) && $criteria->getVisibility()) {
            $qb->setParameter('visibility', $criteria->getVisibility());

            return $qb->expr()->eq('f.visibility', ':visibility');
        }
    }
}
