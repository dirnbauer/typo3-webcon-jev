<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Netresearch\NrVault\Configuration\ExtensionConfigurationInterface as VaultConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Which backend user the vault writes are attributed to.
 *
 * nr-vault's own `provisioningBeUserUid` setting is asked first, because an installation that has
 * deliberately named an identity should get that one.
 *
 * Failing that, the user this extension's setup command creates is looked up by name. That
 * fallback is not a convenience: extension configuration lives in config/system/settings.php,
 * which on a container deployment is part of the image rather than a persistent volume. Measured
 * on typo3-lab: the setting was written, the next deploy replaced the file, and the uid was back
 * to 0 — so a rotation months later would have failed with nothing obviously changed. The
 * backend user is a database row and survives.
 *
 * The configured uid is only trusted while it names a user nr-vault will accept. The database
 * can move without the configuration: typo3-lab's was replaced by a copy of the development
 * database, where the provisioner has another uid, and the image kept pointing at a uid that no
 * longer existed — every provisioned command then died in nr-vault with "does not resolve to a
 * non-deleted be_users record". A configured uid that is gone, deleted, disabled or not at root
 * level now falls through to the lookup by name.
 */
final readonly class ProvisionerResolver
{
    /** Matches the user {@see \Webconsulting\WebconJev\Command\SetupProvisionerCommand} creates. */
    public const string USERNAME = 'vault_provisioner';

    public function __construct(
        private VaultConfiguration $vaultConfiguration,
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return int 0 when no usable identity exists
     */
    public function resolve(): int
    {
        $configured = $this->vaultConfiguration->getProvisioningBeUserUid();
        if ($configured > 0 && $this->isUsable($configured)) {
            return $configured;
        }

        return $this->byUsername();
    }

    /**
     * Where the uid came from, so a command can say so rather than leaving it a mystery.
     */
    public function describe(): string
    {
        $configured = $this->vaultConfiguration->getProvisioningBeUserUid();
        if ($configured > 0 && $this->isUsable($configured)) {
            return sprintf('backend user %d (nr-vault configuration)', $configured);
        }

        $found = $this->byUsername();
        $ignored = $configured > 0
            ? sprintf('; the configured backend user %d is gone or disabled', $configured)
            : '';

        return $found > 0
            ? sprintf('backend user %d ("%s", found by name%s)', $found, self::USERNAME, $ignored)
            : sprintf('none — run "webcon-jev:vault:setup-provisioner"%s', $ignored);
    }

    private function isUsable(int $uid): bool
    {
        $query = $this->usableUsers();

        return $query
            ->andWhere($query->expr()->eq('uid', $query->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne() !== false;
    }

    private function byUsername(): int
    {
        $query = $this->usableUsers();

        return Cast::int($query
            ->andWhere($query->expr()->eq('username', $query->createNamedParameter(self::USERNAME)))
            ->executeQuery()
            ->fetchOne());
    }

    /**
     * Backend users nr-vault accepts as a technical actor: present, not deleted, enabled and at
     * root level. A row failing any of those is no better than no row — nr-vault throws on it.
     */
    private function usableUsers(): QueryBuilder
    {
        $query = $this->connectionPool->getQueryBuilderForTable('be_users');
        $query->getRestrictions()->removeAll();

        return $query
            ->select('uid')
            ->from('be_users')
            ->where(
                $query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)),
                $query->expr()->eq('disable', $query->createNamedParameter(0, Connection::PARAM_INT)),
                $query->expr()->eq('pid', $query->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->setMaxResults(1);
    }
}
