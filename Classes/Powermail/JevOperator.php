<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\QuestionType;

/**
 * The rule operators this extension adds to powermail_cond.
 *
 * The values sit in the range powermail_cond reserves for other extensions
 * ({@see \In2code\PowermailCond\Domain\Model\Rule::OPERATOR_THIRD_PARTY_OFFSET}), so they never
 * collide with an operator the fork gains later.
 */
enum JevOperator: int
{
    case ChoiceIs = 100;
    case ChoiceIsNot = 101;
    case ScoreAtLeast = 102;
    case ScoreBelow = 103;
    case NoulAbove = 104;
    case NoulBelow = 105;

    /**
     * Which question type this operator can read.
     */
    public function expects(): QuestionType
    {
        return match ($this) {
            self::ChoiceIs, self::ChoiceIsNot => QuestionType::Choice,
            self::ScoreAtLeast, self::ScoreBelow => QuestionType::Score,
            self::NoulAbove, self::NoulBelow => QuestionType::Noul,
        };
    }

    /**
     * Whether the comparison value is a number rather than an option id.
     */
    public function comparesNumerically(): bool
    {
        return $this !== self::ChoiceIs && $this !== self::ChoiceIsNot;
    }

    /**
     * Decide the rule from an answer. $expected is the option id, or the threshold as a string.
     */
    public function matches(Answer $answer, string $expected): bool
    {
        if ($answer->type !== $this->expects()) {
            return false;
        }

        $value = $answer->value();
        if ($value === null) {
            return false;
        }

        return match ($this) {
            self::ChoiceIs => (string)$value === trim($expected),
            self::ChoiceIsNot => (string)$value !== trim($expected),
            self::ScoreAtLeast, self::NoulAbove => (float)$value >= (float)$expected,
            self::ScoreBelow, self::NoulBelow => (float)$value < (float)$expected,
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_map(static fn(self $case): int => $case->value, self::cases());
    }

    /**
     * @return list<int>
     */
    public static function choiceValues(): array
    {
        return [self::ChoiceIs->value, self::ChoiceIsNot->value];
    }

    /**
     * @return list<int>
     */
    public static function numericValues(): array
    {
        return array_values(array_diff(self::values(), self::choiceValues()));
    }
}
