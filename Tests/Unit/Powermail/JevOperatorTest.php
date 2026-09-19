<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Powermail;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Powermail\JevOperator;

/**
 * Six operators, three answer types. What each one compares, and the refusal to compare an answer
 * of the wrong type, is what a powermail_cond rule's behaviour rests on.
 */
final class JevOperatorTest extends TestCase
{
    /**
     * @return iterable<string, array{JevOperator, Answer, string, bool}>
     */
    public static function comparisons(): iterable
    {
        $sales = Answer::fromResponse('d', QuestionType::Choice, ['choice' => 'sales', 'confidence' => 0.9]);
        yield 'choice is, matching' => [JevOperator::ChoiceIs, $sales, 'sales', true];
        yield 'choice is, trimmed expectation' => [JevOperator::ChoiceIs, $sales, ' sales ', true];
        yield 'choice is, other option' => [JevOperator::ChoiceIs, $sales, 'support', false];
        yield 'choice is not, other option' => [JevOperator::ChoiceIsNot, $sales, 'support', true];
        yield 'choice is not, same option' => [JevOperator::ChoiceIsNot, $sales, 'sales', false];

        $score = Answer::fromResponse('s', QuestionType::Score, ['score' => 2.0, 'confidence' => 0.9]);
        yield 'score at least, equal' => [JevOperator::ScoreAtLeast, $score, '2', true];
        yield 'score at least, above' => [JevOperator::ScoreAtLeast, $score, '1.5', true];
        yield 'score at least, below' => [JevOperator::ScoreAtLeast, $score, '2.5', false];
        yield 'score below, strictly' => [JevOperator::ScoreBelow, $score, '2', false];
        yield 'score below, yes' => [JevOperator::ScoreBelow, $score, '2.5', true];

        $noul = Answer::fromResponse('n', QuestionType::Noul, ['noul' => 0.6]);
        yield 'noul above, equal' => [JevOperator::NoulAbove, $noul, '0.6', true];
        yield 'noul above, no' => [JevOperator::NoulAbove, $noul, '0.7', false];
        yield 'noul below, yes' => [JevOperator::NoulBelow, $noul, '0.7', true];
        yield 'noul below, equal is not below' => [JevOperator::NoulBelow, $noul, '0.6', false];
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function anOperatorComparesItsOwnKindOfAnswer(JevOperator $operator, Answer $answer, string $expected, bool $matches): void
    {
        self::assertSame($matches, $operator->matches($answer, $expected));
    }

    #[Test]
    public function anAnswerOfTheWrongTypeNeverMatches(): void
    {
        // A score rule reading a choice, or a noul rule reading a score, is a misconfigured rule;
        // it must not apply by accident because "sales" happens to compare against "0.6".
        $choice = Answer::fromResponse('d', QuestionType::Choice, ['choice' => 'sales', 'confidence' => 1.0]);
        $score = Answer::fromResponse('s', QuestionType::Score, ['score' => 1.0, 'confidence' => 1.0]);

        self::assertFalse(JevOperator::ScoreAtLeast->matches($choice, '0'));
        self::assertFalse(JevOperator::NoulAbove->matches($score, '0'));
        self::assertFalse(JevOperator::ChoiceIs->matches($score, '1'));
    }

    #[Test]
    public function aMissingValueNeverMatchesEvenNegatively(): void
    {
        $empty = Answer::fromResponse('d', QuestionType::Choice, ['confidence' => 1.0]);

        self::assertFalse(JevOperator::ChoiceIs->matches($empty, 'sales'));
        self::assertFalse(JevOperator::ChoiceIsNot->matches($empty, 'sales'));
    }

    #[Test]
    public function everyOperatorSitsInTheRangePowermailCondReservesForOthers(): void
    {
        foreach (JevOperator::cases() as $operator) {
            self::assertGreaterThanOrEqual(100, $operator->value, $operator->name);
        }
        self::assertCount(count(JevOperator::cases()), array_unique(JevOperator::values()));
    }

    #[Test]
    public function choiceOperatorsAreTheOnlyNonNumericOnes(): void
    {
        self::assertSame([100, 101], JevOperator::choiceValues());
        self::assertSame([102, 103, 104, 105], JevOperator::numericValues());
        self::assertFalse(JevOperator::ChoiceIs->comparesNumerically());
        self::assertTrue(JevOperator::NoulBelow->comparesNumerically());
    }
}
