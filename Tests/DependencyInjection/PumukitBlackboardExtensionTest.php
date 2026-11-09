<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\BlackboardBundle\Command\ImportRecordingsCommand;
use Pumukit\BlackboardBundle\Command\SyncCoursesCommand;
use Pumukit\BlackboardBundle\DependencyInjection\PumukitBlackboardExtension;
use Pumukit\BlackboardBundle\Services\LearnAPIAuth;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitBlackboardExtensionTest extends TestCase
{
    public function testTheDisabledBundleRegistersNothing(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([], $this->blackboardServices($container));
        $this->assertFalse($container->getParameter('pumukit_blackboard.enable'));
    }

    public function testTheEnabledBundleRegistersItsCommandsAndServices(): void
    {
        $container = $this->load([
            'learn_host' => 'https://learn.example.org',
            'learn_key' => 'key',
            'learn_secret' => 'secret',
            'collaborate_host' => 'https://collaborate.example.org',
            'collaborate_key' => 'key',
            'collaborate_secret' => 'secret',
        ]);

        $this->assertTrue($container->hasDefinition(SyncCoursesCommand::class));
        $this->assertTrue($container->hasDefinition(ImportRecordingsCommand::class));
        $this->assertTrue($container->hasDefinition(LearnAPIAuth::class));
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PumukitBlackboardExtension())->load([$config], $container);

        return $container;
    }

    private function blackboardServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\BlackboardBundle\\')));
    }
}
