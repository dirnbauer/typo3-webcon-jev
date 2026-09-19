<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Domain;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * Decisions are ordinary translatable records. The default language carries the structure and a
 * translation overrides the wording — and a field left empty in the translation keeps the default
 * text, so a half-translated decision still asks a complete question.
 */
final class DecisionRepositoryTest extends AbstractJevTestCase
{
    private DecisionRepository $decisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->decisions = $this->get(DecisionRepository::class);
    }

    #[Test]
    public function theDefaultLanguageLoadsStructureAndWording(): void
    {
        $decision = $this->decisions->findByIdentifier('routing');

        self::assertNotNull($decision);
        self::assertSame(1, $decision->uid);
        self::assertSame('Contact routing', $decision->title);
        self::assertSame('office@example.com', $decision->defaultOutcome);
        self::assertSame(['department', 'is_bug'], array_map(static fn($q) => $q->name, $decision->questions));
        self::assertSame(QuestionType::Choice, $decision->questions[0]->type);
        self::assertSame('Which department?', $decision->questions[0]->instructions);
        self::assertSame('sales@example.com', $decision->questions[0]->outcomeValueFor('sales'));
    }

    #[Test]
    public function aTranslationOverridesTheWordingAndKeepsTheStructure(): void
    {
        $decision = $this->decisions->findByIdentifier('routing', languageId: 1);

        self::assertNotNull($decision);
        self::assertSame(1, $decision->uid, 'the uid is the default record: conditions refer to it');
        self::assertSame(1, $decision->languageId);
        self::assertSame('Kontakt-Routing', $decision->title);
        self::assertSame('Welche Abteilung?', $decision->questions[0]->instructions);
        self::assertSame('Will kaufen', $decision->questions[0]->criteria[0]->description);
        self::assertSame('sales', $decision->questions[0]->criteria[0]->identifier, 'identifiers are never translated');
    }

    #[Test]
    public function anEmptyTranslatedFieldKeepsTheDefaultText(): void
    {
        $decision = $this->decisions->findByIdentifier('routing', languageId: 1);

        self::assertNotNull($decision);
        self::assertSame('Message: {{field.message}}', $decision->stateTemplate, 'empty template in the translation');
        self::assertSame('office@example.com', $decision->defaultOutcome, 'empty default outcome in the translation');
        self::assertSame('Is it broken?', $decision->questions[1]->instructions, 'empty instructions in the translation');
        self::assertSame('Something is broken', $decision->questions[0]->criteria[1]->description, 'empty criterion');
        self::assertSame('sales@example.com', $decision->questions[0]->outcomeValueFor('sales'), 'empty outcome value');
    }

    #[Test]
    public function anUnknownLanguageFallsBackToTheDefault(): void
    {
        $decision = $this->decisions->findByIdentifier('routing', languageId: 7);

        self::assertNotNull($decision);
        self::assertSame('Contact routing', $decision->title);
    }

    #[Test]
    public function hiddenAndDeletedRecordsAreInvisibleUnlessAskedFor(): void
    {
        self::assertNull($this->decisions->findByIdentifier('hidden_one'));
        self::assertNull($this->decisions->findByIdentifier('deleted_one'));
        self::assertNull($this->decisions->findByUid(3));
        self::assertNotNull($this->decisions->findByUid(3, includeHidden: true), 'the module edits hidden ones');
        self::assertNull($this->decisions->findByUid(4, includeHidden: true), 'deleted stays deleted');

        self::assertSame([1], array_map(static fn($d) => $d->uid, $this->decisions->findAll()));
        self::assertSame([1, 3], array_map(static fn($d) => $d->uid, $this->decisions->findAll(includeHidden: true)));
    }

    #[Test]
    public function questionsBecomeAPayloadKeyedByName(): void
    {
        $decision = $this->decisions->findByIdentifier('routing');
        self::assertNotNull($decision);

        $questions = $decision->toClientQuestions();

        self::assertSame(['department', 'is_bug'], array_keys($questions));
        $department = $questions['department']->toPayload();
        self::assertArrayHasKey('criteria', $department);
        self::assertSame(['sales' => 'Wants to buy', 'support' => 'Something is broken'], $department['criteria']);
        self::assertArrayNotHasKey('criteria', $questions['is_bug']->toPayload());
    }
}
