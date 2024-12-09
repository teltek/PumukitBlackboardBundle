<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_blackboard');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('learn_host')
            ->info('Host of blackboard learn.')
            ->isRequired()
            ->end()
            ->scalarNode('learn_key')
            ->info('Application key used to connect with blackboard')
            ->isRequired()
            ->end()
            ->scalarNode('learn_secret')
            ->info('Application secret used to connect with blackboard')
            ->isRequired()
            ->end()
            ->scalarNode('collaborate_host')
            ->info('Host of blackboard collaborate')
            ->isRequired()
            ->end()
            ->scalarNode('collaborate_key')
            ->info('API key used to connect with blackboard collaborate')
            ->isRequired()
            ->end()
            ->scalarNode('collaborate_secret')
            ->info('API secret used to connect with blackboard collaborate')
            ->isRequired()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
