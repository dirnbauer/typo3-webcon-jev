<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Creates the backend identity nr-vault writes secrets as on a server.
 *
 * The alternative is switching nr-vault's "allowCliAccess" on, which hands every CLI process on
 * the host create, rotate and use over every secret in that vault — the other extensions'
 * credentials included. This is the narrow version of the same capability: one non-admin user,
 * two permissions, no password that can ever be used to sign in, and every write it performs
 * attributed to it by name in the audit log.
 *
 * It is a command rather than three steps in a manual because a production backend is a bad place
 * to be clicking, and because a deployment that has to be reproduced in a year should not depend
 * on somebody remembering which two permission options to tick.
 */
#[AsCommand(
    name: 'webcon-jev:vault:setup-provisioner',
    description: "Create nr-vault's provisioning backend user, so a server need not enable CLI vault access",
)]
final class SetupProvisionerCommand extends Command
{
    private const GROUP_TITLE = 'Vault provisioning';
    private const USERNAME = 'vault_provisioner';

    /** Exactly what an unattended deployment needs to write a secret, and nothing more. */
    private const GRANTS = 'tx_nrvault:secret.create,tx_nrvault:secret.rotate';

    /**
     * Not a hash of anything. TYPO3's password hashing never produces this, so no input can match
     * it and the account cannot be signed into even if somebody enables it in the backend.
     */
    private const UNUSABLE_PASSWORD = 'invalid-no-login';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'skip-configuration',
            null,
            InputOption::VALUE_NONE,
            'Create the identity but leave nr-vault\'s provisioningBeUserUid alone',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = time();

        try {
            $groupUid = $this->upsertGroup($now);
            $userUid = $this->upsertUser($groupUid, $now);
        } catch (Throwable $exception) {
            $io->error('Could not create the provisioning identity: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Backend group' => sprintf('%d — %s', $groupUid, self::GROUP_TITLE)],
            ['Permissions' => self::GRANTS],
            ['Backend user' => sprintf('%d — %s (not an admin, cannot sign in)', $userUid, self::USERNAME)],
        );

        if ($input->getOption('skip-configuration')) {
            $io->note(sprintf(
                'Set nr_vault\'s "provisioningBeUserUid" to %d yourself, then run "webcon-jev:token:import --as-provisioner".',
                $userUid,
            ));

            return Command::SUCCESS;
        }

        try {
            $vault = $this->extensionConfiguration->get('nr_vault');
            $vault = is_array($vault) ? $vault : [];
            $previous = Cast::int($vault['provisioningBeUserUid'] ?? null);
            $vault['provisioningBeUserUid'] = (string)$userUid;
            $this->extensionConfiguration->set('nr_vault', $vault);
        } catch (Throwable $exception) {
            $io->error('The identity exists, but nr-vault\'s configuration could not be written: '
                . $exception->getMessage());
            $io->note(sprintf('Set "provisioningBeUserUid" to %d by hand.', $userUid));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'nr-vault will write as backend user %d%s. Run "webcon-jev:token:import --as-provisioner" next.',
            $userUid,
            $previous > 0 && $previous !== $userUid ? sprintf(' (was %d)', $previous) : '',
        ));
        $io->note(
            'This extension does not depend on that setting surviving: it finds the user by name if the'
            . ' configuration is gone. On a container deployment config/system/settings.php is usually part'
            . ' of the image rather than a volume, so the next deploy is likely to reset it.',
        );

        return Command::SUCCESS;
    }

    private function upsertGroup(int $now): int
    {
        $connection = $this->connectionPool->getConnectionForTable('be_groups');
        $existing = $this->existingUid('be_groups', 'title', self::GROUP_TITLE);

        if ($existing > 0) {
            $connection->update('be_groups', ['custom_options' => self::GRANTS, 'tstamp' => $now], ['uid' => $existing]);

            return $existing;
        }

        $connection->insert('be_groups', [
            'pid' => 0,
            'tstamp' => $now,
            'crdate' => $now,
            'deleted' => 0,
            'hidden' => 0,
            'title' => self::GROUP_TITLE,
            'custom_options' => self::GRANTS,
            'description' => 'Carries only the two vault permissions an unattended deployment needs. Not for people.',
        ], ['pid' => Connection::PARAM_INT, 'tstamp' => Connection::PARAM_INT, 'crdate' => Connection::PARAM_INT]);

        return Cast::int($connection->lastInsertId());
    }

    private function upsertUser(int $groupUid, int $now): int
    {
        $connection = $this->connectionPool->getConnectionForTable('be_users');
        $existing = $this->existingUid('be_users', 'username', self::USERNAME);

        // The technical actor must be a root-level, enabled, non-admin user: nr-vault refuses to
        // resolve anything else, and an admin would bypass the grant this whole arrangement exists
        // to keep narrow. Re-asserting them on an existing row makes the command idempotent and
        // repairs a user somebody has since edited.
        $shape = [
            'pid' => 0,
            'disable' => 0,
            'admin' => 0,
            'usergroup' => (string)$groupUid,
            'starttime' => 0,
            'endtime' => 0,
            'tstamp' => $now,
        ];

        if ($existing > 0) {
            $connection->update('be_users', $shape, ['uid' => $existing]);

            return $existing;
        }

        $connection->insert('be_users', $shape + [
            'crdate' => $now,
            'deleted' => 0,
            'username' => self::USERNAME,
            'password' => self::UNUSABLE_PASSWORD,
            'realName' => 'Vault provisioning (technical)',
            'description' => 'Technical identity for unattended vault writes. Never signs in: the password hash is deliberately unusable.',
        ]);

        return Cast::int($connection->lastInsertId());
    }

    /**
     * The row as it is, hidden or not.
     *
     * Connection::select() and ::count() go through a QueryBuilder that applies TYPO3's default
     * restrictions, and for be_users that includes the "disable" flag. Measured: a provisioning
     * user somebody had disabled was invisible to the lookup, so this command created a second
     * vault_provisioner instead of repairing the first — and two users of one name make the
     * lookup by name ambiguous, which is the one thing it exists to avoid. An identity check has
     * to see the disabled row, because repairing it is the point.
     */
    private function existingUid(string $table, string $column, string $value): int
    {
        $query = $this->connectionPool->getQueryBuilderForTable($table);
        $query->getRestrictions()->removeAll();

        return Cast::int(
            $query
                ->select('uid')
                ->from($table)
                ->where(
                    $query->expr()->eq($column, $query->createNamedParameter($value)),
                    $query->expr()->eq('deleted', $query->createNamedParameter(0, Connection::PARAM_INT)),
                )
                ->orderBy('uid')
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne(),
        );
    }
}
