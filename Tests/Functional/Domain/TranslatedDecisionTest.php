<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Domain;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Editing\DecisionValidator;
use Webconsulting\WebconJev\Editing\DecisionWriter;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Editing\Dto\ValidationError;
use Webconsulting\WebconJev\Support\Cast;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * A decision and its German translation share one identifier — the identifier is excluded from
 * translation, so every translation carries its decision's. Decision 1 ("routing") and its
 * translation 2 are exactly that, as the five lab examples are.
 *
 * The identifier is unique among default-language decisions only; a translation is never a
 * decision of its own — not in a lookup, not in the module, and not when it is saved.
 */
final class TranslatedDecisionTest extends AbstractJevTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
    }

    #[Test]
    public function aLookupByIdentifierFindsTheDecisionAndOverlaysTheRequestedLanguage(): void
    {
        $repository = $this->get(DecisionRepository::class);

        $default = $repository->findByIdentifier('routing');
        $german = $repository->findByIdentifier('routing', 1);

        self::assertNotNull($default);
        self::assertNotNull($german);
        self::assertSame(1, $default->uid);
        self::assertSame(1, $german->uid, 'the same decision, never the translation row');
        self::assertSame('Contact routing', $default->title);
        self::assertSame('Kontakt-Routing', $german->title);
        self::assertSame('Welche Abteilung?', $german->questions[0]->instructions);
    }

    #[Test]
    public function aTranslationsUidStandsForItsDecision(): void
    {
        $repository = $this->get(DecisionRepository::class);

        $inDefault = $repository->findByUid(2);
        $inGerman = $repository->findByUid(2, 1);

        self::assertNotNull($inDefault);
        self::assertNotNull($inGerman);
        self::assertSame(1, $inDefault->uid);
        self::assertSame('Contact routing', $inDefault->title, 'the wording follows the language asked for');
        self::assertSame(['department', 'is_bug'], array_map(static fn(DecisionQuestion $q): string => $q->name, $inDefault->questions));
        self::assertSame(1, $inGerman->uid);
        self::assertSame('Kontakt-Routing', $inGerman->title);
    }

    #[Test]
    public function theModuleListsEachDecisionOnce(): void
    {
        $uids = array_map(
            static fn(Decision $decision): int => $decision->uid,
            $this->get(DecisionRepository::class)->findAll(0, true),
        );

        self::assertSame([1, 3], $uids);
    }

    #[Test]
    public function theIdentifierIsTakenOnlyByAnotherDefaultLanguageDecision(): void
    {
        $validator = $this->get(DecisionValidator::class);

        self::assertSame([], $validator->validate(self::draft(1)), 'the decision itself, although its translation shares the identifier');

        $errors = $validator->validate(self::draft(0));
        self::assertSame(
            [['identifier', 'validation.identifier.taken', ['Contact routing']]],
            array_map(static fn(ValidationError $e): array => [$e->path, $e->labelKey, $e->arguments], $errors),
            'a new decision is refused, and told the default record, not its translation',
        );
    }

    #[Test]
    public function savingTheDecisionKeepsItsIdentifierAndItsTranslation(): void
    {
        $result = $this->get(DecisionWriter::class)->save(self::draft(1));

        self::assertSame([], $result['errors']);
        self::assertSame('routing', $this->row('tx_webconjev_decision', 1)['identifier'], 'no "-1" suffix');
        $translation = $this->row('tx_webconjev_decision', 2);
        self::assertSame('routing', $translation['identifier']);
        self::assertSame(1, (int)$translation['sys_language_uid']);
        self::assertSame(1, (int)$translation['l10n_parent']);
        self::assertSame(0, (int)$translation['deleted']);
    }

    #[Test]
    public function editingTheTranslationInTheRecordEditorIsNotAnIdentifierConflict(): void
    {
        $errors = $this->process(['tx_webconjev_decision' => [2 => [
            'title' => 'Kontakt-Routing, überarbeitet',
            // The record editor shows the identifier read-only; submitting it anyway changes nothing.
            'identifier' => 'routing',
        ]]]);

        self::assertSame([], $errors);
        $translation = $this->row('tx_webconjev_decision', 2);
        self::assertSame('Kontakt-Routing, überarbeitet', $translation['title']);
        self::assertSame('routing', $translation['identifier'], 'no "-1" suffix on the translation either');
        self::assertSame('routing', $this->row('tx_webconjev_decision', 1)['identifier']);
    }

    #[Test]
    public function renamingTheIdentifierCarriesItToTheTranslation(): void
    {
        $errors = $this->process(['tx_webconjev_decision' => [1 => ['identifier' => 'contact_routing']]]);

        self::assertSame([], $errors);
        self::assertSame('contact_routing', $this->row('tx_webconjev_decision', 1)['identifier']);
        self::assertSame('contact_routing', $this->row('tx_webconjev_decision', 2)['identifier']);
        self::assertSame(1, $this->get(DecisionRepository::class)->findByIdentifier('contact_routing', 1)?->uid);
    }

    #[Test]
    public function deletingTheDecisionTakesTheTranslationAlong(): void
    {
        self::assertSame([], $this->get(DecisionWriter::class)->delete(1));

        self::assertSame(1, (int)$this->row('tx_webconjev_decision', 2)['deleted']);
        self::assertNull($this->get(DecisionRepository::class)->findByIdentifier('routing', 1));
    }

    private static function draft(int $uid): DecisionDraft
    {
        return DecisionDraft::fromPayload([
            'uid' => $uid,
            'title' => 'Contact routing',
            'identifier' => 'routing',
            'questions' => [
                ['uid' => 1, 'name' => 'department', 'type' => 'choice', 'instructions' => 'Which department?', 'criteria' => [
                    ['uid' => 1, 'identifier' => 'sales', 'description' => 'Wants to buy', 'outcomeValue' => 'sales@example.com'],
                    ['uid' => 2, 'identifier' => 'support', 'description' => 'Something is broken', 'outcomeValue' => 'support@example.com'],
                ]],
                ['uid' => 3, 'name' => 'is_bug', 'type' => 'noul', 'instructions' => 'Is it broken?'],
            ],
        ]);
    }

    /**
     * A save the way the record editor sends it.
     *
     * @param array<string, array<int, array<string, mixed>>> $data
     *
     * @return list<string>
     */
    private function process(array $data): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        return array_values(array_map(Cast::string(...), $dataHandler->errorLog));
    }
}
