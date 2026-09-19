<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Reads decisions, their questions and their criteria.
 *
 * Translations are ordinary TYPO3 record translations: the default-language record carries the
 * structure (identifiers, question names, types) and a translation overrides the wording — the
 * instructions and the criteria descriptions. A field left empty in the translation keeps the
 * default language's text, so a half-translated decision still asks a complete question.
 */
final readonly class DecisionRepository
{
    private const DECISIONS = 'tx_webconjev_decision';
    private const QUESTIONS = 'tx_webconjev_question';
    private const CRITERIA = 'tx_webconjev_criterion';

    /** Wording an editor may translate, per table. */
    private const TRANSLATABLE = [
        self::DECISIONS => ['title', 'description', 'state_template', 'default_outcome'],
        self::QUESTIONS => ['instructions'],
        self::CRITERIA => ['description', 'outcome_value'],
    ];

    public function __construct(private ConnectionPool $connectionPool) {}

    public function findByUid(int $uid, int $languageId = 0, bool $includeHidden = false): ?Decision
    {
        if ($uid <= 0) {
            return null;
        }

        $query = $this->query(self::DECISIONS, $includeHidden);
        $row = $query
            ->select('*')
            ->from(self::DECISIONS)
            ->andWhere($query->expr()->eq('uid', $query->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->hydrate($row, $languageId, $includeHidden) : null;
    }

    public function findByIdentifier(string $identifier, int $languageId = 0, bool $includeHidden = false): ?Decision
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $query = $this->query(self::DECISIONS, $includeHidden);
        $row = $query
            ->select('*')
            ->from(self::DECISIONS)
            ->andWhere(
                $query->expr()->eq('identifier', $query->createNamedParameter($identifier)),
                $query->expr()->in('sys_language_uid', [0, -1]),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->hydrate($row, $languageId, $includeHidden) : null;
    }

    /**
     * @return list<Decision>
     */
    public function findAll(int $languageId = 0, bool $includeHidden = false): array
    {
        $query = $this->query(self::DECISIONS, $includeHidden);
        $rows = $query
            ->select('*')
            ->from(self::DECISIONS)
            ->andWhere($query->expr()->in('sys_language_uid', [0, -1]))
            ->orderBy('sorting')
            ->addOrderBy('title')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            fn(array $row): Decision => $this->hydrate($row, $languageId, $includeHidden),
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row, int $languageId, bool $includeHidden): Decision
    {
        $row = $this->overlay(self::DECISIONS, $row, $languageId);
        $questions = [];

        foreach ($this->children(self::QUESTIONS, 'decision', Cast::int($row['uid']), $includeHidden) as $questionRow) {
            $questionRow = $this->overlay(self::QUESTIONS, $questionRow, $languageId);
            $criteria = array_map(
                fn(array $criterionRow): Criterion => Criterion::fromRow(
                    $this->overlay(self::CRITERIA, $criterionRow, $languageId),
                ),
                $this->children(self::CRITERIA, 'question', Cast::int($questionRow['uid']), $includeHidden),
            );
            $questions[] = DecisionQuestion::fromRow($questionRow, array_values($criteria));
        }

        return Decision::fromRow($row, $questions);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function children(string $table, string $parentField, int $parentUid, bool $includeHidden): array
    {
        $query = $this->query($table, $includeHidden);

        /** @var list<array<string, mixed>> $rows */
        $rows = $query
            ->select('*')
            ->from($table)
            ->andWhere(
                $query->expr()->eq($parentField, $query->createNamedParameter($parentUid, Connection::PARAM_INT)),
                $query->expr()->in('sys_language_uid', [0, -1]),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    /**
     * Merge the translation's wording over the default-language row.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function overlay(string $table, array $row, int $languageId): array
    {
        if ($languageId <= 0) {
            return $row;
        }

        $query = $this->query($table, true);
        $translation = $query
            ->select(...self::TRANSLATABLE[$table])
            ->from($table)
            ->andWhere(
                $query->expr()->eq('l10n_parent', $query->createNamedParameter(Cast::int($row['uid']), Connection::PARAM_INT)),
                $query->expr()->eq('sys_language_uid', $query->createNamedParameter($languageId, Connection::PARAM_INT)),
                $query->expr()->eq('hidden', $query->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($translation)) {
            return $row;
        }

        foreach (self::TRANSLATABLE[$table] as $field) {
            $value = $translation[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $row[$field] = $value;
            }
        }
        $row['sys_language_uid'] = $languageId;

        return $row;
    }

    private function query(string $table, bool $includeHidden): QueryBuilder
    {
        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();
        $query->where($query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)));

        if (!$includeHidden) {
            $query->andWhere($query->expr()->eq('hidden', $query->createNamedParameter(0, Connection::PARAM_INT)));
        }

        return $query;
    }
}
