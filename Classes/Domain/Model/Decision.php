<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Domain\Model;

use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Support\Cast;

/**
 * A named set of questions about one kind of state, with what to do when the answer is unclear.
 */
final readonly class Decision
{
    /** Use the extension-wide cache lifetime rather than one of this decision's own. */
    public const int CACHE_LIFETIME_INHERIT = -1;

    /** What a new decision asks for before anybody has changed it. */
    public const float DEFAULT_CONFIDENCE_THRESHOLD = 0.6;

    /**
     * @param list<DecisionQuestion> $questions
     */
    public function __construct(
        public int $uid,
        public string $identifier,
        public string $title,
        public string $description,
        public string $stateTemplate,
        public string $model,
        public float $confidenceThreshold,
        public int $cacheLifetime,
        public string $defaultOutcome,
        public array $questions = [],
        public int $languageId = 0,
        public bool $hidden = false,
    ) {}

    /**
     * @param array<string, mixed>   $row
     * @param list<DecisionQuestion> $questions
     */
    public static function fromRow(array $row, array $questions): self
    {
        return new self(
            uid: Cast::int($row['uid'] ?? null),
            identifier: Cast::trimmed($row['identifier'] ?? null),
            title: Cast::trimmed($row['title'] ?? null),
            description: Cast::trimmed($row['description'] ?? null),
            stateTemplate: Cast::string($row['state_template'] ?? null),
            model: Cast::trimmed($row['model'] ?? null),
            confidenceThreshold: Cast::float($row['confidence_threshold'] ?? null, self::DEFAULT_CONFIDENCE_THRESHOLD),
            cacheLifetime: Cast::int($row['cache_lifetime'] ?? null, self::CACHE_LIFETIME_INHERIT),
            defaultOutcome: Cast::trimmed($row['default_outcome'] ?? null),
            questions: $questions,
            languageId: Cast::int($row['sys_language_uid'] ?? null),
            hidden: Cast::bool($row['hidden'] ?? null),
        );
    }

    /**
     * The same decision with a different cache lifetime — how the playground asks without the cache.
     */
    public function withCacheLifetime(int $cacheLifetime): self
    {
        return new self(
            uid: $this->uid,
            identifier: $this->identifier,
            title: $this->title,
            description: $this->description,
            stateTemplate: $this->stateTemplate,
            model: $this->model,
            confidenceThreshold: $this->confidenceThreshold,
            cacheLifetime: $cacheLifetime,
            defaultOutcome: $this->defaultOutcome,
            questions: $this->questions,
            languageId: $this->languageId,
            hidden: $this->hidden,
        );
    }

    /**
     * @return array<string, Question> Keyed by question name, ready for the client
     *
     * @throws \Webconsulting\WebconJev\Exception\InvalidQuestionException when a question cannot be asked as it stands
     */
    public function toClientQuestions(): array
    {
        $questions = [];
        foreach ($this->questions as $question) {
            if ($question->name === '') {
                continue;
            }
            $questions[$question->name] = $question->toClientQuestion();
        }

        return $questions;
    }

    public function question(string $name): ?DecisionQuestion
    {
        return array_find($this->questions, static fn(DecisionQuestion $question): bool => $question->name === $name);
    }

    /**
     * @return array{uid: int, identifier: string, title: string, description: string, stateTemplate: string, model: string, confidenceThreshold: float, cacheLifetime: int, defaultOutcome: string, languageId: int, hidden: bool, questions: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'identifier' => $this->identifier,
            'title' => $this->title,
            'description' => $this->description,
            'stateTemplate' => $this->stateTemplate,
            'model' => $this->model,
            'confidenceThreshold' => $this->confidenceThreshold,
            'cacheLifetime' => $this->cacheLifetime,
            'defaultOutcome' => $this->defaultOutcome,
            'languageId' => $this->languageId,
            'hidden' => $this->hidden,
            'questions' => array_map(static fn(DecisionQuestion $q): array => $q->toArray(), $this->questions),
        ];
    }
}
