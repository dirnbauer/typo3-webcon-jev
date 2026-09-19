<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client\Dto;

use Webconsulting\WebconJev\Exception\InvalidQuestionException;

/**
 * One typed question put to Jev.
 */
final readonly class Question
{
    /**
     * @param array<string, string>|list<string> $criteria Options keyed by id for a choice, the meaning of
     *                                                     yes/no for a noul, an ordered list of levels for a score
     */
    public function __construct(
        public string $name,
        public QuestionType $type,
        public string $instructions,
        public array $criteria = [],
    ) {
        if ($this->name === '') {
            throw new InvalidQuestionException('A question needs a name to carry its answer back under.');
        }
        if ($this->instructions === '') {
            throw new InvalidQuestionException(sprintf('Question "%s" has no instructions.', $this->name));
        }
        if ($this->type === QuestionType::Choice && count($this->criteria) < 2) {
            throw new InvalidQuestionException(
                sprintf('Choice question "%s" needs at least two options to choose between.', $this->name),
            );
        }
        if ($this->type === QuestionType::Score && count($this->criteria) < 2) {
            throw new InvalidQuestionException(
                sprintf('Score question "%s" needs at least two levels.', $this->name),
            );
        }
    }

    /**
     * @return array{type: string, instructions: string, criteria?: array<string, string>|list<string>}
     */
    public function toPayload(): array
    {
        $payload = [
            'type' => $this->type->value,
            'instructions' => $this->instructions,
        ];

        if ($this->criteria !== []) {
            $payload['criteria'] = $this->type->criteriaAreOrdered()
                ? array_values($this->criteria)
                : $this->criteria;
        }

        return $payload;
    }
}
