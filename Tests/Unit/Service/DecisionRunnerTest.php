<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Service;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Throwable;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Client\JevClientInterface;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Exception\RateLimitException;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\StateBuilder;

/**
 * Nothing here ever throws at a form. Every way a call can fail becomes a fallback carrying the
 * decision's default — and the cache and the budget guard both sit in front of the call.
 */
final class DecisionRunnerTest extends TestCase
{
    #[Test]
    public function aWorkingClientProducesAnOutcomeAndCachesIt(): void
    {
        $client = new FakeClient(self::answered('sales'));
        $cache = new ArrayCache();
        $runner = self::runner($client, $cache, cacheLifetime: 300);

        $first = $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);
        $second = $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);

        self::assertSame('sales', $first->answer('department')?->choice);
        self::assertFalse($first->result->fromCache);
        self::assertTrue($second->result->fromCache, 'the identical question reuses its answer');
        self::assertSame(1, $client->calls, 'the API was asked once');
    }

    #[Test]
    public function aDifferentStateIsADifferentQuestion(): void
    {
        $client = new FakeClient(self::answered('sales'));
        $runner = self::runner($client, new ArrayCache(), cacheLifetime: 300);

        $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);
        $runner->run(self::decision(), ['field' => ['message' => 'Broken']]);

        self::assertSame(2, $client->calls);
    }

    #[Test]
    public function aCacheLifetimeOfZeroNeverReads(): void
    {
        $client = new FakeClient(self::answered('sales'));
        $runner = self::runner($client, new ArrayCache(), cacheLifetime: 0);

        $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);
        $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);

        self::assertSame(2, $client->calls);
    }

    #[Test]
    public function aClientFailureBecomesAFallbackNotAnException(): void
    {
        $client = new FakeClient(new RateLimitException('Jev is rate limiting'));
        $outcome = self::runner($client, new ArrayCache())->run(self::decision(), ['field' => ['message' => 'x']]);

        self::assertTrue($outcome->isFallback());
        self::assertSame('Jev is rate limiting', $outcome->result->fallbackReason);
        self::assertSame('office@example.com', $outcome->outcomeFor('department'));
    }

    #[Test]
    public function aFallbackIsNeverCached(): void
    {
        $client = new FakeClient(new RateLimitException('busy'));
        $cache = new ArrayCache();
        self::runner($client, $cache, cacheLifetime: 300)->run(self::decision(), ['field' => ['message' => 'x']]);

        self::assertSame([], array_filter(array_keys($cache->entries), static fn(string $k): bool => !str_starts_with($k, 'rate_')));
    }

    #[Test]
    public function anUnconfiguredClientFallsBackWithoutBeingAsked(): void
    {
        $client = new FakeClient(self::answered('sales'), configured: false);
        $outcome = self::runner($client, new ArrayCache())->run(self::decision(), ['field' => ['message' => 'x']]);

        self::assertTrue($outcome->isFallback());
        self::assertSame('no API token is configured', $outcome->result->fallbackReason);
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aDisabledExtensionFallsBackWithoutBeingAsked(): void
    {
        $client = new FakeClient(self::answered('sales'));
        $outcome = self::runner($client, new ArrayCache(), enabled: false)->run(self::decision(), ['field' => []]);

        self::assertTrue($outcome->isFallback());
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aDecisionWithoutQuestionsFallsBackWithoutBeingAsked(): void
    {
        $client = new FakeClient(self::answered('sales'));
        $empty = new Decision(2, 'empty', 'Empty', '', '', '', 0.6, -1, 'office@example.com', []);
        $outcome = self::runner($client, new ArrayCache())->run($empty, ['field' => []]);

        self::assertTrue($outcome->isFallback());
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function theBudgetGuardStopsCallsBeyondTheLimit(): void
    {
        $client = new FakeClient(self::answered('sales'));
        $runner = self::runner($client, new ArrayCache(), cacheLifetime: 0, maxCallsPerMinute: 2);

        $runner->run(self::decision(), ['field' => ['message' => 'a']]);
        $runner->run(self::decision(), ['field' => ['message' => 'b']]);
        $third = $runner->run(self::decision(), ['field' => ['message' => 'c']]);

        self::assertSame(2, $client->calls);
        self::assertTrue($third->isFallback());
        self::assertStringContainsString('budget guard', (string)$third->result->fallbackReason);
    }

    private static function runner(
        JevClientInterface $client,
        FrontendInterface $cache,
        int $cacheLifetime = 0,
        int $maxCallsPerMinute = 0,
        bool $enabled = true,
    ): DecisionRunner {
        $settings = self::settings([
            'cacheLifetime' => (string)$cacheLifetime,
            'maxCallsPerMinute' => (string)$maxCallsPerMinute,
            'enabled' => $enabled ? '1' : '0',
            'logRuns' => '0',
        ]);

        // With logRuns off the logger never touches the pool, so a mock that must not be called
        // is the honest dependency.
        $pool = self::createMockPool();

        return new DecisionRunner(
            $client,
            new StateBuilder(),
            new RunLogger($pool, $settings),
            $settings,
            $cache,
            new NullLogger(),
        );
    }

    private static function createMockPool(): ConnectionPool
    {
        return new class extends ConnectionPool {
            public function getConnectionForTable(string $tableName): never
            {
                throw new LogicException('The run log must not be written when logRuns is off.');
            }
        };
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

    private static function decision(): Decision
    {
        $question = new DecisionQuestion(1, 'department', QuestionType::Choice, 'Which?', [
            new Criterion(1, 'sales', 'Buying', 'sales@example.com'),
            new Criterion(2, 'support', 'Broken', 'support@example.com'),
        ]);

        return new Decision(1, 'routing', 'Routing', '', '', '', 0.6, Decision::CACHE_LIFETIME_INHERIT, 'office@example.com', [$question]);
    }

    private static function answered(string $choice): DecisionResult
    {
        return new DecisionResult(
            ['department' => Answer::fromResponse('department', QuestionType::Choice, ['choice' => $choice, 'confidence' => 0.9])],
            'jev-test',
            new Usage(100),
            durationMs: 12.0,
        );
    }
}

/**
 * A client that answers with what the test told it to, or throws it.
 */
final class FakeClient implements JevClientInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly DecisionResult|Throwable $response,
        private readonly bool $configured = true,
    ) {}

    public function ask(string|array|null $state, array $questions, ?string $model = null): DecisionResult
    {
        $this->calls++;
        foreach ($questions as $question) {
            assert($question instanceof Question);
        }
        if ($this->response instanceof Throwable) {
            throw $this->response;
        }

        return $this->response;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}

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
