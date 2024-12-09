<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PumukitBlackboardExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_blackboard.learn_host', $config['learn_host']);
        $container->setParameter('pumukit_blackboard.learn_key', $config['learn_key']);
        $container->setParameter('pumukit_blackboard.learn_secret', $config['learn_secret']);

        $container->setParameter('pumukit_blackboard.collaborate_host', $config['collaborate_host']);
        $container->setParameter('pumukit_blackboard.collaborate_key', $config['collaborate_key']);
        $container->setParameter('pumukit_blackboard.collaborate_secret', $config['collaborate_secret']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_blackboard.yaml');
    }
}
