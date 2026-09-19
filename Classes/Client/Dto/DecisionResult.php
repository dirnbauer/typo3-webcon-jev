<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client\Dto;

/**
 * Everything one call to Jev came back with.
 */
final readonly class DecisionResult
{
    /**
     * @param array<string, Answer> $answers Keyed by question name
     */
    public function __construct(
        public array $answers,
        public string $model,
        public Usage $usage,
        public float $durationMs = 0.0,
        public bool $fromCache = false,
        public bool $isFallback = false,
        public ?string $fallbackReason = null,
    ) {}

    /**
     * A result that stands in for an answer Jev could not give — an outage, a rate limit, or no
     * token configured. Callers treat it as "use the default and carry on".
     */
    public static function fallback(string $reason, string $model = ''): self
    {
        return new self(
            answers: [],
            model: $model,
            usage: new Usage(),
            isFallback: true,
            fallbackReason: $reason,
        );
    }

    public function get(string $question): ?Answer
    {
        return $this->answers[$question] ?? null;
    }

    public function withCacheFlag(bool $fromCache): self
    {
        return new self(
            answers: $this->answers,
            model: $this->model,
            usage: $this->usage,
            durationMs: $this->durationMs,
            fromCache: $fromCache,
            isFallback: $this->isFallback,
            fallbackReason: $this->fallbackReason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'answers' => array_map(static fn(Answer $a): array => $a->toArray(), $this->answers),
            'usage' => [
                'inputTokens' => $this->usage->inputTokens,
                'outputTokens' => $this->usage->outputTokens,
                'costUsd' => $this->usage->costInUsd(),
            ],
            'durationMs' => $this->durationMs,
            'fromCache' => $this->fromCache,
            'isFallback' => $this->isFallback,
            'fallbackReason' => $this->fallbackReason,
        ];
    }
}
