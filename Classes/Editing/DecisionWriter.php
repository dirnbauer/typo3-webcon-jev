<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Editing\Dto\QuestionDraft;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Writes a decision the module's editor sent, through the DataHandler.
 *
 * Going through the DataHandler rather than straight to the database gives an edit made here the
 * same history entry, permission check and hooks as one made in the record editor, and lets it be
 * rolled back from the record history like anything else.
 *
 * The DataHandler does not delete an inline child that is merely left out of its parent's list —
 * the record editor sends an explicit delete for every child an editor removes. So this does the
 * same: whatever the decision had and the draft no longer lists is deleted in the same run. A
 * question removed here and still answering at runtime was the defect that made this necessary.
 */
final readonly class DecisionWriter
{
    private const string DECISIONS = 'tx_webconjev_decision';
    private const string QUESTIONS = 'tx_webconjev_question';
    private const string CRITERIA = 'tx_webconjev_criterion';

    public function __construct(
        private ConnectionPool $connectionPool,
        private Settings $settings,
    ) {}

    /**
     * Save a draft the validator has accepted.
     *
     * @return array{uid: int, errors: list<string>} The saved decision's uid, or what the DataHandler refused
     */
    public function save(DecisionDraft $draft): array
    {
        $existing = $draft->uid > 0 ? $this->existingStructure($draft->uid) : null;
        if ($draft->uid > 0 && $existing === null) {
            return ['uid' => 0, 'errors' => [sprintf('Decision %d does not exist any more.', $draft->uid)]];
        }

        $decisionId = $existing !== null ? (string)$draft->uid : StringUtility::getUniqueId('NEW');
        $pid = $existing['pid'] ?? $this->settings->storagePid();

        $data = [self::DECISIONS => [], self::QUESTIONS => [], self::CRITERIA => []];
        $keptQuestions = [];
        $keptCriteria = [];
        $questionIds = [];

        foreach ($draft->questions as $question) {
            // A uid is only trusted if it already belongs to this decision; anything else is new.
            $ownsQuestion = $existing !== null && isset($existing['questions'][$question->uid]);
            $questionId = $ownsQuestion ? (string)$question->uid : StringUtility::getUniqueId('NEW');
            $questionIds[] = $questionId;
            if ($ownsQuestion) {
                $keptQuestions[] = $question->uid;
            }

            $criterionIds = [];
            foreach ($question->criteria as $criterion) {
                $ownsCriterion = $ownsQuestion
                    && in_array($criterion->uid, $existing['questions'][$question->uid], true);
                $criterionId = $ownsCriterion ? (string)$criterion->uid : StringUtility::getUniqueId('NEW');
                $criterionIds[] = $criterionId;
                if ($ownsCriterion) {
                    $keptCriteria[] = $criterion->uid;
                }

                $data[self::CRITERIA][$criterionId] = [
                    'identifier' => $criterion->identifier,
                    'description' => $criterion->description,
                    'outcome_value' => $criterion->outcomeValue,
                ] + ($ownsCriterion ? [] : ['pid' => $pid]);
            }

            $data[self::QUESTIONS][$questionId] = [
                'name' => $question->name,
                'type' => $this->type($question),
                'instructions' => $question->instructions,
                'criteria' => implode(',', $criterionIds),
            ] + ($ownsQuestion ? [] : ['pid' => $pid]);
        }

        $data[self::DECISIONS][$decisionId] = [
            'title' => $draft->title,
            'identifier' => $draft->identifier,
            'description' => $draft->description,
            'state_template' => $draft->stateTemplate,
            'model' => $draft->model,
            'confidence_threshold' => $draft->confidenceThreshold(),
            'cache_lifetime' => $draft->cacheLifetime(),
            'default_outcome' => $draft->defaultOutcome,
            'hidden' => $draft->hidden ? 1 : 0,
            'questions' => implode(',', $questionIds),
        ] + ($existing !== null ? [] : ['pid' => $pid]);

        $commands = $this->deletionsFor($existing, $keptQuestions, $keptCriteria);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(array_filter($data), $commands);
        $dataHandler->process_datamap();
        if ($commands !== []) {
            $dataHandler->process_cmdmap();
        }

        $errors = array_values(array_map(Cast::string(...), $dataHandler->errorLog));
        $uid = Cast::int($dataHandler->substNEWwithIDs[$decisionId] ?? null, $draft->uid);

        return ['uid' => $errors === [] ? $uid : 0, 'errors' => $errors];
    }

    /**
     * @return list<string> What the DataHandler refused; empty when the decision is gone
     */
    public function delete(int $uid): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [self::DECISIONS => [$uid => ['delete' => 1]]]);
        $dataHandler->process_cmdmap();

        return array_values(array_map(Cast::string(...), $dataHandler->errorLog));
    }

    private function type(QuestionDraft $question): string
    {
        return ($question->questionType() ?? QuestionType::Choice)->value;
    }

    /**
     * The decision's pid and the default-language questions and criteria it has now.
     *
     * @return array{pid: int, questions: array<int, list<int>>}|null Criteria uids keyed by question uid
     */
    private function existingStructure(int $uid): ?array
    {
        $decision = $this->rows(self::DECISIONS, 'uid', [$uid])[0] ?? null;
        if ($decision === null) {
            return null;
        }

        $questions = [];
        foreach ($this->rows(self::QUESTIONS, 'decision', [$uid]) as $question) {
            $questions[Cast::int($question['uid'] ?? null)] = [];
        }
        if ($questions !== []) {
            foreach ($this->rows(self::CRITERIA, 'question', array_keys($questions)) as $criterion) {
                $questions[Cast::int($criterion['question'] ?? null)][] = Cast::int($criterion['uid'] ?? null);
            }
        }

        return ['pid' => Cast::int($decision['pid'] ?? null), 'questions' => $questions];
    }

    /**
     * Delete commands for what the decision had and the draft no longer lists. Deleting a question
     * takes its criteria and translations with it, so those are not listed separately.
     *
     * @param array{pid: int, questions: array<int, list<int>>}|null $existing
     * @param list<int>                                              $keptQuestions
     * @param list<int>                                              $keptCriteria
     *
     * @return array<string, array<int, array{delete: int}>>
     */
    private function deletionsFor(?array $existing, array $keptQuestions, array $keptCriteria): array
    {
        if ($existing === null) {
            return [];
        }

        $commands = [];
        foreach ($existing['questions'] as $questionUid => $criterionUids) {
            if (!in_array($questionUid, $keptQuestions, true)) {
                $commands[self::QUESTIONS][$questionUid] = ['delete' => 1];
                continue;
            }
            foreach (array_diff($criterionUids, $keptCriteria) as $criterionUid) {
                $commands[self::CRITERIA][$criterionUid] = ['delete' => 1];
            }
        }

        return $commands;
    }

    /**
     * Live, default-language rows that are not deleted. Hidden ones count: the editor shows them.
     *
     * @param list<int> $values
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, string $field, array $values): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();

        /** @var list<array<string, mixed>> $rows */
        $rows = $query
            ->select('uid', 'pid', ...($table === self::CRITERIA ? ['question'] : []))
            ->from($table)
            ->where(
                $query->expr()->in($field, $query->createNamedParameter($values, Connection::PARAM_INT_ARRAY)),
                $query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)),
                $query->expr()->in('sys_language_uid', [0, -1]),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }
}
