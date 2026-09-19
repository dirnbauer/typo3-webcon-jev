<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Netresearch\NrVault\Configuration\ExtensionConfigurationInterface as VaultConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
 */
final readonly class ProvisionerResolver
{
    /** Matches the user {@see \Webconsulting\WebconJev\Command\SetupProvisionerCommand} creates. */
    public const USERNAME = 'vault_provisioner';

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
        if ($configured > 0) {
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
        if ($configured > 0) {
            return sprintf('backend user %d (nr-vault configuration)', $configured);
        }

        $found = $this->byUsername();

        return $found > 0
            ? sprintf('backend user %d ("%s", found by name)', $found, self::USERNAME)
            : 'none — run "webcon-jev:vault:setup-provisioner"';
    }

    private function byUsername(): int
    {
        $query = $this->connectionPool->getQueryBuilderForTable('be_users');
        $query->getRestrictions()->removeAll();

        // nr-vault refuses an actor that is not root-level and enabled, so a row failing those is
        // no better than no row: report nothing rather than a uid that will throw later.
        $uid = $query
            ->select('uid')
            ->from('be_users')
            ->where(
                $query->expr()->eq('username', $query->createNamedParameter(self::USERNAME)),
                $query->expr()->eq('deleted', $query->createNamedParameter(0)),
                $query->expr()->eq('disable', $query->createNamedParameter(0)),
                $query->expr()->eq('pid', $query->createNamedParameter(0)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return Cast::int($uid);
    }
}
