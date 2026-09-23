<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Double;

use LogicException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Just enough of the caching framework to prove reads, writes and lifetimes.
 */
final class ArrayCache implements FrontendInterface
{
    /** @var array<string, mixed> */
    public array $entries = [];

    public function getIdentifier(): string
    {
        return 'test';
    }

    public function getBackend(): never
    {
        throw new LogicException('not needed');
    }

    /**
     * @param list<string> $tags
     */
    public function set(string $entryIdentifier, mixed $data, array $tags = [], ?int $lifetime = null): void
    {
        $this->entries[$entryIdentifier] = $data;
    }

    public function get(string $entryIdentifier): mixed
    {
        return $this->entries[$entryIdentifier] ?? false;
    }

    public function has(string $entryIdentifier): bool
    {
        return array_key_exists($entryIdentifier, $this->entries);
    }

    public function remove(string $entryIdentifier): bool
    {
        unset($this->entries[$entryIdentifier]);

        return true;
    }

    public function flush(): void
    {
        $this->entries = [];
    }

    public function flushByTag(string $tag): void {}

    public function flushByTags(array $tags): void {}

    public function collectGarbage(): void {}

    public function isValidEntryIdentifier(string $identifier): bool
    {
        return true;
    }

    public function isValidTag(string $tag): bool
    {
        return true;
    }

    public function requireOnce(string $entryIdentifier): mixed
    {
        return null;
    }
}
