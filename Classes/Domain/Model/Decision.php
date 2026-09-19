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
    public const CACHE_LIFETIME_INHERIT = -1;

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
            confidenceThreshold: Cast::float($row['confidence_threshold'] ?? null, 0.6),
            cacheLifetime: Cast::int($row['cache_lifetime'] ?? null, self::CACHE_LIFETIME_INHERIT),
            defaultOutcome: Cast::trimmed($row['default_outcome'] ?? null),
            questions: $questions,
            languageId: Cast::int($row['sys_language_uid'] ?? null),
        );
    }

    /**
     * @return array<string, Question> Keyed by question name, ready for the client
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
        foreach ($this->questions as $question) {
            if ($question->name === $name) {
                return $question;
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
            'identifier' => $this->identifier,
            'title' => $this->title,
            'description' => $this->description,
            'stateTemplate' => $this->stateTemplate,
            'model' => $this->model,
            'confidenceThreshold' => $this->confidenceThreshold,
            'cacheLifetime' => $this->cacheLifetime,
            'defaultOutcome' => $this->defaultOutcome,
            'languageId' => $this->languageId,
            'questions' => array_map(static fn(DecisionQuestion $q): array => $q->toArray(), $this->questions),
        ];
    }
}
