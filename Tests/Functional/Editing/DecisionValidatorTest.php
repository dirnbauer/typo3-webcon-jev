<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Functional\Editing;

use PHPUnit\Framework\Attributes\Test;
use Webconsulting\WebconJev\Editing\DecisionValidator;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;
use Webconsulting\WebconJev\Editing\Dto\ValidationError;
use Webconsulting\WebconJev\Tests\Functional\AbstractJevTestCase;

/**
 * Each rule is one the API or an integration relies on, caught where an editor can still act on
 * it — and named by the path of the field it belongs to.
 */
final class DecisionValidatorTest extends AbstractJevTestCase
{
    private DecisionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/decisions.csv');
        $this->validator = $this->get(DecisionValidator::class);
    }

    #[Test]
    public function aCompleteDecisionPasses(): void
    {
        self::assertSame([], $this->validator->validate(self::draft()));
    }

    #[Test]
    public function theDecisionsOwnFieldsAreChecked(): void
    {
        $errors = $this->validator->validate(self::draft([
            'title' => '',
            'identifier' => 'Contact Routing',
            'confidenceThreshold' => '1.5',
            'cacheLifetime' => '-2',
            'model' => 'jev latest',
        ]));

        self::assertSame([
            ['title', 'validation.required'],
            ['identifier', 'validation.identifier.format'],
            ['confidenceThreshold', 'validation.threshold.range'],
            ['cacheLifetime', 'validation.cacheLifetime.range'],
            ['model', 'validation.model.format'],
        ], self::summary($errors));
    }

    #[Test]
    public function anIdentifierAnotherDecisionUsesIsRefusedAndNamesThatDecision(): void
    {
        $errors = $this->validator->validate(self::draft(['identifier' => 'routing']));

        self::assertSame([['identifier', 'validation.identifier.taken']], self::summary($errors));
        self::assertSame(['Contact routing'], $errors[0]->arguments);

        self::assertSame([], $this->validator->validate(self::draft(['uid' => 1, 'identifier' => 'routing'])), 'its own identifier');
    }

    #[Test]
    public function questionsAreCheckedTheWayTheApiWouldCheckThem(): void
    {
        $errors = $this->validator->validate(self::draft(['questions' => [
            ['name' => 'department', 'type' => 'choice', 'instructions' => 'Which?', 'criteria' => [
                ['identifier' => 'sales', 'description' => 'Buying'],
            ]],
            ['name' => 'department', 'type' => 'ranking', 'instructions' => ''],
            ['name' => 'urgency level', 'type' => 'score', 'instructions' => 'How urgent?', 'criteria' => [
                ['description' => 'Can wait'],
            ]],
            ['name' => 'product', 'type' => 'choice', 'instructions' => 'Which product?', 'criteria' => [
                ['identifier' => 'shop', 'description' => 'The shop'],
                ['identifier' => 'shop', 'description' => ''],
                ['identifier' => '', 'description' => 'The app'],
            ]],
            ['name' => 'angry', 'type' => 'noul', 'instructions' => 'Is the writer angry?'],
        ]]));

        self::assertSame([
            ['questions.0.name', 'validation.question.name.duplicate'],
            ['questions.0.criteria', 'validation.criteria.choiceMinimum'],
            ['questions.1.name', 'validation.question.name.duplicate'],
            ['questions.1.type', 'validation.question.type.invalid'],
            ['questions.1.instructions', 'validation.required'],
            ['questions.2.name', 'validation.name.format'],
            ['questions.2.criteria', 'validation.criteria.scoreMinimum'],
            ['questions.3.criteria.0.identifier', 'validation.criterion.identifier.duplicate'],
            ['questions.3.criteria.1.identifier', 'validation.criterion.identifier.duplicate'],
            ['questions.3.criteria.1.description', 'validation.required'],
            ['questions.3.criteria.2.identifier', 'validation.required'],
        ], self::summary($errors), 'a noul needs no options, and a score needs no ids');
    }

    #[Test]
    public function tryingNeedsAQuestionButNotATitle(): void
    {
        self::assertSame(
            [['questions', 'validation.questions.none']],
            self::summary($this->validator->validateForTrying(self::draft(['questions' => []]))),
        );
        self::assertSame(
            [],
            $this->validator->validateForTrying(self::draft(['title' => '', 'identifier' => 'routing'])),
            'a missing title or a taken identifier does not stop anybody trying the wording',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function draft(array $overrides = []): DecisionDraft
    {
        return DecisionDraft::fromPayload($overrides + [
            'uid' => 0,
            'title' => 'Triage',
            'identifier' => 'triage',
            'confidenceThreshold' => '0.6',
            'cacheLifetime' => '-1',
            'questions' => [[
                'name' => 'department',
                'type' => 'choice',
                'instructions' => 'Which department?',
                'criteria' => [
                    ['identifier' => 'sales', 'description' => 'Wants to buy', 'outcomeValue' => 'sales@example.com'],
                    ['identifier' => 'support', 'description' => 'Something is broken'],
                ],
            ]],
        ]);
    }

    /**
     * @param list<ValidationError> $errors
     *
     * @return list<array{string, string}>
     */
    private static function summary(array $errors): array
    {
        return array_map(static fn(ValidationError $error): array => [$error->path, $error->labelKey], $errors);
    }
}
