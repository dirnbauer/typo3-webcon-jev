<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * Until 0.2.0 every label of these tables was a broken reference ("locallang_db.xlfx_…") that the
 * record editor printed as it was. A label that does not resolve comes back unchanged, which is
 * what this looks for — in English and in German.
 */
final class TcaLabelsTest extends AbstractJevTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tablesAndLanguages(): iterable
    {
        foreach (['tx_webconjev_decision', 'tx_webconjev_question', 'tx_webconjev_criterion'] as $table) {
            foreach (['default', 'de'] as $language) {
                yield $table . ' in ' . $language => [$table, $language];
            }
        }
    }

    #[Test]
    #[DataProvider('tablesAndLanguages')]
    public function everyLabelResolves(string $table, string $language): void
    {
        $languageService = $this->get(LanguageServiceFactory::class)->create($language);
        $tca = $GLOBALS['TCA'][$table] ?? null;
        self::assertIsArray($tca);

        $references = [$tca['ctrl']['title']];
        foreach ($tca['columns'] as $field => $column) {
            foreach (['label', 'description'] as $key) {
                if (isset($column[$key]) && is_string($column[$key])) {
                    $references[$field . '.' . $key] = $column[$key];
                }
            }
            foreach ($column['config']['items'] ?? [] as $item) {
                $references[$field . '.item'] = $item['label'];
            }
        }
        preg_match_all('/--div--;([^,]+)/', (string)$tca['types']['1']['showitem'], $tabs);
        foreach ($tabs[1] as $index => $tab) {
            $references['tab.' . $index] = trim($tab);
        }

        foreach ($references as $where => $reference) {
            if ($reference === '' || !is_string($reference)) {
                continue;
            }
            $label = $languageService->sL($reference);
            self::assertNotSame($reference, $label, sprintf('%s: "%s" does not resolve', $where, $reference));
            self::assertNotSame('', trim($label), sprintf('%s: "%s" is empty', $where, $reference));
        }
    }

    #[Test]
    public function theGermanTitleIsGerman(): void
    {
        $languageService = $this->get(LanguageServiceFactory::class)->create('de');

        self::assertSame('Jev-Entscheidung', $languageService->sL($GLOBALS['TCA']['tx_webconjev_decision']['ctrl']['title']));
    }
}
