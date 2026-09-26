<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Seeding;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\WebconJev\Data\JevExampleDefinitions;
use Webconsulting\WebconJev\Seeding\ExampleSeeder;
use Webconsulting\WebconJev\Seeding\SchemaHelper;
use Webconsulting\WebconJev\Support\Cast;

/**
 * The lab page lists the examples next to EXT:desiderio's own forms. A reseed gives the example
 * pages new uids, so the list has to be rebuilt with them rather than added to.
 */
final class ExampleSeederTest extends FunctionalTestCase
{
    // The seeder writes powermail and powermail_cond records; see OptionalIntegrationsTest for
    // why scheduler has to be loaded with them.
    protected array $coreExtensionsToLoad = ['backend', 'install', 'frontend', 'extbase', 'fluid', 'scheduler'];

    protected array $testExtensionsToLoad = ['nr_vault', 'powermail', 'powermail_cond', 'webcon_jev'];

    #[Test]
    public function theLabPageListsEveryExampleOncePerLanguageAfterAReseed(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/powermail_lab.csv');
        $seeder = new ExampleSeeder($this->getConnectionPool(), new SchemaHelper($this->getConnectionPool()));
        $io = new SymfonyStyle(new ArrayInput([]), new NullOutput());

        $seeder->seed(1, 1, $io);
        $seeder->seed(1, 1, $io);

        $overview = $this->overviewRows();
        self::assertCount(2, $overview, 'one section in English, one in German');
        [$english, $german] = $overview;

        self::assertSame([1, 0], [Cast::int($english['pid']), Cast::int($english['sys_language_uid'])]);
        // On the lab page itself, not on its translation record, or the frontend never shows it.
        self::assertSame(
            [1, 1, Cast::int($english['uid'])],
            [Cast::int($german['pid']), Cast::int($german['sys_language_uid']), Cast::int($german['l18n_parent'])],
        );

        $examples = JevExampleDefinitions::all();
        $pageUids = $this->examplePageUids();
        self::assertCount(count($examples), $pageUids);

        $englishBody = Cast::string($english['bodytext']);
        $germanBody = Cast::string($german['bodytext']);
        self::assertSame(count($examples), substr_count($englishBody, '<li>'));
        self::assertSame(count($examples), substr_count($germanBody, '<li>'));
        foreach ($pageUids as $pageUid) {
            self::assertStringContainsString(sprintf('"t3://page?uid=%d"', $pageUid), $englishBody);
            self::assertStringContainsString(sprintf('"t3://page?uid=%d"', $pageUid), $germanBody);
        }
        foreach ($examples as $example) {
            self::assertStringContainsString(htmlspecialchars($example['titleEn']), $englishBody);
            self::assertStringContainsString(htmlspecialchars($example['titleDe']), $germanBody);
        }
    }

    #[Test]
    public function theGermanElementsOfAnExampleSitOnItsEnglishPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/powermail_lab.csv');
        $seeder = new ExampleSeeder($this->getConnectionPool(), new SchemaHelper($this->getConnectionPool()));
        $seeder->seed(1, 1, new SymfonyStyle(new ArrayInput([]), new NullOutput()));

