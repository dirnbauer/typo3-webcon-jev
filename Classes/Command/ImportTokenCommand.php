<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Netresearch\NrVault\Security\TechnicalActorContextInterface;
use Netresearch\NrVault\Service\VaultServiceInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Service\ProvisionerResolver;
use Webconsulting\WebconJev\Service\TokenProvider;

/**
 * Moves the Jev API token out of the environment and into the vault.
 *
 * The token is taken from TYPESAFE_API_KEY rather than from an argument, so it never appears in
 * a shell history, a process list, or a terminal recording.
 */
#[AsCommand(
    name: 'webcon-jev:token:import',
    description: 'Store the Jev API token from TYPESAFE_API_KEY in the vault',
)]
final class ImportTokenCommand extends Command
{
    public function __construct(
        private readonly VaultServiceInterface $vault,
        private readonly Settings $settings,
        private readonly TechnicalActorContextInterface $technicalActor,
        private readonly ProvisionerResolver $provisioner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace a token that is already stored')
            ->addOption(
                'as-provisioner',
                null,
                InputOption::VALUE_NONE,
                'Write as nr-vault\'s configured provisioning backend user instead of the unattributed CLI actor',
            )
            ->setHelp(
                'Reads ' . TokenProvider::ENVIRONMENT_VARIABLE . ' and stores it under the identifier from the'
                . ' extension configuration. The secret is marked frontend-accessible, because the powermail'
                . ' condition endpoint that consults Jev runs with no backend user. The token itself is never'
                . ' sent to a browser — only the decision it produced.'
                . "\n\n"
                . 'On an installation that keeps nr-vault\'s "allowCliAccess" switched off — which it should —'
                . ' pass --as-provisioner. The write is then attributed to the backend user named by nr-vault\'s'
                . ' "provisioningBeUserUid" setting, which needs a group carrying tx_nrvault:secret.create and'
                . ' secret.rotate and nothing else. The UID comes from configuration, never from this command'
                . ' line, so a shell alone cannot choose whose identity to write under.',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $this->settings->tokenIdentifier();

        $token = getenv(TokenProvider::ENVIRONMENT_VARIABLE);
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            $io->error(sprintf(
                '%s is not set. Add it to .ddev/config.local.yaml (which is git-ignored) and restart ddev.',
                TokenProvider::ENVIRONMENT_VARIABLE,
            ));

            return Command::FAILURE;
        }

        $write = function (bool $asProvisioner) use ($identifier, $token, $input): TokenImportOutcome {
            $exists = $this->vault->exists($identifier);
            if ($exists && !$input->getOption('force')) {
                return TokenImportOutcome::Skipped;
            }

            if ($exists) {
                $this->vault->rotate($identifier, $token, 'Replaced from ' . TokenProvider::ENVIRONMENT_VARIABLE);

                return TokenImportOutcome::Rotated;
            }

            $options = [
                'frontendAccessible' => true,
                'description' => 'TypeSafe AI Jev API token, used by EXT:webcon_jev',
            ];

            // Owned by whoever wrote it. Rotating a secret is a per-secret ACL decision, not a
            // group permission, so a provisioner that handed ownership to uid 0 could create the
            // token once and then be refused every rotation of it afterwards — the failure would
            // surface the first time somebody tried to replace a leaked key, which is the worst
            // possible moment. Omitting the option lets nr-vault default it to the acting
            // identity; the unattributed CLI actor has none, so there it stays 0 as before.
            if (!$asProvisioner) {
                $options['owner'] = 0;
            }

            $this->vault->store($identifier, $token, $options);

            return TokenImportOutcome::Created;
        };

        $asProvisioner = (bool)$input->getOption('as-provisioner');
        $provisionerUid = $this->provisioner->resolve();
        if ($asProvisioner && $provisionerUid <= 0) {
            $io->error('No provisioning backend user. Run "webcon-jev:vault:setup-provisioner" first.');

            return Command::FAILURE;
        }

        try {
            $outcome = $asProvisioner
                ? $this->technicalActor->runAs($provisionerUid, static fn(): TokenImportOutcome => $write(true))
                : $write(false);
        } catch (Throwable $exception) {
            $io->error('The vault refused the token: ' . $exception->getMessage());
            if (!$asProvisioner && str_contains($exception->getMessage(), 'permission denied')) {
                $io->note(
                    'This installation keeps nr-vault\'s CLI access off, which is the right default.'
                    . ' Re-run with --as-provisioner.',
                );
            }

            return Command::FAILURE;
        }

        if ($outcome === TokenImportOutcome::Skipped) {
            $io->warning(sprintf('"%s" is already in the vault. Pass --force to replace it.', $identifier));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            '%s "%s" in the vault as %s (%d characters, ending "%s").',
            $outcome === TokenImportOutcome::Rotated ? 'Replaced' : 'Stored',
            $identifier,
            $asProvisioner ? 'provisioning backend user ' . $provisionerUid : 'the CLI actor',
            strlen($token),
            substr($token, -4),
        ));
        $io->note('You can remove TYPESAFE_API_KEY from ddev now — the vault is read first.');

        return Command::SUCCESS;
    }
}
