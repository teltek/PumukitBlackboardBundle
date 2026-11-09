<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const CREDENTIALS = ['learn_host', 'learn_key', 'learn_secret', 'collaborate_host', 'collaborate_key', 'collaborate_secret'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_blackboard');

        $treeBuilder->getRootNode()
            ->validate()
            ->ifTrue(static fn (array $config): bool => $config['enable'] && [] !== self::missingCredentials($config))
            ->then(static function (array $config): never {
                throw new \InvalidArgumentException('pumukit_blackboard needs '.implode(', ', self::missingCredentials($config)).' when it is enabled');
            })
            ->end()
            ->children()
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('If false, nothing of the bundle is loaded (no commands nor services) and the credentials are not required')
            ->end()
            ->scalarNode('learn_host')
            ->defaultNull()
            ->info('Host of Blackboard Learn, with the scheme')
            ->end()
            ->scalarNode('learn_key')
            ->defaultNull()
            ->info('Application key of the Learn REST API')
            ->end()
            ->scalarNode('learn_secret')
            ->defaultNull()
            ->info('Application secret of the Learn REST API')
            ->end()
            ->scalarNode('collaborate_host')
            ->defaultNull()
            ->info('Host of the Collaborate REST API, with the scheme')
            ->end()
            ->scalarNode('collaborate_key')
            ->defaultNull()
            ->info('Key of the Collaborate REST API')
            ->end()
            ->scalarNode('collaborate_secret')
            ->defaultNull()
            ->info('Secret of the Collaborate REST API, used to sign the JWT')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private static function missingCredentials(array $config): array
    {
        return array_values(array_filter(self::CREDENTIALS, static fn (string $key): bool => '' === (string) ($config[$key] ?? '')));
    }
}
