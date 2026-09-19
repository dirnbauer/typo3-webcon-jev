<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Reads the Jev columns this extension adds to a powermail_cond rule.
 *
 * They are read straight from the row rather than through the Extbase model, because the model
 * belongs to powermail_cond and gains no properties from a foreign TCA override.
 */
final readonly class RuleConfigurationRepository
{
    private const TABLE = 'tx_powermailcond_domain_model_rule';

    public function __construct(private ConnectionPool $connectionPool) {}

    public function forRule(int $ruleUid): ?RuleConfiguration
    {
        if ($ruleUid <= 0) {
            return null;
        }

        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query->getRestrictions()->removeAll();

        $row = $query
            ->select('tx_webconjev_decision', 'tx_webconjev_question', 'tx_webconjev_expect', 'tx_webconjev_threshold')
            ->from(self::TABLE)
            ->where($query->expr()->eq('uid', $query->createNamedParameter($ruleUid, Connection::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return null;
        }

        return new RuleConfiguration(
            decisionUid: Cast::int($row['tx_webconjev_decision'] ?? null),
            questionName: Cast::trimmed($row['tx_webconjev_question'] ?? null),
            expected: Cast::trimmed($row['tx_webconjev_expect'] ?? null),
            threshold: Cast::float($row['tx_webconjev_threshold'] ?? null, 0.6),
        );
    }
}
