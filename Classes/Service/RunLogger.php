<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Records what every decision cost and what it decided.
 *
 * The log is the only place a fallback is visible after the fact — a form that quietly routed to
 * its default receiver for a week looks exactly like a form that worked.
 */
final readonly class RunLogger
{
    public const TABLE = 'tx_webconjev_run';

    public const CONTEXT_CONDITION = 'powermail_cond';
    public const CONTEXT_FINISHER = 'powermail_finisher';
    public const CONTEXT_PLAYGROUND = 'playground';
    public const CONTEXT_CLI = 'cli';

    public function __construct(
        private ConnectionPool $connectionPool,
        private Settings $settings,
    ) {}

    public function log(
        Decision $decision,
        DecisionResult $result,
        string $context,
        string $origin = '',
        string $stateHash = '',
    ): void {
        if (!$this->settings->logRuns()) {
            return;
        }

        $answers = array_map(static fn(Answer $answer): array => $answer->toArray(), $result->answers);
        $now = Cast::int($GLOBALS['EXEC_TIME'] ?? null, time());

        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'pid' => 0,
            'crdate' => $now,
            'tstamp' => $now,
            'decision' => $decision->uid,
            'decision_identifier' => $decision->identifier,
            'context' => $context,
            'origin' => mb_substr($origin, 0, 250),
            'model' => $result->model,
            'state_hash' => $stateHash,
            'question_count' => count($decision->questions),
            'duration_ms' => $result->durationMs,
            'input_tokens' => $result->usage->inputTokens,
            'output_tokens' => $result->usage->outputTokens,
            'cost_usd' => $result->usage->costInUsd(),
            'from_cache' => $result->fromCache ? 1 : 0,
            'is_fallback' => $result->isFallback ? 1 : 0,
            'fallback_reason' => mb_substr((string)$result->fallbackReason, 0, 250),
            'answers' => json_encode($answers, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50, int $decisionUid = 0): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query->select('*')->from(self::TABLE)->orderBy('crdate', 'DESC')->setMaxResults(max(1, $limit));

        if ($decisionUid > 0) {
            $query->where(
                $query->expr()->eq('decision', $query->createNamedParameter($decisionUid, Connection::PARAM_INT)),
            );
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();

        return $rows;
    }

    /**
     * Totals since a point in time, for the module's cost panel.
     *
     * @return array{runs: int, calls: int, fallbacks: int, inputTokens: int, costUsd: float, avgDurationMs: float}
     */
    public function totalsSince(int $timestamp): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $query
            ->selectLiteral(
                'COUNT(*) AS runs',
                'SUM(CASE WHEN from_cache = 0 AND is_fallback = 0 THEN 1 ELSE 0 END) AS calls',
                'SUM(is_fallback) AS fallbacks',
                'SUM(input_tokens) AS input_tokens',
                'SUM(cost_usd) AS cost_usd',
                'AVG(NULLIF(duration_ms, 0)) AS avg_duration',
            )
            ->from(self::TABLE)
            ->where($query->expr()->gte('crdate', $query->createNamedParameter($timestamp, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        $row = is_array($row) ? $row : [];

        return [
            'runs' => Cast::int($row['runs'] ?? null),
            'calls' => Cast::int($row['calls'] ?? null),
            'fallbacks' => Cast::int($row['fallbacks'] ?? null),
            'inputTokens' => Cast::int($row['input_tokens'] ?? null),
            'costUsd' => Cast::float($row['cost_usd'] ?? null),
            'avgDurationMs' => Cast::float($row['avg_duration'] ?? null),
        ];
    }

    /**
     * Delete rows created before the given time. Returns how many went.
     */
    public function pruneBefore(int $timestamp): int
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return (int)$query
            ->delete(self::TABLE)
            ->where($query->expr()->lt('crdate', $query->createNamedParameter($timestamp, Connection::PARAM_INT)))
            ->executeStatement();
    }
}
