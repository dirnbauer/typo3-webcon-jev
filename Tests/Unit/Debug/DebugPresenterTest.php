<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Debug;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Debug\DebugLog;
use Webconsulting\WebconJev\Debug\DebugPresenter;
use Webconsulting\WebconJev\Debug\DecisionTrace;
use Webconsulting\WebconJev\Debug\RoutingTrace;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Service\DecisionOutcome;
use Webconsulting\WebconJev\Service\StateBuilder;

/**
 * What the panel says about an answer. Rules need their powermail_cond rows and are covered by
 * the functional test; everything here is formatting of what Jev returned.
 */
final class DebugPresenterTest extends TestCase
{
    #[Test]
    public function aChoiceListsItsWholeDistributionMostLikelyFirst(): void
    {
        $answer = self::presentAnswer(Answer::fromResponse('queue', QuestionType::Choice, [
            'choice' => 'engineering',
            'confidence' => 0.9,
            'probabilities' => ['first_line' => 0.05, 'engineering' => 0.9, 'account' => 0.05],
        ]));

        self::assertSame('engineering', $answer['value']);
        self::assertSame('90 %', $answer['confidencePercent']);
        self::assertTrue($answer['confident']);
        self::assertSame(['engineering', 'first_line', 'account'], array_column($answer['options'], 'label'));
        self::assertSame([true, false, false], array_column($answer['options'], 'chosen'));
    }

    #[Test]
    public function aScoreStepIsNamedByItsPositionAndDescription(): void
    {
        $answer = self::presentAnswer(Answer::fromResponse('severity', QuestionType::Score, [
            'score' => 2.84,
            'confidence' => 0.84,
            'probabilities' => [0.0, 0.0, 0.16, 0.84],
        ]));

        self::assertSame('2.84 ≈ 3 · Completely', $answer['value']);
        self::assertSame(
            ['0 · Not at all', '1 · A little', '2 · A lot', '3 · Completely'],
            array_column($answer['options'], 'label'),
            'rubric order, not sorted by probability',
        );
        self::assertSame([false, false, false, true], array_column($answer['options'], 'chosen'));
    }

    #[Test]
    public function aNoulSaysItsConfidenceIsDerivedAndBelowTheThresholdIsFlagged(): void
    {
        $answer = self::presentAnswer(Answer::fromResponse('is_bug', QuestionType::Noul, ['noul' => 0.6]));

        self::assertSame('0.60', $answer['value']);
        self::assertTrue($answer['derived']);
        self::assertSame('20 %', $answer['confidencePercent']);
        self::assertFalse($answer['confident'], 'derived 0.20 is below the decision threshold of 0.55');
        self::assertSame([], $answer['options']);
    }

    #[Test]
    public function aFallbackIsReportedWithItsReasonAndNoCost(): void
    {
        $log = new DebugLog();
        $log->trace('conditions:1:1', new DecisionTrace(
            DecisionTrace::CONDITIONS,
            new DecisionOutcome(self::decision(), DecisionResult::fallback('no API token is configured')),
            [],
        ));

        $decision = self::presenter()->present($log)['decisions'][0];

        self::assertSame('fallback', $decision['status']);
        self::assertSame('no API token is configured', $decision['fallbackReason']);
        self::assertSame('', $decision['cost']);
        self::assertSame('', $decision['duration']);
        self::assertSame([], $decision['answers']);
    }

    #[Test]
    public function routingShowsTheReceiversAndWhetherTheDefaultStoodIn(): void
    {
        $log = new DebugLog();
        $log->trace('routing:7', new DecisionTrace(
            DecisionTrace::ROUTING,
            new DecisionOutcome(self::decision(), new DecisionResult([], 'jev-1.13.0', new Usage(460), 329.4)),
            ['field' => ['message' => 'Invoice twice']],
        ))->setRouting(new RoutingTrace('department', 'office@example.com', ['office@example.com'], true));

        $decision = self::presenter()->present($log)['decisions'][0];

        self::assertSame('routing', $decision['kind']);
        self::assertSame('live', $decision['status']);
        self::assertSame('329 ms', $decision['duration']);
        self::assertSame('$0.000019', $decision['cost']);
        self::assertSame(
            ['question' => 'department', 'outcome' => 'office@example.com', 'receivers' => ['office@example.com'], 'applied' => true, 'usedDefault' => true],
            $decision['routing'],
        );
        self::assertStringContainsString('Invoice twice', $decision['state'], 'the state Jev read is shown');
    }

    #[Test]
    public function notesComeThroughOnceEach(): void
    {
        $log = new DebugLog();
        $log->note('Rule 3 has no decision or question and never applies.');
        $log->note('Rule 3 has no decision or question and never applies.');

        self::assertSame(['Rule 3 has no decision or question and never applies.'], self::presenter()->present($log)['notes']);
        self::assertFalse($log->isEmpty());
    }

    /**
     * The panel's view of one answer, as the only answer of a live decision.
     *
     * @return array<string, mixed>
     */
    private static function presentAnswer(Answer $answer): array
    {
        $log = new DebugLog();
        $log->trace('conditions:1:1', new DecisionTrace(
            DecisionTrace::CONDITIONS,
            new DecisionOutcome(self::decision(), new DecisionResult([$answer->name => $answer], 'jev-1.13.0', new Usage(500), 400.0)),
            [],
        ));

        $answers = self::presenter()->present($log)['decisions'][0]['answers'];
        self::assertIsArray($answers);
        self::assertCount(1, $answers);
        self::assertIsArray($answers[0]);

        return $answers[0];
    }

    private static function presenter(): DebugPresenter
    {
        // No rules in these traces, so the connection pool is never asked.
        return new DebugPresenter(new StateBuilder(), new ConnectionPool());
    }

    private static function decision(): Decision
    {
        return new Decision(
            1,
            'jev_support_triage',
            'Support triage',
            '',
            'Message: {{field.message}}',
            '',
            0.55,
            -1,
            'office@example.com',
            [
                new DecisionQuestion(1, 'is_bug', QuestionType::Noul, 'Is it a bug?', [
                    new Criterion(1, 'yes', 'Something is broken.'),
                    new Criterion(2, 'no', 'A question.'),
                ]),
                new DecisionQuestion(2, 'severity', QuestionType::Score, 'How bad?', [
                    new Criterion(3, '', 'Not at all: a question.'),
                    new Criterion(4, '', 'A little: there is a workaround.'),
                    new Criterion(5, '', 'A lot: no workaround.'),
                    new Criterion(6, '', 'Completely: the system is down.'),
                ]),
                new DecisionQuestion(3, 'queue', QuestionType::Choice, 'Who answers?', [
                    new Criterion(7, 'first_line', 'Usage'),
                    new Criterion(8, 'engineering', 'Code'),
                    new Criterion(9, 'account', 'Contract'),
                ]),
            ],
        );
    }
}
