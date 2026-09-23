<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing\Dto;

use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Support\Cast;

/**
 * One option or level as the module's editor sent it: trimmed, but not yet judged.
 */
final readonly class CriterionDraft
{
    public function __construct(
        public int $uid,
        public string $identifier,
        public string $description,
        public string $outcomeValue,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            uid: max(0, Cast::int($payload['uid'] ?? null)),
            identifier: Cast::trimmed($payload['identifier'] ?? null),
            description: Cast::trimmed($payload['description'] ?? null),
            outcomeValue: Cast::trimmed($payload['outcomeValue'] ?? null),
        );
    }

    public function toCriterion(): Criterion
    {
        return new Criterion($this->uid, $this->identifier, $this->description, $this->outcomeValue);
    }
}
