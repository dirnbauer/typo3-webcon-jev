<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webconsulting\WebconJev\Configuration\Settings;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Keeps the run log from growing without bound. Schedule it.
 */
#[AsCommand(
    name: 'webcon-jev:log:prune',
    description: 'Delete run log rows older than the configured retention',
)]
final class PruneLogCommand extends Command
{
    public function __construct(
        private readonly RunLogger $runLogger,
        private readonly Settings $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_REQUIRED,
            'Keep this many days instead of the configured retention',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = Cast::int($input->getOption('days'), $this->settings->logRetentionDays());
        if ($days < 1) {
            $io->error('Keep at least one day.');

            return Command::FAILURE;
        }

        $deleted = $this->runLogger->pruneBefore(time() - $days * 86400);
        $io->success(sprintf('Deleted %d run log rows older than %d days.', $deleted, $days));

        return Command::SUCCESS;
    }
}
