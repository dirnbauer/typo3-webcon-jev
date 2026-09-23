<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Netresearch\NrVault\Security\TechnicalActorContextInterface;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Exception\JevException;
use Webconsulting\WebconJev\Service\ConnectionProbe;
use Webconsulting\WebconJev\Service\ProvisionerResolver;
use Webconsulting\WebconJev\Service\TokenProvider;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Asks Jev one trivial question, to prove the token, the endpoint and the network all work.
 */
#[AsCommand(
    name: 'webcon-jev:ping',
    description: 'Check that the configured Jev token can reach the API',
)]
final class PingCommand extends Command
{
    public function __construct(
        private readonly ConnectionProbe $probe,
        private readonly TokenProvider $tokenProvider,
        private readonly Settings $settings,
        private readonly TechnicalActorContextInterface $technicalActor,
        private readonly ProvisionerResolver $provisioner,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'as-provisioner',
            null,
            InputOption::VALUE_NONE,
            'Read the token as nr-vault\'s provisioning backend user, for a server that keeps CLI vault access off',
        )->setHelp(
            'On a server with nr-vault\'s "allowCliAccess" off — which is the right default — the'
            . ' unattributed CLI actor may not READ the token either, so a plain ping reports no token'
            . ' even though the frontend resolves it perfectly well through its frontend_accessible'
            . ' flag. Pass --as-provisioner there, and the check runs as the same identity that wrote it.',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $asProvisioner = (bool)$input->getOption('as-provisioner');
        $provisionerUid = $this->provisioner->resolve();
        if ($asProvisioner && $provisionerUid <= 0) {
            $io->error('No provisioning backend user. Run "webcon-jev:vault:setup-provisioner" first.');

            return Command::FAILURE;
        }

        // Everything that touches the vault runs inside the same scope, so the status line and the
        // call itself cannot disagree about whether the token is readable.
        $check = fn(): int => $this->check($io, $asProvisioner, $provisionerUid);

        return $asProvisioner
            ? $this->technicalActor->runAs($provisionerUid, $check)
            : $check();
    }

    private function check(SymfonyStyle $io, bool $asProvisioner, int $provisionerUid): int
    {
        $io->definitionList(
            ['Endpoint' => $this->settings->endpoint()],
            ['Model' => $this->settings->model()],
            ['Token' => $this->tokenProvider->describeSource()],
            ['Enabled' => $this->settings->isEnabled() ? 'yes' : 'no'],
            ['Reading as' => $asProvisioner
                ? $this->provisioner->describe()
                : 'the ambient actor (add --as-provisioner on a server)'],
            ['Frontend can read it' => $this->tokenProvider->isReadableByFrontend()
                ? 'yes'
                : 'NO — powermail conditions and routing will fall back'],
        );

        if (!$this->tokenProvider->hasToken()) {
            $io->error('No token this actor can read.');
            $io->note($asProvisioner
                ? 'Store one with "webcon-jev:token:import --as-provisioner" after setting TYPESAFE_API_KEY.'
                : 'If the token is in the vault but this installation keeps nr-vault\'s CLI access off,'
                    . ' re-run with --as-provisioner. Otherwise store one with "webcon-jev:token:import".');

            return Command::FAILURE;
        }

        try {
            $result = $this->probe->ask();
        } catch (JevException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $answer = $result->get(ConnectionProbe::QUESTION);
        $io->success(sprintf(
            'Jev answered in %d ms: mood=%s, confidence %.2f (model %s, %d input tokens, $%.6f).',
            (int)$result->durationMs,
            Cast::string($answer?->choice),
            $answer !== null ? $answer->confidence : 0.0,
            $result->model,
            $result->usage->inputTokens,
            $result->usage->costInUsd(),
        ));

        return Command::SUCCESS;
    }
}
