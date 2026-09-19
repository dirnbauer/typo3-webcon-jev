<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Domain\Model;

use Webconsulting\WebconJev\Support\Cast;

/**
 * One option of a choice, one level of a score, or the meaning of yes/no for a noul.
 */
final readonly class Criterion
{
    public function __construct(
        public int $uid,
        public string $identifier,
        public string $description,
        public string $outcomeValue = '',
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            uid: Cast::int($row['uid'] ?? null),
            identifier: Cast::trimmed($row['identifier'] ?? null),
            description: Cast::trimmed($row['description'] ?? null),
            outcomeValue: Cast::trimmed($row['outcome_value'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'identifier' => $this->identifier,
            'description' => $this->description,
            'outcomeValue' => $this->outcomeValue,
        ];
    }
}
