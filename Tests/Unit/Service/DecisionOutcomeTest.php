<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Service\DecisionOutcome;

/**
 * The confidence gate and what it falls back to. This is where a wrong answer is turned into
 * the default rather than into a mail to the wrong department.
 */
final class DecisionOutcomeTest extends TestCase
{
    #[Test]
    public function aConfidentChoiceRoutesToItsOutcomeValue(): void
    {
        $outcome = self::outcome(0.6, ['department' => self::choice('accounting', 0.95)]);

        self::assertSame('accounting@example.com', $outcome->outcomeFor('department'));
        self::assertFalse($outcome->needsHumanReview());
        self::assertNotNull($outcome->confidentAnswer('department'));
    }

    #[Test]
    public function belowTheThresholdTheDefaultWinsAndAHumanIsAsked(): void
    {
        $outcome = self::outcome(0.6, ['department' => self::choice('accounting', 0.59)]);

        self::assertSame('office@example.com', $outcome->outcomeFor('department'));
        self::assertNull($outcome->confidentAnswer('department'));
        self::assertNotNull($outcome->answer('department'), 'the raw answer is still there to inspect');
        self::assertTrue($outcome->needsHumanReview());
    }

    #[Test]
    public function anOptionWithoutAnOutcomeValueFallsBackToTheDefault(): void
    {
        $outcome = self::outcome(0.6, ['department' => self::choice('press', 0.99)]);

        self::assertSame('office@example.com', $outcome->outcomeFor('department'));
    }

    #[Test]
    public function aFallbackResultNeedsReviewAndSaysWhy(): void
    {
        $outcome = new DecisionOutcome(self::decision(0.6), DecisionResult::fallback('no API token is configured'));

        self::assertTrue($outcome->isFallback());
        self::assertTrue($outcome->needsHumanReview());
        self::assertSame('office@example.com', $outcome->outcomeFor('department'));
        self::assertSame('fallback (no API token is configured)', $outcome->summary());
    }

    #[Test]
    public function oneConfidentAnswerAmongSeveralIsEnoughToSkipReview(): void
    {
        $outcome = self::outcome(0.6, [
            'department' => self::choice('accounting', 0.2),
            'is_bug' => Answer::fromResponse('is_bug', QuestionType::Noul, ['noul' => 0.95]),
        ]);

        self::assertFalse($outcome->needsHumanReview());
        self::assertSame('department=accounting (0.20), is_bug=0.95 (0.90)', $outcome->summary());
    }

    /**
     * @param array<string, Answer> $answers
     */
    private static function outcome(float $threshold, array $answers): DecisionOutcome
    {
        return new DecisionOutcome(self::decision($threshold), new DecisionResult($answers, 'jev-test', new Usage(10)));
    }

    private static function decision(float $threshold): Decision
    {
        $question = new DecisionQuestion(1, 'department', QuestionType::Choice, 'Which?', [
            new Criterion(1, 'accounting', 'Money', 'accounting@example.com'),
            new Criterion(2, 'press', 'Media'),
        ]);

        return new Decision(1, 'routing', 'Routing', '', '', '', $threshold, -1, 'office@example.com', [$question]);
    }

    private static function choice(string $option, float $confidence): Answer
    {
        return Answer::fromResponse('department', QuestionType::Choice, ['choice' => $option, 'confidence' => $confidence]);
    }
}
