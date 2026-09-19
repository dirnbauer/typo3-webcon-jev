<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Service\StateBuilder;

/**
 * What Jev gets to read: the whole context as JSON, or only what a template names.
 */
final class StateBuilderTest extends TestCase
{
    #[Test]
    public function withoutATemplateTheWholeContextGoesOverMinusWhatIsEmpty(): void
    {
        $decision = self::decision('');
        $context = [
            'form' => ['uid' => 5, 'title' => 'Contact'],
            'field' => ['message' => 'Hello', 'subject' => '', 'phone' => null, 'tags' => []],
        ];

        self::assertSame([
            'form' => ['uid' => 5, 'title' => 'Contact'],
            'field' => ['message' => 'Hello'],
        ], (new StateBuilder())->build($decision, $context));
    }

    #[Test]
    public function aTemplateRendersDottedPathsAndLeavesUnknownOnesEmpty(): void
    {
        $decision = self::decision("Subject: {{field.subject}}\nMessage: {{ field.message }}\nMissing: {{field.nope}}");
        $state = (new StateBuilder())->build($decision, ['field' => ['subject' => 'Invoice', 'message' => 'Twice']]);

        self::assertSame("Subject: Invoice\nMessage: Twice\nMissing: ", $state);
    }

    #[Test]
    public function renderedValuesAreTextNotStructure(): void
    {
        $builder = new StateBuilder();

        self::assertSame('a, b', $builder->render('{{x}}', ['x' => ['a', 'b']]));
        self::assertSame('yes', $builder->render('{{x}}', ['x' => true]));
        self::assertSame('no', $builder->render('{{x}}', ['x' => false]));
        self::assertSame('3', $builder->render('{{x}}', ['x' => 3]));
    }

    private static function decision(string $template): Decision
    {
        return new Decision(1, 'd', 'D', '', $template, '', 0.6, -1, '', []);
    }
}
