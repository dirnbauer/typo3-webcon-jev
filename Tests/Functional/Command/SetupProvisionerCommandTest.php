<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Command\SetupProvisionerCommand;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * The identity a server writes secrets as: one non-admin user that cannot sign in, carrying two
 * permissions and nothing else. Idempotent, and it repairs a user somebody has since edited.
 *
 * The helpers below read rows with every restriction removed, on purpose. Connection::select()
 * applies TYPO3's default restrictions, and for be_users that includes "disable" — so a test
 * that disables the user and then asks a restricted helper whether it still exists gets "no",
 * which is what sent the command itself down the wrong path before this suite existed.
 */
final class SetupProvisionerCommandTest extends AbstractJevTestCase
{
    #[Test]
    public function itCreatesTheGroupTheUserAndTheConfiguration(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(0, $tester->execute([]), $tester->getDisplay());

        $group = $this->rawRow('be_groups', 'title', 'Vault provisioning');
        self::assertSame('tx_nrvault:secret.create,tx_nrvault:secret.rotate', $group['custom_options']);

        $user = $this->rawRow('be_users', 'username', 'vault_provisioner');
        self::assertSame(0, (int)$user['admin'], 'an admin would bypass the grant this exists to keep narrow');
        self::assertSame(0, (int)$user['disable']);
        self::assertSame(0, (int)$user['pid']);
        self::assertSame((string)$group['uid'], $user['usergroup']);
        self::assertSame('invalid-no-login', $user['password'], 'not a hash of anything: nothing can match it');

        $vault = $this->get(ExtensionConfiguration::class)->get('nr_vault');
        self::assertIsArray($vault);
        self::assertSame((string)$user['uid'], $vault['provisioningBeUserUid']);
        self::assertStringContainsString(
            'webcon-jev:token:import --as-provisioner',
            (string)preg_replace('/\s+/', ' ', $tester->getDisplay()),
            'the console wraps long lines, so compare with whitespace collapsed',
        );
    }

    #[Test]
    public function runningItAgainRepairsRatherThanDuplicates(): void
    {
        $tester = new CommandTester($this->command());
        $tester->execute([]);
        $user = $this->rawRow('be_users', 'username', 'vault_provisioner');

        // Somebody promoted it to admin, disabled it, took it out of its group, and widened the
        // group to a permission it must never hold.
        $this->getConnectionPool()->getConnectionForTable('be_users')
            ->update('be_users', ['admin' => 1, 'disable' => 1, 'usergroup' => ''], ['uid' => (int)$user['uid']]);
        $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->update('be_groups', ['custom_options' => 'tx_nrvault:secret.delete'], ['title' => 'Vault provisioning']);

        self::assertSame(0, $tester->execute([]), $tester->getDisplay());

        self::assertSame(1, $this->rawCount('be_users', 'username', 'vault_provisioner'), 'the same row, not a second user');
        self::assertSame(1, $this->rawCount('be_groups', 'title', 'Vault provisioning'));

        $repaired = $this->rawRow('be_users', 'username', 'vault_provisioner');
        self::assertSame((int)$user['uid'], (int)$repaired['uid']);
        self::assertSame(0, (int)$repaired['admin']);
        self::assertSame(0, (int)$repaired['disable']);
        self::assertNotSame('', $repaired['usergroup']);
        self::assertSame(
            'tx_nrvault:secret.create,tx_nrvault:secret.rotate',
            $this->rawRow('be_groups', 'title', 'Vault provisioning')['custom_options'],
            'a widened grant is narrowed back',
        );
    }

    #[Test]
    public function skippingTheConfigurationLeavesNrVaultAlone(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(0, $tester->execute(['--skip-configuration' => true]), $tester->getDisplay());

        $vault = $this->get(ExtensionConfiguration::class)->get('nr_vault');
        self::assertTrue(!is_array($vault) || !isset($vault['provisioningBeUserUid']) || $vault['provisioningBeUserUid'] === '0');
        self::assertStringContainsString('yourself', $tester->getDisplay());
    }

    /**
     * Console commands are private services; the two collaborators are not.
     */
    private function command(): SetupProvisionerCommand
    {
        return new SetupProvisionerCommand($this->get(ConnectionPool::class), $this->get(ExtensionConfiguration::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function rawRow(string $table, string $column, string $value): array
    {
        $query = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();
        $row = $query->select('*')->from($table)
            ->where(
                $query->expr()->eq($column, $query->createNamedParameter($value)),
                $query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('uid')->setMaxResults(1)
            ->executeQuery()->fetchAssociative();
        self::assertIsArray($row, sprintf('%s with %s = %s exists', $table, $column, $value));

        return $row;
    }

    private function rawCount(string $table, string $column, string $value): int
    {
        $query = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();

        return (int)$query->count('uid')->from($table)
            ->where(
                $query->expr()->eq($column, $query->createNamedParameter($value)),
                $query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchOne();
    }
}
