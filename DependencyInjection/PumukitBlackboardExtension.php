<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class PumukitBlackboardExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('pumukit_blackboard.enable', $config['enable']);
        foreach (Configuration::CREDENTIALS as $key) {
            $container->setParameter('pumukit_blackboard.'.$key, (string) $config[$key]);
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_blackboard.yaml');
    }
}
