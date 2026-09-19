<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client\Dto;

/**
 * The three primitives Jev answers.
 */
enum QuestionType: string
{
    /** Pick one of a known, unordered set of options. */
    case Choice = 'choice';

    /** Place something on an ordered rubric; the answer may fall between two levels. */
    case Score = 'score';

    /** How likely a yes/no statement is to be true, as a probability from 0 to 1. */
    case Noul = 'noul';

    /**
     * The key the answer for this type is carried under in the API response.
     */
    public function answerKey(): string
    {
        return $this->value;
    }

    /**
     * Choice and noul describe their criteria as a map, score as an ordered list.
     */
    public function criteriaAreOrdered(): bool
    {
        return $this === self::Score;
    }
}
