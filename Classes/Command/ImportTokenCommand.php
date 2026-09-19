<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Netresearch\NrVault\Service\VaultServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Webconsulting\WebconJev\Configuration\Settings;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace a token that is already stored')
            ->setHelp(
                'Reads ' . TokenProvider::ENVIRONMENT_VARIABLE . ' and stores it under the identifier from the'
                . ' extension configuration. The secret is marked frontend-accessible, because the powermail'
                . ' condition endpoint that consults Jev runs with no backend user. The token itself is never'
                . ' sent to a browser — only the decision it produced.',
            );
    }

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

        try {
            $exists = $this->vault->exists($identifier);
            if ($exists && !$input->getOption('force')) {
                $io->warning(sprintf('"%s" is already in the vault. Pass --force to replace it.', $identifier));

                return Command::SUCCESS;
            }

            if ($exists) {
                $this->vault->rotate($identifier, $token, 'Replaced from ' . TokenProvider::ENVIRONMENT_VARIABLE);
            } else {
                $this->vault->store($identifier, $token, [
                    'owner' => 0,
                    'frontendAccessible' => true,
                    'description' => 'TypeSafe AI Jev API token, used by EXT:webcon_jev',
                ]);
            }
        } catch (Throwable $exception) {
            $io->error('The vault refused the token: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%s "%s" in the vault (%d characters, ending "%s").',
            $exists ? 'Replaced' : 'Stored',
            $identifier,
            strlen($token),
            substr($token, -4),
        ));
        $io->note('You can remove TYPESAFE_API_KEY from ddev now — the vault is read first.');

        return Command::SUCCESS;
    }
}
