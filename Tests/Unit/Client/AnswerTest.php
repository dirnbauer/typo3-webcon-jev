<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\QuestionType;

/**
 * The three primitives come back in three shapes. What each one carries, and what confidence
 * means for a noul — which has none of its own — is the contract every gate in this extension
 * reads.
 */
final class AnswerTest extends TestCase
{
    #[Test]
    public function aChoiceCarriesTheWinnerAndTheWholeDistribution(): void
    {
        $answer = Answer::fromResponse('department', QuestionType::Choice, [
            'choice' => 'sales',
            'probabilities' => ['sales' => 0.85, 'support' => 0.15],
            'confidence' => 0.82,
        ]);

        self::assertSame('sales', $answer->choice);
        self::assertSame('sales', $answer->value());
        self::assertSame(0.82, $answer->confidence);
        self::assertSame(['sales' => 0.85, 'support' => 0.15], $answer->probabilities);
        self::assertNull($answer->score);
        self::assertNull($answer->noul);
    }

    #[Test]
    public function aScoreMayFallBetweenTwoLevelsAndKeepsTheLegend(): void
    {
        $answer = Answer::fromResponse('severity', QuestionType::Score, [
            'score' => 1.5,
            'legend' => ['cosmetic', 'awkward', 'blocking'],
            'probabilities' => [0.1, 0.6, 0.3],
            'confidence' => 0.75,
        ]);

        self::assertSame(1.5, $answer->score);
        self::assertSame(1.5, $answer->value());
        self::assertSame(['cosmetic', 'awkward', 'blocking'], $answer->legend);
        self::assertSame([0.1, 0.6, 0.3], $answer->probabilities);
    }

    #[Test]
    public function aNoulDerivesConfidenceFromHowFarItSitsFromEven(): void
    {
        // The API returns only the probability. Confidence is |p - 0.5| * 2, so an even coin
        // toss is 0 and a certain answer either way is 1 - the same scale the other types use.
        self::assertSame(0.0, Answer::fromResponse('q', QuestionType::Noul, ['noul' => 0.5])->confidence);
        self::assertSame(1.0, Answer::fromResponse('q', QuestionType::Noul, ['noul' => 1.0])->confidence);
        self::assertSame(1.0, Answer::fromResponse('q', QuestionType::Noul, ['noul' => 0.0])->confidence);
        self::assertEqualsWithDelta(0.66, Answer::fromResponse('q', QuestionType::Noul, ['noul' => 0.17])->confidence, 0.001);
    }

    #[Test]
    public function aNoulThatDoesArriveWithConfidenceKeepsIt(): void
    {
        $answer = Answer::fromResponse('q', QuestionType::Noul, ['noul' => 0.9, 'confidence' => 0.3]);

        self::assertSame(0.3, $answer->confidence);
        self::assertSame(0.9, $answer->value());
    }

    #[Test]
    public function aMissingValueIsNeverConfidentEnough(): void
    {
        $answer = Answer::fromResponse('q', QuestionType::Choice, ['confidence' => 0.99]);

        self::assertNull($answer->value());
        self::assertFalse($answer->isConfidentEnough(0.0));
    }

    #[Test]
    public function confidenceIsComparedInclusivelyAgainstTheThreshold(): void
    {
        $answer = Answer::fromResponse('q', QuestionType::Choice, ['choice' => 'a', 'confidence' => 0.6]);

        self::assertTrue($answer->isConfidentEnough(0.6));
        self::assertFalse($answer->isConfidentEnough(0.61));
    }

    #[Test]
    public function toArrayDropsWhatIsEmptySoALogRowStaysSmall(): void
    {
        $array = Answer::fromResponse('q', QuestionType::Noul, ['noul' => 0.9])->toArray();

        self::assertSame(['name' => 'q', 'type' => 'noul', 'value' => 0.9, 'confidence' => 0.8], $array);
        self::assertArrayNotHasKey('probabilities', $array);
        self::assertArrayNotHasKey('legend', $array);
    }
}
