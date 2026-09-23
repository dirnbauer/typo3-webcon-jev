<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing\Dto;

use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Support\Cast;

/**
 * A question as the module's editor sent it. The type stays a string until it has been validated,
 * so a request carrying an unknown one can be told so instead of silently becoming a choice.
 */
final readonly class QuestionDraft
{
    /**
     * @param list<CriterionDraft> $criteria
     */
    public function __construct(
        public int $uid,
        public string $name,
        public string $type,
        public string $instructions,
        public array $criteria = [],
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            uid: max(0, Cast::int($payload['uid'] ?? null)),
            name: Cast::trimmed($payload['name'] ?? null),
            type: Cast::trimmed($payload['type'] ?? null, QuestionType::Choice->value),
            instructions: Cast::trimmed($payload['instructions'] ?? null),
            criteria: array_values(array_map(
                static fn(mixed $criterion): CriterionDraft => CriterionDraft::fromPayload(Cast::map($criterion)),
                array_filter(Cast::array($payload['criteria'] ?? null), is_array(...)),
            )),
        );
    }

    public function questionType(): ?QuestionType
    {
        return QuestionType::tryFrom($this->type);
    }

    public function toQuestion(): DecisionQuestion
    {
        return new DecisionQuestion(
            uid: $this->uid,
            name: $this->name,
            type: $this->questionType() ?? QuestionType::Choice,
            instructions: $this->instructions,
            criteria: array_map(static fn(CriterionDraft $criterion): Criterion => $criterion->toCriterion(), $this->criteria),
        );
    }
}
