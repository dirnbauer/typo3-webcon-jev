<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Editing;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Which powermail forms route through a decision, and which condition rules read it.
 *
 * The module shows this next to each decision and in the delete confirmation, because a decision is
 * only as harmless to change as the forms that use it — and deleting one quietly turns every rule
 * reading it off.
 */
final readonly class DecisionUsage
{
    private const string FORMS = 'tx_powermail_domain_model_form';
    private const string FORM_FIELD = 'tx_webconjev_routing_decision';
    private const string RULES = 'tx_powermailcond_domain_model_rule';
    private const string RULE_FIELD = 'tx_webconjev_decision';

    public function __construct(
        private ConnectionPool $connectionPool,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @param list<int> $decisionUids
     *
     * @return array<int, array{forms: int, rules: int}> Keyed by decision uid; every uid asked for is present
     */
    public function countFor(array $decisionUids): array
    {
        if ($decisionUids === []) {
            return [];
        }

        $forms = $this->count(self::FORMS, self::FORM_FIELD, $decisionUids);
        $rules = $this->count(self::RULES, self::RULE_FIELD, $decisionUids);

        $usage = [];
        foreach ($decisionUids as $uid) {
            $usage[$uid] = ['forms' => $forms[$uid] ?? 0, 'rules' => $rules[$uid] ?? 0];
        }

        return $usage;
    }

    /**
     * Rows pointing at each decision. Nothing when the integration is not installed.
     *
     * @param list<int> $decisionUids
     *
     * @return array<int, int>
     */
    private function count(string $table, string $field, array $decisionUids): array
    {
        if (!$this->tcaSchemaFactory->has($table) || !$this->tcaSchemaFactory->get($table)->hasField($field)) {
            return [];
        }

        $query = $this->connectionPool->getQueryBuilderForTable($table);
        // Hidden forms and rules still point at the decision; deleted ones do not.
        $query->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $rows = $query
            ->select($field)
            ->addSelectLiteral('COUNT(*) AS ' . $query->quoteIdentifier('usage_count'))
            ->from($table)
            ->where(
                $query->expr()->in($field, $query->createNamedParameter($decisionUids, Connection::PARAM_INT_ARRAY)),
            )
            ->groupBy($field)
            ->executeQuery()
            ->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[Cast::int($row[$field] ?? null)] = Cast::int($row['usage_count'] ?? null);
        }

        return $counts;
    }
}
