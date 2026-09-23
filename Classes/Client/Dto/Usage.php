<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client\Dto;

use Webconsulting\WebconJev\Support\Cast;

/**
 * What a request cost. Jev bills input tokens only — output is free.
 */
final readonly class Usage
{
    private const float USD_PER_INPUT_TOKEN = 0.042 / 1_000_000;

    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromResponse(array $raw): self
    {
        return new self(
            inputTokens: Cast::int($raw['input_tokens'] ?? null),
            outputTokens: Cast::int($raw['output_tokens'] ?? null),
        );
    }

    public function costInUsd(): float
    {
        return $this->inputTokens * self::USD_PER_INPUT_TOKEN;
    }
}
