<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Service;

use Netresearch\NrVault\Configuration\ExtensionConfigurationInterface as VaultConfiguration;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Service\ProvisionerResolver;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * The provisioning identity must survive a deploy that resets configuration. Measured on the
 * lab: settings.php is part of the container image, so a uid written there was back to 0 within
 * minutes, and a rotation months later would have failed with nothing obviously changed.
 */
final class ProvisionerResolverTest extends AbstractJevTestCase
{
    #[Test]
    public function aConfiguredUidWinsOverTheLookupByName(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->getConnectionPool()->getConnectionForTable('be_users')->insert('be_users', [
            'uid' => 99,
            'pid' => 0,
            'username' => 'deliberately_named',
            'password' => 'invalid-no-login',
        ]);
        $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: 99), $this->get(ConnectionPool::class));

        self::assertSame(99, $resolver->resolve());
        self::assertStringContainsString('nr-vault configuration', $resolver->describe());
    }

    #[Test]
    public function aConfiguredUidThatNoLongerExistsFallsBackToTheName(): void
    {
        // typo3-lab 2026-09-26: the database was replaced by a development copy where the
        // provisioner is uid 13, and the image still configured 12 — a uid with no row behind it.
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: 12), $this->get(ConnectionPool::class));

        self::assertSame(20, $resolver->resolve());
        self::assertSame(
            'backend user 20 ("vault_provisioner", found by name; the configured backend user 12 is gone or disabled)',
            $resolver->describe(),
        );
    }

    #[Test]
    public function aConfiguredUidThatIsDeletedOrDisabledIsNotUsed(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $connection = $this->getConnectionPool()->getConnectionForTable('be_users');
        $connection->insert('be_users', ['uid' => 30, 'pid' => 0, 'username' => 'old_provisioner', 'deleted' => 1]);
        $connection->insert('be_users', ['uid' => 31, 'pid' => 0, 'username' => 'locked_provisioner', 'disable' => 1]);

        foreach ([30, 31] as $uid) {
            $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: $uid), $this->get(ConnectionPool::class));
            self::assertSame(20, $resolver->resolve(), sprintf('configured uid %d', $uid));
        }
    }

    #[Test]
    public function aStaleConfigurationAndNoProvisionerSaysBoth(): void
    {
        $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: 12), $this->get(ConnectionPool::class));

        self::assertSame(0, $resolver->resolve());
        self::assertSame(
            'none — run "webcon-jev:vault:setup-provisioner"; the configured backend user 12 is gone or disabled',
            $resolver->describe(),
        );
    }

    #[Test]
    public function withNoConfigurationTheUserIsFoundByName(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: 0), $this->get(ConnectionPool::class));

        self::assertSame(20, $resolver->resolve());
        self::assertStringContainsString('found by name', $resolver->describe());
    }

    #[Test]
    public function aDisabledOrNonRootUserIsNotOffered(): void
    {
        // nr-vault refuses to resolve an actor that is disabled or not at root level, so handing
        // it such a uid would only move the failure to a worse place.
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->getConnectionPool()->getConnectionForTable('be_users')
            ->update('be_users', ['disable' => 1], ['uid' => 20]);
        $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: 0), $this->get(ConnectionPool::class));

        self::assertSame(0, $resolver->resolve());
        self::assertStringContainsString('setup-provisioner', $resolver->describe());
    }

    #[Test]
    public function withNothingAtAllItSaysWhatToRun(): void
    {
        $resolver = new ProvisionerResolver(self::vaultConfiguration(uid: 0), $this->get(ConnectionPool::class));

        self::assertSame(0, $resolver->resolve());
        self::assertSame('none — run "webcon-jev:vault:setup-provisioner"', $resolver->describe());
    }

    private static function vaultConfiguration(int $uid): VaultConfiguration
    {
        $configuration = self::createStub(VaultConfiguration::class);
        $configuration->method('getProvisioningBeUserUid')->willReturn($uid);

        return $configuration;
    }
}
