<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Powermail;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Client\Dto\Usage;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Powermail\RoutingDecision;
use Webconsulting\WebconJev\Service\DecisionOutcome;

/**
 * The visitor reads one sentence about where the submission went. It has to say which of four
 * things happened, not only the address.
 */
final class RoutingDecisionTest extends TestCase
{
    #[Test]
    public function theSentenceSaysWhatHappened(): void
    {
        self::assertSame('routing.sent', self::routing(self::choice('accounting', 0.95))->messageKey());
        self::assertSame('routing.unsure', self::routing(self::choice('accounting', 0.4))->messageKey());
        self::assertSame('routing.default', self::routing(self::choice('press', 0.95))->messageKey(), 'a confident option without an address');

        $fallback = new RoutingDecision(
            new DecisionOutcome(self::decision(), DecisionResult::fallback('no API token is configured')),
            'department',
            ['office@example.com'],
            1,
            '',
        );
        self::assertSame('routing.fallback', $fallback->messageKey());
    }

    #[Test]
    public function theCertaintyIsWrittenTheWayTheLocaleWritesAPercentage(): void
    {
        $routing = self::routing(self::choice('accounting', 0.954));

        self::assertSame('95%', $routing->confidence('en-US'));
        self::assertMatchesRegularExpression('/^95\h%$/u', $routing->confidence('de-AT'));
    }

    private static function routing(Answer $answer): RoutingDecision
    {
        return new RoutingDecision(
            new DecisionOutcome(self::decision(), new DecisionResult(['department' => $answer], 'jev-test', new Usage(10))),
            'department',
            ['accounting@example.com'],
            1,
            '',
        );
    }

    private static function decision(): Decision
    {
        $question = new DecisionQuestion(1, 'department', QuestionType::Choice, 'Which?', [
            new Criterion(1, 'accounting', 'Money', 'accounting@example.com'),
            new Criterion(2, 'press', 'Media'),
        ]);

        return new Decision(1, 'routing', 'Routing', '', '', '', 0.6, -1, 'office@example.com', [$question]);
    }

    private static function choice(string $option, float $confidence): Answer
    {
        return Answer::fromResponse('department', QuestionType::Choice, ['choice' => $option, 'confidence' => $confidence]);
    }
}
