<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service\Dto;

use Webconsulting\WebconJev\Support\Cast;

/**
 * Which runs the run log should show. Every constraint is optional; none means all of them.
 */
final readonly class RunLogFilter
{
    public function __construct(
        public int $decision = 0,
        public string $context = '',
        public ?RunOutcome $outcome = null,
    ) {}

    /**
     * @param array<string, mixed> $parameters The filter as the module's form submits it
     */
    public static function fromArray(array $parameters): self
    {
        $context = Cast::trimmed($parameters['context'] ?? null);

        return new self(
            decision: max(0, Cast::int($parameters['decision'] ?? null)),
            // Contexts are free text a developer may pass to the runner, so only the shape is checked.
            context: preg_match('/^[a-z0-9_]{1,32}$/', $context) === 1 ? $context : '',
            outcome: RunOutcome::tryFrom(Cast::trimmed($parameters['outcome'] ?? null)),
        );
    }

    public function isActive(): bool
    {
        return $this->decision > 0 || $this->context !== '' || $this->outcome !== null;
    }

    /**
     * @return array{decision?: int, context?: string, outcome?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'decision' => $this->decision,
            'context' => $this->context,
            'outcome' => $this->outcome?->value,
        ], static fn(int|string|null $value): bool => $value !== 0 && $value !== '' && $value !== null);
    }
}
