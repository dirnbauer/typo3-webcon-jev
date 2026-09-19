<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Client\Dto\Question;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Exception\InvalidQuestionException;

/**
 * A question that cannot be answered is refused before it costs a call.
 */
final class QuestionTest extends TestCase
{
    #[Test]
    public function aChoiceSendsItsOptionsAsAMap(): void
    {
        $question = new Question('dept', QuestionType::Choice, 'Which?', ['sales' => 'Buying', 'support' => 'Broken']);

        self::assertSame([
            'type' => 'choice',
            'instructions' => 'Which?',
            'criteria' => ['sales' => 'Buying', 'support' => 'Broken'],
        ], $question->toPayload());
    }

    #[Test]
    public function aScoreSendsItsLevelsAsAnOrderedListWhateverTheKeys(): void
    {
        $question = new Question('sev', QuestionType::Score, 'How bad?', ['x' => 'none', 'y' => 'some', 'z' => 'all']);

        $payload = $question->toPayload();

        self::assertArrayHasKey('criteria', $payload);
        self::assertSame(['none', 'some', 'all'], $payload['criteria']);
    }

    #[Test]
    public function aNoulNeedsNoCriteriaAndThenSendsNone(): void
    {
        $question = new Question('bug', QuestionType::Noul, 'Is it broken?');

        self::assertArrayNotHasKey('criteria', $question->toPayload());
    }

    #[Test]
    public function aChoiceWithOneOptionIsNotAChoice(): void
    {
        $this->expectException(InvalidQuestionException::class);
        new Question('dept', QuestionType::Choice, 'Which?', ['only' => 'one']);
    }

    #[Test]
    public function aScoreWithOneLevelIsNotAScale(): void
    {
        $this->expectException(InvalidQuestionException::class);
        new Question('sev', QuestionType::Score, 'How bad?', ['one']);
    }

    #[Test]
    public function aNameAndInstructionsAreRequired(): void
    {
        $this->expectException(InvalidQuestionException::class);
        new Question('', QuestionType::Noul, 'x');
    }

    #[Test]
    public function emptyInstructionsAreRefused(): void
    {
        $this->expectException(InvalidQuestionException::class);
        new Question('q', QuestionType::Noul, '');
    }
}
