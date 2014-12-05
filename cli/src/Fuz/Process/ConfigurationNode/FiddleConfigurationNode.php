<?php

namespace Fuz\Process\ConfigurationNode;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Fuz\Framework\Api\ConfigurationNodeInterface;

class FiddleConfigurationNode implements ConfigurationNodeInterface
{

    public function getConfigurationNode()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('fiddle');

        $rootNode
           ->children()
                ->scalarNode("file")
                    ->defaultValue('fiddle.shr')
                ->end()
                ->scalarNode('templates_dir')
                    ->defaultValue('templates/')
                ->end()
                ->scalarNode('compiled_dir')
                    ->defaultValue('compiled/')
                ->end()
           ->end()
        ;

        return $rootNode;
    }

}