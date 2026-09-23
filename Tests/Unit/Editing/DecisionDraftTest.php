<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Editing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Editing\Dto\DecisionDraft;

/**
 * What the editor sends arrives as loosely typed JSON. The draft keeps what somebody typed where
 * a validator has to judge it, and becomes a runnable decision only through toDecision().
 */
final class DecisionDraftTest extends TestCase
{
    #[Test]
    public function aPayloadBecomesATrimmedDraft(): void
    {
        $draft = DecisionDraft::fromPayload([
            'uid' => '7',
            'title' => '  Contact routing ',
            'identifier' => ' contact_routing ',
            'stateTemplate' => "Message: {{field.message}}\n\n",
            'confidenceThreshold' => '0,75',
            'cacheLifetime' => 120,
            'hidden' => true,
            'questions' => [
                [
                    'uid' => 3,
                    'name' => ' department ',
                    'type' => 'choice',
                    'instructions' => ' Which department? ',
                    'criteria' => [
                        ['uid' => 0, 'identifier' => 'sales', 'description' => 'Wants to buy', 'outcomeValue' => ' sales@example.com '],
                        'not an option',
                    ],
                ],
                'not a question',
            ],
        ]);

        self::assertSame(7, $draft->uid);
        self::assertSame('Contact routing', $draft->title);
        self::assertSame('contact_routing', $draft->identifier);
        self::assertSame('Message: {{field.message}}', $draft->stateTemplate, 'trailing blank lines are not part of the template');
        self::assertSame('0.75', $draft->confidenceThreshold, 'a decimal comma is read as a decimal point');
        self::assertSame(0.75, $draft->confidenceThreshold());
        self::assertSame(120, $draft->cacheLifetime());
        self::assertTrue($draft->hidden);
        self::assertCount(1, $draft->questions, 'entries that are not objects are dropped');
        self::assertSame('department', $draft->questions[0]->name);
        self::assertCount(1, $draft->questions[0]->criteria);
        self::assertSame('sales@example.com', $draft->questions[0]->criteria[0]->outcomeValue);
    }

    #[Test]
    public function missingNumbersFallBackToTheDefaultsAndAnUnknownTypeStaysVisible(): void
    {
        $draft = DecisionDraft::fromPayload([
            'title' => 'Triage',
            'questions' => [['name' => 'urgency', 'type' => 'ranking', 'instructions' => 'How urgent?']],
        ]);

        self::assertSame(0, $draft->uid);
        self::assertSame(Decision::DEFAULT_CONFIDENCE_THRESHOLD, $draft->confidenceThreshold());
        self::assertSame(Decision::CACHE_LIFETIME_INHERIT, $draft->cacheLifetime());
        self::assertSame('ranking', $draft->questions[0]->type, 'kept as typed, so the validator can name it');
        self::assertNull($draft->questions[0]->questionType());
    }

    #[Test]
    public function aDraftBecomesTheDecisionTheRunnerAsks(): void
    {
        $decision = DecisionDraft::fromPayload([
            'uid' => 4,
            'title' => 'Triage',
            'identifier' => 'triage',
            'confidenceThreshold' => '0.8',
            'defaultOutcome' => 'office@example.com',
            'questions' => [[
                'uid' => 9,
                'name' => 'urgency',
                'type' => 'score',
                'instructions' => 'How urgent is it?',
                'criteria' => [
                    ['description' => 'Can wait'],
                    ['description' => 'Today'],
                ],
            ]],
        ])->toDecision();

        self::assertSame(4, $decision->uid);
        self::assertSame(0.8, $decision->confidenceThreshold);
        self::assertSame(QuestionType::Score, $decision->questions[0]->type);
        self::assertSame(
            ['urgency' => ['type' => 'score', 'instructions' => 'How urgent is it?', 'criteria' => ['Can wait', 'Today']]],
            array_map(static fn(Question $question): array => $question->toPayload(), $decision->toClientQuestions()),
        );
    }
}
