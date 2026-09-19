<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Configuration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use Webconsulting\WebconJev\Support\Cast;

/**
 * The extension configuration, read once and typed.
 *
 * Shared by the container, so the configuration is parsed once per request.
 */
final class Settings
{
    public const EXTENSION_KEY = 'webcon_jev';

    /** @var array<string, mixed> */
    private readonly array $raw;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        try {
            $raw = $extensionConfiguration->get(self::EXTENSION_KEY);
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            $raw = [];
        }
        $this->raw = Cast::map($raw);
    }

    public function tokenIdentifier(): string
    {
        return $this->string('tokenIdentifier', 'typesafe_api_key');
    }

    public function model(): string
    {
        return $this->string('model', 'jev-latest');
    }

    public function endpoint(): string
    {
        return $this->string('endpoint', 'https://api.typesafe.ai/v1/systemone');
    }

    public function isEnabled(): bool
    {
        return $this->bool('enabled', true);
    }

    public function timeout(): int
    {
        return max(1, $this->int('timeout', 10));
    }

    public function cacheLifetime(): int
    {
        return max(0, $this->int('cacheLifetime', 300));
    }

    public function maxCallsPerMinute(): int
    {
        return max(0, $this->int('maxCallsPerMinute', 120));
    }

    public function logRuns(): bool
    {
        return $this->bool('logRuns', true);
    }

    public function logRetentionDays(): int
    {
        return max(1, $this->int('logRetentionDays', 30));
    }

    public function storagePid(): int
    {
        return max(0, $this->int('storagePid', 0));
    }

    private function string(string $key, string $default): string
    {
        return Cast::trimmed($this->raw[$key] ?? null, $default);
    }

    private function int(string $key, int $default): int
    {
        return isset($this->raw[$key]) && $this->raw[$key] !== ''
            ? Cast::int($this->raw[$key], $default)
            : $default;
    }

    private function bool(string $key, bool $default): bool
    {
        return isset($this->raw[$key]) && $this->raw[$key] !== ''
            ? Cast::bool($this->raw[$key], $default)
            : $default;
    }
}
