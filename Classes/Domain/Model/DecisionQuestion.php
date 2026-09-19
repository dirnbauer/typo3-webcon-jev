<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Domain\Model;

use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Support\Cast;

/**
 * A question as an editor defined it, ready to be turned into an API question.
 */
final readonly class DecisionQuestion
{
    /**
     * @param list<Criterion> $criteria
     */
    public function __construct(
        public int $uid,
        public string $name,
        public QuestionType $type,
        public string $instructions,
        public array $criteria = [],
    ) {}

    /**
     * @param array<string, mixed> $row
     * @param list<Criterion>      $criteria
     */
    public static function fromRow(array $row, array $criteria): self
    {
        return new self(
            uid: Cast::int($row['uid'] ?? null),
            name: Cast::trimmed($row['name'] ?? null),
            type: QuestionType::tryFrom(Cast::string($row['type'] ?? null)) ?? QuestionType::Choice,
            instructions: Cast::trimmed($row['instructions'] ?? null),
            criteria: $criteria,
        );
    }

    public function toClientQuestion(): Question
    {
        return new Question(
            name: $this->name,
            type: $this->type,
            instructions: $this->instructions,
            criteria: $this->criteriaPayload(),
        );
    }

    /**
     * Choice and noul describe their options by id, score by position.
     *
     * @return array<string, string>|list<string>
     */
    private function criteriaPayload(): array
    {
        if ($this->type->criteriaAreOrdered()) {
            return array_values(array_map(
                static fn(Criterion $c): string => $c->description,
                $this->criteria,
            ));
        }

        $payload = [];
        foreach ($this->criteria as $index => $criterion) {
            $key = $criterion->identifier !== '' ? $criterion->identifier : 'option_' . ($index + 1);
            $payload[$key] = $criterion->description;
        }

        return $payload;
    }

    /**
     * What the integration should do when this option wins — for the routing finisher, the
     * receiver address.
     */
    public function outcomeValueFor(string $identifier): ?string
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion->identifier === $identifier) {
                return $criterion->outcomeValue !== '' ? $criterion->outcomeValue : null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'name' => $this->name,
            'type' => $this->type->value,
            'instructions' => $this->instructions,
            'criteria' => array_map(static fn(Criterion $c): array => $c->toArray(), $this->criteria),
        ];
    }
}
