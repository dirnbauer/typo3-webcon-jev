<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Client\Dto;

use Webconsulting\WebconJev\Support\Cast;

/**
 * One typed answer, with the probability distribution it was read off.
 */
final readonly class Answer
{
    /**
     * @param array<string, float>|list<float> $probabilities
     * @param list<string>                     $legend Level descriptions, for a score
     */
    private function __construct(
        public string $name,
        public QuestionType $type,
        public ?string $choice,
        public ?float $score,
        public ?float $noul,
        public float $confidence,
        public array $probabilities = [],
        public array $legend = [],
    ) {}

    /**
     * @param array<string, mixed> $raw The answer object as the API returned it
     */
    public static function fromResponse(string $name, QuestionType $type, array $raw): self
    {
        $probabilities = Cast::distribution($raw['probabilities'] ?? null);
        $legend = Cast::stringList($raw['legend'] ?? null);

        return match ($type) {
            QuestionType::Choice => new self(
                name: $name,
                type: $type,
                choice: isset($raw['choice']) ? Cast::string($raw['choice']) : null,
                score: null,
                noul: null,
                confidence: Cast::float($raw['confidence'] ?? null),
                probabilities: $probabilities,
                legend: $legend,
            ),
            QuestionType::Score => new self(
                name: $name,
                type: $type,
                choice: null,
                score: isset($raw['score']) ? Cast::float($raw['score']) : null,
                noul: null,
                confidence: Cast::float($raw['confidence'] ?? null),
                probabilities: $probabilities,
                legend: $legend,
            ),
            QuestionType::Noul => self::noul($name, isset($raw['noul']) ? Cast::float($raw['noul']) : null, $raw),
        };
    }

    /**
     * A noul answer carries no confidence of its own — the probability is the answer. How far it
     * sits from an even 0.5 is the same statement confidence makes for the other two types, so it
     * is exposed under the same name and a caller can gate all three the same way.
     *
     * @param array<string, mixed> $raw
     */
    private static function noul(string $name, ?float $noul, array $raw): self
    {
        $confidence = isset($raw['confidence'])
            ? Cast::float($raw['confidence'])
            : ($noul === null ? 0.0 : abs($noul - 0.5) * 2.0);

        return new self(
            name: $name,
            type: QuestionType::Noul,
            choice: null,
            score: null,
            noul: $noul,
            confidence: $confidence,
        );
    }

    /**
     * The answer itself: the chosen option id, the position on the rubric, or the probability.
     */
    public function value(): string|float|null
    {
        return match ($this->type) {
            QuestionType::Choice => $this->choice,
            QuestionType::Score => $this->score,
            QuestionType::Noul => $this->noul,
        };
    }

    /**
     * Whether this answer is certain enough to act on without asking a human.
     */
    public function isConfidentEnough(float $threshold): bool
    {
        return $this->value() !== null && $this->confidence >= $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'value' => $this->value(),
            'confidence' => $this->confidence,
            'probabilities' => $this->probabilities,
            'legend' => $this->legend,
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }
}
