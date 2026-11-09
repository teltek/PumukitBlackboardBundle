<?php

declare(strict_types=1);

namespace Pumukit\BlackboardBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\BlackboardBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    private const CREDENTIALS = [
        'learn_host' => 'https://learn.example.org',
        'learn_key' => 'key',
        'learn_secret' => 'secret',
        'collaborate_host' => 'https://collaborate.example.org',
        'collaborate_key' => 'key',
        'collaborate_secret' => 'secret',
    ];

    public function testTheEnabledBundleNeedsEveryCredential(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('collaborate_secret');

        $this->process(['collaborate_secret' => ''] + self::CREDENTIALS);
    }

    public function testTheEnabledBundleWithItsCredentialsIsValid(): void
    {
        $this->assertTrue($this->process(self::CREDENTIALS)['enable']);
    }

    public function testTheDisabledBundleNeedsNoCredentials(): void
    {
        $this->assertFalse($this->process(['enable' => false])['enable']);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
