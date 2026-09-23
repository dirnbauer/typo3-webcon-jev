<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Service\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Service\Dto\RunLogFilter;
use Webconsulting\WebconJev\Service\Dto\RunOutcome;

/**
 * The run log's filter comes from a query string, so everything in it is untrusted text.
 */
final class RunLogFilterTest extends TestCase
{
    #[Test]
    public function aValidFilterIsReadAndWrittenBackTheSameWay(): void
    {
        $filter = RunLogFilter::fromArray(['decision' => '5', 'context' => 'powermail_cond', 'outcome' => 'fallback']);

        self::assertSame(5, $filter->decision);
        self::assertSame('powermail_cond', $filter->context);
        self::assertSame(RunOutcome::Fallback, $filter->outcome);
        self::assertTrue($filter->isActive());
        self::assertSame(['decision' => 5, 'context' => 'powermail_cond', 'outcome' => 'fallback'], $filter->toArray());
    }

    #[Test]
    public function anythingElseMeansNoConstraint(): void
    {
        $filter = RunLogFilter::fromArray(['decision' => '-3', 'context' => "x' OR 1=1 --", 'outcome' => 'everything']);

        self::assertSame(0, $filter->decision);
        self::assertSame('', $filter->context);
        self::assertNull($filter->outcome);
        self::assertFalse($filter->isActive());
        self::assertSame([], $filter->toArray());
    }
}
