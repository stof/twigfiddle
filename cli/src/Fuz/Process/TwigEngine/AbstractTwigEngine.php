<?php

/*
 * This file is part of twigfiddle.com project.
 *
 * (c) Alain Tiemblo <alain@fuz.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fuz\Process\TwigEngine;

use Fuz\Framework\Base\BaseService;
use Fuz\Process\Agent\FiddleAgent;

abstract class AbstractTwigEngine extends BaseService implements TwigEngineInterface
{
    /**
     * @var FiddleAgent
     */
    protected $agent;

    public function __construct(FiddleAgent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * From all released versions of Twig, there are backward compatibility to render a template.
     *
     * @param \Twig_Environment $twigEnvironment
     * @param string            $template
     * @param array             $context
     *
     * @return string
     */
    public function render(\Twig_Environment $twigEnvironment, $template, array $context = array())
    {
        $templateObject = $twigEnvironment->loadTemplate($template);

        ob_start();
        $templateObject->display($context);
        $result = ob_get_clean();

        return $result;
    }

    /**
     * The first coomment of all compiled twig file contains the twig file name since the very first Twig's version.
     * This method just extracts it.
     *
     * @param string $cacheDirectory
     * @param array  $files
     *
     * @return array
     */
    public function extractTemplateName($content)
    {
        $templateName = null;
        $tokens = token_get_all($content);
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                continue;
            }
            list($identifier, $string) = $token;
            if ($identifier !== T_COMMENT) {
                continue;
            }
            $templateName = trim(str_replace(array('/*', '*/'), '', $string));
            break;
        }

        return $templateName;
    }

    abstract public function getName();
}
