<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Seeding;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
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
