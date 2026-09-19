<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Seeding\ExampleSeeder;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Builds the five Jev examples in the Powermail Lab.
 */
#[AsCommand(
    name: 'webcon-jev:examples:seed',
    description: 'Create the five Jev powermail examples, their decisions and their conditions',
)]
final class SeedExamplesCommand extends Command
{
    private const LAB_SLUG = '/desiderio-powermail-lab';

    public function __construct(
        private readonly ExampleSeeder $seeder,
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'The Powermail Lab page to build them under')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'The German language id', '1')
            ->setHelp(
                'Running this a second time replaces what the first run made rather than adding to it.'
                . ' It only ever touches records it marked as its own.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $missing = $this->seeder->missingTables();
        if ($missing !== []) {
            $io->error('These tables are missing, so powermail or powermail_cond is not installed: '
                . implode(', ', $missing));

            return Command::FAILURE;
        }

        $labPageUid = Cast::int($input->getOption('page'));
        if ($labPageUid <= 0) {
            $labPageUid = $this->findLabPage();
        }
        if ($labPageUid <= 0) {
            $io->error(sprintf(
                'No page with the slug "%s". Seed the Desiderio styleguide first, or pass --page.',
                self::LAB_SLUG,
            ));

            return Command::FAILURE;
        }

        $io->section(sprintf('Building the Jev examples under page %d', $labPageUid));
        $counts = $this->seeder->seed($labPageUid, Cast::int($input->getOption('language'), 1), $io);

        $io->success(sprintf(
            '%d examples: %d decisions, %d forms, %d conditions, %d pages.',
            $counts['examples'],
            $counts['decisions'],
            $counts['forms'],
            $counts['conditions'],
            $counts['pages'],
        ));
        $io->note('Flush the frontend caches, then open the pages below the Powermail Lab.');

        return Command::SUCCESS;
    }

    private function findLabPage(): int
    {
        $query = $this->connectionPool->getQueryBuilderForTable('pages');
        $query->getRestrictions()->removeAll();

        $uid = $query
            ->select('uid')
            ->from('pages')
            ->where(
                $query->expr()->eq('slug', $query->createNamedParameter(self::LAB_SLUG)),
                $query->expr()->eq('deleted', $query->createNamedParameter(0)),
                $query->expr()->eq('sys_language_uid', $query->createNamedParameter(0)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return Cast::int($uid);
    }
}
