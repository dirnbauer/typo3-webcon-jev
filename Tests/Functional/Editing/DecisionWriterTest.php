<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Editing;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Editing\DecisionWriter;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * Saving through the DataHandler, the way the module's editor does.
 *
 * The DataHandler does not delete an inline child that is merely left out of its parent's list, so
 * before 0.2.0 a question removed in the module came back on the next page load and went on being
 * asked. These tests pin down that what the editor removes is gone.
 */
final class DecisionWriterTest extends AbstractJevTestCase
{
    private DecisionWriter $writer;

    private DecisionRepository $decisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdministrator();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->writer = $this->get(DecisionWriter::class);
        $this->decisions = $this->get(DecisionRepository::class);
    }

    #[Test]
    public function aNewDecisionIsCreatedWithItsQuestionsAndOptionsInOrder(): void
    {
        $result = $this->writer->save(DecisionDraft::fromPayload([
            'title' => 'Support triage',
            'identifier' => 'support_triage',
            'confidenceThreshold' => '0.7',
            'defaultOutcome' => 'support@example.com',
            'questions' => [
                [
                    'name' => 'product',
                    'type' => 'choice',
                    'instructions' => 'Which product is it about?',
                    'criteria' => [
                        ['identifier' => 'shop', 'description' => 'The online shop', 'outcomeValue' => 'shop@example.com'],
                        ['identifier' => 'app', 'description' => 'The mobile app', 'outcomeValue' => 'app@example.com'],
                    ],
                ],
                ['name' => 'angry', 'type' => 'noul', 'instructions' => 'Is the writer angry?'],
            ],
        ]));

        self::assertSame([], $result['errors']);
        self::assertGreaterThan(4, $result['uid']);

        $decision = $this->decisions->findByUid($result['uid'], 0, true);
        self::assertNotNull($decision);
        self::assertSame('Support triage', $decision->title);
        self::assertSame('support_triage', $decision->identifier);
        self::assertSame(0.7, $decision->confidenceThreshold);
        self::assertSame(['product', 'angry'], array_map(static fn(DecisionQuestion $q): string => $q->name, $decision->questions));
        self::assertSame(['shop', 'app'], array_map(static fn(Criterion $c): string => $c->identifier, $decision->questions[0]->criteria));
        self::assertSame('app@example.com', $decision->questions[0]->outcomeValueFor('app'));
    }

    #[Test]
    public function whatTheDraftNoLongerListsIsDeletedWithItsTranslations(): void
    {
        // Decision 1 has "department" (options sales, support) and "is_bug"; the draft keeps only
        // "department" with "sales", and reorders nothing.
        $result = $this->writer->save(DecisionDraft::fromPayload([
            'uid' => 1,
            'title' => 'Contact routing',
            'identifier' => 'routing',
            'questions' => [[
                'uid' => 1,
                'name' => 'department',
                'type' => 'choice',
                'instructions' => 'Which department?',
                'criteria' => [
                    ['uid' => 1, 'identifier' => 'sales', 'description' => 'Wants to buy', 'outcomeValue' => 'sales@example.com'],
                    ['uid' => 0, 'identifier' => 'press', 'description' => 'Writes for a newspaper', 'outcomeValue' => 'press@example.com'],
                ],
            ]],
        ]));

        self::assertSame([], $result['errors']);
        self::assertSame(1, $result['uid']);

        $decision = $this->decisions->findByUid(1, 0, true);
        self::assertNotNull($decision);
        self::assertSame(['department'], array_map(static fn(DecisionQuestion $q): string => $q->name, $decision->questions));
        self::assertSame(['sales', 'press'], array_map(static fn(Criterion $c): string => $c->identifier, $decision->questions[0]->criteria));

        self::assertSame(1, (int)$this->row('tx_webconjev_question', 3)['deleted'], 'the removed question');
        self::assertSame(1, (int)$this->row('tx_webconjev_question', 4)['deleted'], 'its translation');
        self::assertSame(1, (int)$this->row('tx_webconjev_criterion', 2)['deleted'], 'the removed option');
        self::assertSame(1, (int)$this->row('tx_webconjev_criterion', 4)['deleted'], 'its translation');
        self::assertSame(0, (int)$this->row('tx_webconjev_criterion', 1)['deleted'], 'the option that stayed');
    }

    #[Test]
    public function aUidBelongingToAnotherDecisionIsTreatedAsNew(): void
    {
        $result = $this->writer->save(DecisionDraft::fromPayload([
            'uid' => 3,
            'title' => 'Hidden',
            'identifier' => 'hidden_one',
            'hidden' => true,
            'questions' => [['uid' => 1, 'name' => 'borrowed', 'type' => 'noul', 'instructions' => 'Is it borrowed?']],
        ]));

        self::assertSame([], $result['errors']);
        $question = $this->row('tx_webconjev_question', 1);
        self::assertSame(1, (int)$question['decision'], 'question 1 still belongs to decision 1');
        self::assertSame('department', $question['name'], 'and was not overwritten');

        $hidden = $this->decisions->findByUid(3, 0, true);
        self::assertNotNull($hidden);
        self::assertTrue($hidden->hidden);
        self::assertSame(['borrowed'], array_map(static fn(DecisionQuestion $q): string => $q->name, $hidden->questions));
        self::assertNotSame(1, $hidden->questions[0]->uid);
    }

    #[Test]
    public function aDecisionThatIsGoneIsNotRecreated(): void
    {
        $result = $this->writer->save(DecisionDraft::fromPayload(['uid' => 4, 'title' => 'Deleted']));

        self::assertSame(0, $result['uid']);
        self::assertNotSame([], $result['errors']);
    }

    #[Test]
    public function deletingADecisionTakesItsQuestionsAndOptionsAlong(): void
    {
        self::assertSame([], $this->writer->delete(1));

        self::assertSame(1, (int)$this->row('tx_webconjev_decision', 1)['deleted']);
        self::assertSame(1, (int)$this->row('tx_webconjev_question', 1)['deleted']);
        self::assertSame(1, (int)$this->row('tx_webconjev_question', 3)['deleted']);
        self::assertSame(1, (int)$this->row('tx_webconjev_criterion', 1)['deleted']);
        self::assertNull($this->decisions->findByUid(1, 0, true));
    }
}
