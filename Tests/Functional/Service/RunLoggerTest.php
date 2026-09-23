<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * The run log is the only place a fallback is visible after the fact, so what it records — and
 * what the module's totals add up — is a contract, not bookkeeping.
 */
final class RunLoggerTest extends AbstractJevTestCase
{
    private RunLogger $runLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runLogger = $this->get(RunLogger::class);
    }

    #[Test]
    public function aRealCallIsRecordedWithItsCostAndAnswers(): void
    {
        $result = new DecisionResult(
            ['department' => Answer::fromResponse('department', QuestionType::Choice, ['choice' => 'sales', 'confidence' => 0.9])],
            'jev-1.13.0',
            new Usage(500, 0),
            durationMs: 812.5,
        );

        $this->runLogger->log(self::decision(), $result, RunLogger::CONTEXT_FINISHER, 'form 5, mail 9', 'abc');

        $row = $this->row(RunLogger::TABLE, 1);
        self::assertSame('routing', $row['decision_identifier']);
        self::assertSame('powermail_finisher', $row['context']);
        self::assertSame('form 5, mail 9', $row['origin']);
        self::assertSame('jev-1.13.0', $row['model']);
        self::assertSame(500, (int)$row['input_tokens']);
        self::assertEqualsWithDelta(500 * 0.042 / 1_000_000, (float)$row['cost_usd'], 1e-12);
        self::assertSame(0, (int)$row['is_fallback']);
        self::assertSame(0, (int)$row['from_cache']);
        self::assertSame('sales', json_decode((string)$row['answers'], true)['department']['value'] ?? null);
    }

    #[Test]
    public function aFallbackIsRecordedWithItsReasonAndNoCost(): void
    {
        $this->runLogger->log(self::decision(), DecisionResult::fallback('no API token is configured'), RunLogger::CONTEXT_CONDITION);

        $row = $this->row(RunLogger::TABLE, 1);
        self::assertSame(1, (int)$row['is_fallback']);
        self::assertSame('no API token is configured', $row['fallback_reason']);
        self::assertSame(0, (int)$row['input_tokens']);
        self::assertSame('[]', $row['answers']);
    }

    #[Test]
    public function aDecisionBuiltInCodeIsLoggedUnderUidZeroAndItsIdentifier(): void
    {
        // Run for real: with no token configured, the runner falls back — and logs it.
        $outcome = $this->get(DecisionRunner::class)->run(self::builtInCode(), ['part' => 'text'], 'my_extension_import', 'page 42');

        self::assertTrue($outcome->isFallback());
        $row = $this->row(RunLogger::TABLE, 1);
        self::assertSame(0, (int)$row['decision']);
        self::assertSame('my_extension.content_type', $row['decision_identifier']);
        self::assertSame('my_extension_import', $row['context']);
        self::assertSame('page 42', $row['origin']);
        self::assertSame(2, (int)$row['question_count']);
        self::assertSame('no API token is configured', $row['fallback_reason']);
    }

    #[Test]
    public function whatDoesNotFitItsColumnIsCutRatherThanRefused(): void
    {
        // On MariaDB in strict mode an over-long value fails the insert — and with it the run.
        $decision = new Decision(0, str_repeat('i', 80), '', '', '', '', 0.6, -1, '', []);
        $result = new DecisionResult([], str_repeat('m', 80), new Usage());

        $this->runLogger->log($decision, $result, str_repeat('c', 40));

        $row = $this->row(RunLogger::TABLE, 1);
        self::assertSame(str_repeat('i', 64), $row['decision_identifier']);
        self::assertSame(str_repeat('c', 32), $row['context']);
        self::assertSame(str_repeat('m', 64), $row['model']);
    }

    #[Test]
    public function totalsSeparateRealCallsFromCachedAndFallenBack(): void
    {
        $decision = self::decision();
        $real = new DecisionResult([], 'm', new Usage(100), durationMs: 200.0);

        $this->runLogger->log($decision, $real, RunLogger::CONTEXT_PLAYGROUND);
        $this->runLogger->log($decision, $real->withCacheFlag(true), RunLogger::CONTEXT_PLAYGROUND);
        $this->runLogger->log($decision, DecisionResult::fallback('busy'), RunLogger::CONTEXT_PLAYGROUND);

        $totals = $this->runLogger->totalsSince(0);

        self::assertSame(3, $totals['runs']);
        self::assertSame(1, $totals['calls'], 'only the uncached, non-fallback run reached the API');
        self::assertSame(1, $totals['cached']);
        self::assertSame(1, $totals['fallbacks']);
        self::assertSame(100, $totals['inputTokens'], 'a reused answer is not billed again');
        self::assertEqualsWithDelta(100 * 0.042 / 1_000_000, $totals['costUsd'], 1e-12);
        self::assertSame(200.0, $totals['avgDurationMs'], 'nor does it count towards the latency');
        self::assertSame(100, (int)$this->row(RunLogger::TABLE, 2)['input_tokens'], 'the cached row keeps what its answer was computed on');
        self::assertCount(3, $this->runLogger->recent());
        self::assertCount(3, $this->runLogger->recent(decisionUid: 1));
        self::assertCount(0, $this->runLogger->recent(decisionUid: 99));
    }

    #[Test]
    public function pruningDeletesOnlyWhatIsOlderThanTheCutoff(): void
    {
        $decision = self::decision();
        $this->runLogger->log($decision, DecisionResult::fallback('old'), RunLogger::CONTEXT_CLI);
        $this->getConnectionPool()->getConnectionForTable(RunLogger::TABLE)
            ->update(RunLogger::TABLE, ['crdate' => 1000], ['uid' => 1]);
        $this->runLogger->log($decision, DecisionResult::fallback('new'), RunLogger::CONTEXT_CLI);

        self::assertSame(1, $this->runLogger->pruneBefore(2000));
        self::assertCount(1, $this->runLogger->recent());
        self::assertSame('new', $this->runLogger->recent()[0]['fallback_reason']);
    }

    private static function decision(): Decision
    {
        return new Decision(1, 'routing', 'Routing', '', '', '', 0.6, -1, 'office@example.com', []);
    }

    private static function builtInCode(): Decision
    {
        $options = [new Criterion(0, 'text', 'Running text', 'text'), new Criterion(0, 'table', 'Rows and columns', 'table')];

        return new Decision(0, 'my_extension.content_type', 'Content type', '', '', '', 0.6, -1, 'text', [
            new DecisionQuestion(0, 'part_1', QuestionType::Choice, 'Which content element fits this part?', $options),
            new DecisionQuestion(0, 'part_2', QuestionType::Choice, 'Which content element fits this part?', $options),
        ]);
    }
}
