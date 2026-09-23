<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Service;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Client\JevClientInterface;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Exception\RateLimitException;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Service\StateBuilder;
use Webconsulting\WebconJev\Tests\Double\ArrayCache;
use Webconsulting\WebconJev\Tests\Double\FakeJevClient;
use Webconsulting\WebconJev\Tests\Double\TestSettings;

/**
 * Nothing here ever throws at a form. Every way a call can fail becomes a fallback carrying the
 * decision's default — and the cache and the budget guard both sit in front of the call.
 */
final class DecisionRunnerTest extends TestCase
{
    #[Test]
    public function aWorkingClientProducesAnOutcomeAndCachesIt(): void
    {
        $client = new FakeJevClient(self::answered('sales'));
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
        $client = new FakeJevClient(self::answered('sales'));
        $runner = self::runner($client, new ArrayCache(), cacheLifetime: 300);

        $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);
        $runner->run(self::decision(), ['field' => ['message' => 'Broken']]);

        self::assertSame(2, $client->calls);
    }

    #[Test]
    public function aCacheLifetimeOfZeroNeverReads(): void
    {
        $client = new FakeJevClient(self::answered('sales'));
        $runner = self::runner($client, new ArrayCache(), cacheLifetime: 0);

        $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);
        $runner->run(self::decision(), ['field' => ['message' => 'Buy']]);

        self::assertSame(2, $client->calls);
    }

    #[Test]
    public function aClientFailureBecomesAFallbackNotAnException(): void
    {
        $client = new FakeJevClient(new RateLimitException('Jev is rate limiting'));
        $outcome = self::runner($client, new ArrayCache())->run(self::decision(), ['field' => ['message' => 'x']]);

        self::assertTrue($outcome->isFallback());
        self::assertSame('Jev is rate limiting', $outcome->result->fallbackReason);
        self::assertSame('office@example.com', $outcome->outcomeFor('department'));
    }

    #[Test]
    public function aFallbackIsNeverCached(): void
    {
        $client = new FakeJevClient(new RateLimitException('busy'));
        $cache = new ArrayCache();
        self::runner($client, $cache, cacheLifetime: 300)->run(self::decision(), ['field' => ['message' => 'x']]);

        self::assertSame([], array_filter(array_keys($cache->entries), static fn(string $k): bool => !str_starts_with($k, 'rate_')));
    }

    #[Test]
    public function anUnconfiguredClientFallsBackWithoutBeingAsked(): void
    {
        $client = new FakeJevClient(self::answered('sales'), configured: false);
        $outcome = self::runner($client, new ArrayCache())->run(self::decision(), ['field' => ['message' => 'x']]);

        self::assertTrue($outcome->isFallback());
        self::assertSame('no API token is configured', $outcome->result->fallbackReason);
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aDisabledExtensionFallsBackWithoutBeingAsked(): void
    {
        $client = new FakeJevClient(self::answered('sales'));
        $outcome = self::runner($client, new ArrayCache(), enabled: false)->run(self::decision(), ['field' => []]);

        self::assertTrue($outcome->isFallback());
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aDecisionWithoutQuestionsFallsBackWithoutBeingAsked(): void
    {
        $client = new FakeJevClient(self::answered('sales'));
        $empty = new Decision(2, 'empty', 'Empty', '', '', '', 0.6, -1, 'office@example.com', []);
        $outcome = self::runner($client, new ArrayCache())->run($empty, ['field' => []]);

        self::assertTrue($outcome->isFallback());
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function aQuestionTheApiWouldRefuseFallsBackInsteadOfThrowing(): void
    {
        // A choice left with one option — possible after an edit in the record editor, which does
        // not know the API's rules. It used to throw straight through the powermail integrations.
        $client = new FakeJevClient(self::answered('sales'));
        $lonely = new Decision(3, 'lonely', 'Lonely', '', '', '', 0.6, -1, 'office@example.com', [
            new DecisionQuestion(1, 'department', QuestionType::Choice, 'Which?', [new Criterion(1, 'sales', 'Buying')]),
        ]);

        $outcome = self::runner($client, new ArrayCache())->run($lonely, ['field' => ['message' => 'x']]);

        self::assertTrue($outcome->isFallback());
        self::assertStringContainsString('at least two options', (string)$outcome->result->fallbackReason);
        self::assertSame('office@example.com', $outcome->outcomeFor('department'));
        self::assertSame(0, $client->calls);
    }

    #[Test]
    public function theBudgetGuardStopsCallsBeyondTheLimit(): void
    {
        $client = new FakeJevClient(self::answered('sales'));
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
        $settings = TestSettings::with([
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