        $pageUids = $this->examplePageUids();
        self::assertNotSame([], $pageUids);
        $query = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $query->getRestrictions()->removeAll();
        $rows = $query
            ->select('uid', 'pid', 'CType', 'sys_language_uid', 'l18n_parent')
            ->from('tt_content')
            ->where($query->expr()->in('pid', $query->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        $english = [];
        foreach ($rows as $row) {
            if (Cast::int($row['sys_language_uid']) === 0) {
                $english[Cast::int($row['uid'])] = Cast::int($row['pid']);
            }
        }
        $german = array_values(array_filter($rows, static fn(array $row): bool => Cast::int($row['sys_language_uid']) === 1));
        // An intro and a plugin per example, each translated on the page of its original:
        // content stored on the page's translation record is never shown.
        self::assertCount(2 * count($pageUids), $german);
        foreach ($german as $row) {
            $original = Cast::int($row['l18n_parent']);
            self::assertArrayHasKey($original, $english);
            self::assertSame($english[$original], Cast::int($row['pid']));
        }
    }

    #[Test]
    public function theGermanPluginNamesTheOriginalFormAndThanksInGerman(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/powermail_lab.csv');
        $seeder = new ExampleSeeder($this->getConnectionPool(), new SchemaHelper($this->getConnectionPool()));
        $seeder->seed(1, 1, new SymfonyStyle(new ArrayInput([]), new NullOutput()));

        $query = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $query->getRestrictions()->removeAll();
        $plugins = $query
            ->select('uid', 'sys_language_uid', 'l18n_parent', 'pi_flexform')
            ->from('tt_content')
            ->where($query->expr()->eq('CType', $query->createNamedParameter('powermail_pi1')))
            ->executeQuery()
            ->fetchAllAssociative();

        $formOf = static fn(array $row): string => (string)(preg_match(
            '/settings\.flexform\.main\.form"><value index="vDEF">(\d+)</',
            Cast::string($row['pi_flexform']),
            $match,
        ) ? $match[1] : '');
        $english = [];
        foreach ($plugins as $plugin) {
            if (Cast::int($plugin['sys_language_uid']) === 0) {
                $english[Cast::int($plugin['uid'])] = $plugin;
            }
        }
        $german = array_filter($plugins, static fn(array $row): bool => Cast::int($row['sys_language_uid']) === 1);
        self::assertCount(count(JevExampleDefinitions::all()), $german);

        foreach ($german as $plugin) {
            $original = $english[Cast::int($plugin['l18n_parent'])] ?? null;
            self::assertNotNull($original);
            // Powermail compares the posted form uid - always the original's - with the plugin's.
            self::assertSame($formOf($original), $formOf($plugin));
            self::assertStringContainsString('Vielen Dank für Ihre Nachricht.', Cast::string($plugin['pi_flexform']));
        }
    }

    #[Test]
    public function everyIntroSaysWhatToTryAndWhatIsHard(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/powermail_lab.csv');
        $seeder = new ExampleSeeder($this->getConnectionPool(), new SchemaHelper($this->getConnectionPool()));
        $seeder->seed(1, 1, new SymfonyStyle(new ArrayInput([]), new NullOutput()));

        $query = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $query->getRestrictions()->removeAll();
        $intros = $query
            ->select('pid', 'sys_language_uid', 'bodytext')
            ->from('tt_content')
            ->where(
                $query->expr()->in('pid', $query->createNamedParameter($this->examplePageUids(), Connection::PARAM_INT_ARRAY)),
                $query->expr()->eq('CType', $query->createNamedParameter('text')),
            )
            ->orderBy('pid')
            ->addOrderBy('sys_language_uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $examples = JevExampleDefinitions::all();
        self::assertCount(2 * count($examples), $intros, 'an intro per example and language');

        foreach (array_values($examples) as $index => $example) {
            foreach (['En' => 0, 'De' => 1] as $language => $offset) {
                $body = Cast::string($intros[2 * $index + $offset]['bodytext']);
                $tries = Cast::stringList($example['try' . $language] ?? null);

                self::assertNotSame([], $tries, $example['slug'] . ' ' . $language . ' has nothing to try');
                self::assertCount(count(Cast::stringList($example['tryEn'] ?? null)), $tries, $example['slug'] . ': the languages list the same tries');
                self::assertStringContainsString($language === 'De' ? '<h2>Probieren Sie es aus</h2>' : '<h2>Try it</h2>', $body);
                self::assertStringContainsString($language === 'De' ? '<h2>Die Herausforderung</h2>' : '<h2>The challenge</h2>', $body);
                self::assertSame(count($tries), substr_count($body, '<li>'));
                self::assertStringContainsString(htmlspecialchars(Cast::string($example['challenge' . $language] ?? null)), $body);
                self::assertStringStartsWith('<p>' . htmlspecialchars(Cast::string($example['intro' . $language] ?? null)) . '</p>', $body);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function overviewRows(): array
    {
        $query = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $query->getRestrictions()->removeAll();

        return $query
            ->select('uid', 'pid', 'sys_language_uid', 'l18n_parent', 'bodytext')
            ->from('tt_content')
            ->where($query->expr()->eq('rowDescription', $query->createNamedParameter(ExampleSeeder::OVERVIEW_MARKER)))
            ->orderBy('sys_language_uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<int>
     */
    private function examplePageUids(): array
    {
        $query = $this->getConnectionPool()->getQueryBuilderForTable('pages');
        $query->getRestrictions()->removeAll();
        $uids = $query
            ->select('uid')
            ->from('pages')
            ->where(
                $query->expr()->like('slug', $query->createNamedParameter(ExampleSeeder::SLUG_PREFIX . '%')),
                $query->expr()->eq('sys_language_uid', 0),
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_values(array_map(static fn(mixed $uid): int => Cast::int($uid), $uids));
    }
}
