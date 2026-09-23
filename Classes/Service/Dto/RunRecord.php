<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service\Dto;

use Webconsulting\WebconJev\Support\Cast;

/**
 * One row of the run log, typed for display.
 */
final readonly class RunRecord
{
    /**
     * @param list<array{name: string, type: string, value: string, confidence: float}> $answers
     */
    public function __construct(
        public int $uid,
        public int $createdAt,
        public int $decision,
        public string $decisionIdentifier,
        public string $context,
        public string $origin,
        public string $model,
        public float $durationMs,
        public int $inputTokens,
        public float $costUsd,
        public RunOutcome $outcome,
        public string $fallbackReason,
        public array $answers,
    ) {}

    /**
     * @param array<string, mixed> $row A tx_webconjev_run row
     */
    public static function fromRow(array $row): self
    {
        $outcome = match (true) {
            Cast::bool($row['is_fallback'] ?? null) => RunOutcome::Fallback,
            Cast::bool($row['from_cache'] ?? null) => RunOutcome::Cached,
            default => RunOutcome::Answered,
        };

        return new self(
            uid: Cast::int($row['uid'] ?? null),
            createdAt: Cast::int($row['crdate'] ?? null),
            decision: Cast::int($row['decision'] ?? null),
            decisionIdentifier: Cast::string($row['decision_identifier'] ?? null),
            context: Cast::string($row['context'] ?? null),
            origin: Cast::string($row['origin'] ?? null),
            model: Cast::string($row['model'] ?? null),
            durationMs: Cast::float($row['duration_ms'] ?? null),
            inputTokens: Cast::int($row['input_tokens'] ?? null),
            costUsd: Cast::float($row['cost_usd'] ?? null),
            outcome: $outcome,
            fallbackReason: Cast::string($row['fallback_reason'] ?? null),
            answers: self::answers(Cast::string($row['answers'] ?? null, '[]')),
        );
    }

    /**
     * A run of a decision an integration built in code for the occasion (uid 0), rather than of one
     * stored in the decision table.
     */
    public function isAdHoc(): bool
    {
        return $this->decision === 0;
    }

    public function isFallback(): bool
    {
        return $this->outcome === RunOutcome::Fallback;
    }

    public function isCached(): bool
    {
        return $this->outcome === RunOutcome::Cached;
    }

    /**
     * The answers as stored, reduced to what a table row can show.
     *
     * @return list<array{name: string, type: string, value: string, confidence: float}>
     */
    private static function answers(string $json): array
    {
        $answers = [];
        foreach (Cast::map(json_decode($json, true)) as $name => $raw) {
            $answer = Cast::map($raw);
            $value = $answer['value'] ?? null;
            $answers[] = [
                'name' => $name,
                'type' => Cast::string($answer['type'] ?? null),
                'value' => is_float($value) ? number_format($value, 2) : Cast::string($value),
                'confidence' => Cast::float($answer['confidence'] ?? null),
            ];
        }

        return $answers;
    }
}
