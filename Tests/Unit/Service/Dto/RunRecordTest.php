<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Service\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Service\Dto\RunOutcome;
use Webconsulting\WebconJev\Service\Dto\RunRecord;

/**
 * A run log row as the module shows it: a fallback wins over "cached", and the stored answers are
 * reduced to what fits in a table cell.
 */
final class RunRecordTest extends TestCase
{
    #[Test]
    public function aRowIsTypedForDisplay(): void
    {
        $record = RunRecord::fromRow([
            'uid' => '12',
            'crdate' => '1758600000',
            'decision' => '3',
            'decision_identifier' => 'routing',
            'context' => 'powermail_finisher',
            'origin' => 'form 5, mail 9',
            'model' => 'jev-1.13.0',
            'duration_ms' => '812.5',
            'input_tokens' => '356',
            'cost_usd' => '0.000014952',
            'from_cache' => '0',
            'is_fallback' => '0',
            'fallback_reason' => '',
            'answers' => json_encode([
                'department' => ['name' => 'department', 'type' => 'choice', 'value' => 'sales', 'confidence' => 0.91],
                'urgency' => ['name' => 'urgency', 'type' => 'score', 'value' => 1.456, 'confidence' => 0.7],
            ]),
        ]);

        self::assertSame(12, $record->uid);
        self::assertFalse($record->isAdHoc(), 'a stored decision');
        self::assertTrue(RunRecord::fromRow(['decision' => '0', 'decision_identifier' => 'my_extension.department'])->isAdHoc());
        self::assertSame(RunOutcome::Answered, $record->outcome);
        self::assertFalse($record->isFallback());
        self::assertSame(356, $record->inputTokens);
        self::assertSame(
            [
                ['name' => 'department', 'type' => 'choice', 'value' => 'sales', 'confidence' => 0.91],
                ['name' => 'urgency', 'type' => 'score', 'value' => '1.46', 'confidence' => 0.7],
            ],
            $record->answers,
        );
    }

    #[Test]
    public function aFallbackIsAFallbackEvenWhenTheRowSaysCached(): void
    {
        $record = RunRecord::fromRow(['is_fallback' => 1, 'from_cache' => 1, 'answers' => 'not json']);

        self::assertSame(RunOutcome::Fallback, $record->outcome);
        self::assertTrue($record->isFallback());
        self::assertFalse($record->isCached());
        self::assertSame([], $record->answers);
    }
}
