<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Webconsulting\WebconJev\Configuration\Settings;

/**
 * TYPO3 stores every value as a string. The cases that matter are the ones where a wrong reading
 * would be silently permissive or silently expensive.
 */
final class SettingsTest extends TestCase
{
    #[Test]
    public function valuesAreReadAsTheTypesTheirConsumersNeed(): void
    {
        $settings = self::settings([
            'tokenIdentifier' => ' my_key ',
            'model' => 'jev-1.13.0',
            'endpoint' => 'https://example.invalid/v1',
            'enabled' => '0',
            'timeout' => '7',
            'cacheLifetime' => '0',
            'maxCallsPerMinute' => '3',
            'logRuns' => '1',
            'logRetentionDays' => '2',
            'storagePid' => '44',
        ]);

        self::assertSame('my_key', $settings->tokenIdentifier());
        self::assertSame('jev-1.13.0', $settings->model());
        self::assertFalse($settings->isEnabled());
        self::assertSame(7, $settings->timeout());
        self::assertSame(0, $settings->cacheLifetime());
        self::assertSame(3, $settings->maxCallsPerMinute());
        self::assertTrue($settings->logRuns());
        self::assertSame(2, $settings->logRetentionDays());
        self::assertSame(44, $settings->storagePid());
    }

    #[Test]
    public function anUnconfiguredExtensionFallsBackToSafeDefaults(): void
    {
        $configuration = self::createStub(ExtensionConfiguration::class);
        $configuration->method('get')->willThrowException(new ExtensionConfigurationExtensionNotConfiguredException());
        $settings = new Settings($configuration);

        self::assertSame('typesafe_api_key', $settings->tokenIdentifier());
        self::assertSame('jev-latest', $settings->model());
        self::assertTrue($settings->isEnabled());
        self::assertSame(10, $settings->timeout());
        self::assertSame(300, $settings->cacheLifetime());
        self::assertSame(120, $settings->maxCallsPerMinute());
    }

    #[Test]
    public function nonsenseNeverProducesAZeroTimeoutOrANegativeBudget(): void
    {
        $settings = self::settings(['timeout' => '0', 'maxCallsPerMinute' => '-5', 'logRetentionDays' => '0']);

        self::assertSame(1, $settings->timeout());
        self::assertSame(0, $settings->maxCallsPerMinute());
        self::assertSame(1, $settings->logRetentionDays());
    }

    /**
     * @param array<string, string> $values
     */
    private static function settings(array $values): Settings
    {
        $configuration = new readonly class ($values) extends ExtensionConfiguration {
            /** @param array<string, string> $values */
            public function __construct(private readonly array $values) {}

            public function get(string $extension, string $path = ''): mixed
            {
                return $this->values;
            }
        };

        return new Settings($configuration);
    }
}
