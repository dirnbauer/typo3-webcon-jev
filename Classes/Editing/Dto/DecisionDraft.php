<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing\Dto;

use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Support\Cast;

/**
 * A decision as the module's editor sent it — for saving, or for trying before it is saved.
 *
 * The two numbers are kept as typed, so "0,7" or "high" reach the validator as what somebody
 * entered rather than as a zero nobody chose.
 */
final readonly class DecisionDraft
{
    /**
     * @param list<QuestionDraft> $questions
     */
    public function __construct(
        public int $uid,
        public string $title,
        public string $identifier = '',
        public string $description = '',
        public string $stateTemplate = '',
        public string $model = '',
        public string $confidenceThreshold = '0.6',
        public string $cacheLifetime = '-1',
        public string $defaultOutcome = '',
        public bool $hidden = false,
        public array $questions = [],
    ) {}

    /**
     * @param array<string, mixed> $payload The shape {@see Decision::toArray()} produces
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            uid: max(0, Cast::int($payload['uid'] ?? null)),
            title: Cast::trimmed($payload['title'] ?? null),
            identifier: Cast::trimmed($payload['identifier'] ?? null),
            description: Cast::trimmed($payload['description'] ?? null),
            stateTemplate: rtrim(Cast::string($payload['stateTemplate'] ?? null)),
            model: Cast::trimmed($payload['model'] ?? null),
            confidenceThreshold: str_replace(',', '.', Cast::trimmed(
                $payload['confidenceThreshold'] ?? null,
                (string)Decision::DEFAULT_CONFIDENCE_THRESHOLD,
            )),
            cacheLifetime: Cast::trimmed($payload['cacheLifetime'] ?? null, (string)Decision::CACHE_LIFETIME_INHERIT),
            defaultOutcome: Cast::trimmed($payload['defaultOutcome'] ?? null),
            hidden: Cast::bool($payload['hidden'] ?? null),
            questions: array_values(array_map(
                static fn(mixed $question): QuestionDraft => QuestionDraft::fromPayload(Cast::map($question)),
                array_filter(Cast::array($payload['questions'] ?? null), is_array(...)),
            )),
        );
    }

    public function confidenceThreshold(): float
    {
        return Cast::float($this->confidenceThreshold, Decision::DEFAULT_CONFIDENCE_THRESHOLD);
    }

    public function cacheLifetime(): int
    {
        return Cast::int($this->cacheLifetime, Decision::CACHE_LIFETIME_INHERIT);
    }

    /**
     * The decision this draft describes, as the runner would ask it.
     */
    public function toDecision(): Decision
    {
        return new Decision(
            uid: $this->uid,
            identifier: $this->identifier,
            title: $this->title,
            description: $this->description,
            stateTemplate: $this->stateTemplate,
            model: $this->model,
            confidenceThreshold: $this->confidenceThreshold(),
            cacheLifetime: $this->cacheLifetime(),
            defaultOutcome: $this->defaultOutcome,
            questions: array_map(static fn(QuestionDraft $question): DecisionQuestion => $question->toQuestion(), $this->questions),
            hidden: $this->hidden,
        );
    }
}
