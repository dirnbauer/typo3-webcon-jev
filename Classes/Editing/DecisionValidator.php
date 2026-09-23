<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Editing\Dto\CriterionDraft;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Editing\Dto\QuestionDraft;
use Webconsulting\WebconJev\Editing\Dto\ValidationError;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Everything a decision has to be before it is saved or tried.
 *
 * The rules are the ones the API and the integrations rely on, stated where an editor can still
 * act on them: a choice with one option is refused by Jev, two questions of one name answer under
 * the same key, and two options of one id collapse into one in the request. Caught here, each is
 * a message next to the field; caught at runtime, each was a fallback in the run log.
 */
final readonly class DecisionValidator
{
    private const string TABLE = 'tx_webconjev_decision';

    /** Letters, digits, underscore and dash — what TCA's alphanum_x lets through. */
    private const string NAME_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /** What the slug field keeps of an identifier: it lower-cases everything else away. */
    private const string IDENTIFIER_PATTERN = '/^[a-z0-9_-]+$/';

    private const string MODEL_PATTERN = '/^[A-Za-z0-9._:-]+$/';

    /** The widest value the int(11) cache lifetime column holds. */
    private const int MAX_CACHE_LIFETIME = 2147483647;

    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @return list<ValidationError> Empty when the draft may be saved and asked
     */
    public function validate(DecisionDraft $draft): array
    {
        return [
            ...$this->validateDecision($draft),
            ...$this->validateQuestions($draft->questions),
        ];
    }

    /**
     * What a draft needs before the playground can ask it. A missing title or a taken identifier
     * does not stop anybody trying the wording; a question Jev would refuse does.
     *
     * @return list<ValidationError>
     */
    public function validateForTrying(DecisionDraft $draft): array
    {
        if ($draft->questions === []) {
            return [new ValidationError('questions', 'validation.questions.none')];
        }

        return [
            ...array_filter(
                $this->validateDecision($draft),
                static fn(ValidationError $error): bool => in_array($error->path, ['confidenceThreshold', 'model'], true),
            ),
            ...$this->validateQuestions($draft->questions),
        ];
    }

    /**
     * @return list<ValidationError>
     */
    private function validateDecision(DecisionDraft $draft): array
    {
        $errors = [];

        if ($draft->title === '') {
            $errors[] = new ValidationError('title', 'validation.required');
        } elseif (mb_strlen($draft->title) > 255) {
            $errors[] = new ValidationError('title', 'validation.tooLong', [255]);
        }

        if ($draft->identifier !== '') {
            if (preg_match(self::IDENTIFIER_PATTERN, $draft->identifier) !== 1) {
                $errors[] = new ValidationError('identifier', 'validation.identifier.format');
            } elseif (mb_strlen($draft->identifier) > 64) {
                $errors[] = new ValidationError('identifier', 'validation.tooLong', [64]);
            } elseif (($takenBy = $this->identifierTakenBy($draft->identifier, $draft->uid)) !== null) {
                $errors[] = new ValidationError('identifier', 'validation.identifier.taken', [$takenBy]);
            }
        }

        if (!is_numeric($draft->confidenceThreshold)
            || (float)$draft->confidenceThreshold < 0.0
            || (float)$draft->confidenceThreshold > 1.0
        ) {
            $errors[] = new ValidationError('confidenceThreshold', 'validation.threshold.range');
        }

        if (preg_match('/^-?\d+$/', $draft->cacheLifetime) !== 1
            || (int)$draft->cacheLifetime < -1
            || (int)$draft->cacheLifetime > self::MAX_CACHE_LIFETIME
        ) {
            $errors[] = new ValidationError('cacheLifetime', 'validation.cacheLifetime.range');
        }

        if ($draft->model !== '') {
            if (preg_match(self::MODEL_PATTERN, $draft->model) !== 1) {
                $errors[] = new ValidationError('model', 'validation.model.format');
            } elseif (mb_strlen($draft->model) > 64) {
                $errors[] = new ValidationError('model', 'validation.tooLong', [64]);
            }
        }

        if (mb_strlen($draft->defaultOutcome) > 255) {
            $errors[] = new ValidationError('defaultOutcome', 'validation.tooLong', [255]);
        }

        return $errors;
    }

    /**
     * @param list<QuestionDraft> $questions
     *
     * @return list<ValidationError>
     */
    private function validateQuestions(array $questions): array
    {
        $errors = [];
        $names = array_count_values(array_filter(
            array_map(static fn(QuestionDraft $question): string => $question->name, $questions),
            static fn(string $name): bool => $name !== '',
        ));

        foreach ($questions as $index => $question) {
            $path = 'questions.' . $index;

            if ($question->name === '') {
                $errors[] = new ValidationError($path . '.name', 'validation.required');
            } elseif (preg_match(self::NAME_PATTERN, $question->name) !== 1) {
                $errors[] = new ValidationError($path . '.name', 'validation.name.format');
            } elseif (mb_strlen($question->name) > 64) {
                $errors[] = new ValidationError($path . '.name', 'validation.tooLong', [64]);
            } elseif (($names[$question->name] ?? 0) > 1) {
                $errors[] = new ValidationError($path . '.name', 'validation.question.name.duplicate', [$question->name]);
            }

            $type = $question->questionType();
            if ($type === null) {
                $errors[] = new ValidationError($path . '.type', 'validation.question.type.invalid');
            }

            if ($question->instructions === '') {
                $errors[] = new ValidationError($path . '.instructions', 'validation.required');
            }

            array_push($errors, ...$this->validateCriteria($question->criteria, $type, $path));
        }

        return $errors;
    }

    /**
     * @param list<CriterionDraft> $criteria
     *
     * @return list<ValidationError>
     */
    private function validateCriteria(array $criteria, ?QuestionType $type, string $questionPath): array
    {
        $errors = [];

        if ($type === QuestionType::Choice && count($criteria) < 2) {
            $errors[] = new ValidationError($questionPath . '.criteria', 'validation.criteria.choiceMinimum');
        }
        if ($type === QuestionType::Score && count($criteria) < 2) {
            $errors[] = new ValidationError($questionPath . '.criteria', 'validation.criteria.scoreMinimum');
        }

        // A score is described by position; its ids are neither sent nor shown.
        $idsMatter = $type !== null && !$type->criteriaAreOrdered();
        $ids = array_count_values(array_filter(
            array_map(static fn(CriterionDraft $criterion): string => $criterion->identifier, $criteria),
            static fn(string $id): bool => $id !== '',
        ));

        foreach ($criteria as $index => $criterion) {
            $path = $questionPath . '.criteria.' . $index;

            if ($idsMatter) {
                if ($criterion->identifier === '') {
                    $errors[] = new ValidationError($path . '.identifier', 'validation.required');
                } elseif (preg_match(self::NAME_PATTERN, $criterion->identifier) !== 1) {
                    $errors[] = new ValidationError($path . '.identifier', 'validation.name.format');
                } elseif (mb_strlen($criterion->identifier) > 64) {
                    $errors[] = new ValidationError($path . '.identifier', 'validation.tooLong', [64]);
                } elseif (($ids[$criterion->identifier] ?? 0) > 1) {
                    $errors[] = new ValidationError(
                        $path . '.identifier',
                        'validation.criterion.identifier.duplicate',
                        [$criterion->identifier],
                    );
                }
            }

            if ($criterion->description === '') {
                $errors[] = new ValidationError($path . '.description', 'validation.required');
            }

            if (mb_strlen($criterion->outcomeValue) > 255) {
                $errors[] = new ValidationError($path . '.outcomeValue', 'validation.tooLong', [255]);
            }
        }

        return $errors;
    }

    /**
     * The title of another decision that already answers to this identifier, if one does.
     */
    private function identifierTakenBy(string $identifier, int $ownUid): ?string
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query->getRestrictions()->removeAll();

        $row = $query
            ->select('uid', 'title')
            ->from(self::TABLE)
            ->where(
                $query->expr()->eq('identifier', $query->createNamedParameter($identifier)),
                $query->expr()->neq('uid', $query->createNamedParameter($ownUid, Connection::PARAM_INT)),
                $query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)),
                $query->expr()->in('sys_language_uid', [0, -1]),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        $title = Cast::trimmed($row['title'] ?? null);

        return $title !== '' ? $title : '#' . Cast::int($row['uid'] ?? null);
    }
}
