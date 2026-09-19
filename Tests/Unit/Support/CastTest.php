<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Every value from JSON, a database row or a request arrives as mixed. The interesting cases are
 * the ones where PHP's own cast would have answered something nobody chose.
 */
final class CastTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function strings(): iterable
    {
        yield 'string passes through' => ['hello', 'hello'];
        yield 'int becomes its digits' => [42, '42'];
        yield 'float becomes its digits' => [1.5, '1.5'];
        yield 'null is the default' => [null, ''];
        yield 'bool is not a string' => [true, ''];
        yield 'array is not a string' => [['a'], ''];
    }

    #[Test]
    #[DataProvider('strings')]
    public function stringCoercesOnlyWhatIsReallyText(mixed $value, string $expected): void
    {
        self::assertSame($expected, Cast::string($value));
    }

    #[Test]
    public function trimmedFallsBackWhenOnlyWhitespaceIsLeft(): void
    {
        self::assertSame('x', Cast::trimmed('  x  '));
        self::assertSame('default', Cast::trimmed('   ', 'default'));
        self::assertSame('default', Cast::trimmed(null, 'default'));
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function ints(): iterable
    {
        yield 'int' => [7, 7];
        yield 'numeric string' => ['7', 7];
        yield 'float truncates' => [7.9, 7];
        yield 'non-numeric string is the default' => ['seven', 0];
        yield 'null is the default' => [null, 0];
        yield 'array is the default' => [[7], 0];
    }

    #[Test]
    #[DataProvider('ints')]
    public function intRefusesWhatIsNotANumber(mixed $value, int $expected): void
    {
        self::assertSame($expected, Cast::int($value));
    }

    #[Test]
    public function intAndFloatHonourAnExplicitDefault(): void
    {
        self::assertSame(-1, Cast::int('nope', -1));
        self::assertSame(0.6, Cast::float(null, 0.6));
        self::assertSame(0.25, Cast::float('0.25'));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function bools(): iterable
    {
        yield 'true' => [true, true];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string true' => ['true', true];
        yield 'string yes' => ['YES', true];
        yield 'string 0' => ['0', false];
        yield 'string no' => ['no', false];
        yield 'null is the default' => [null, false];
    }

    #[Test]
    #[DataProvider('bools')]
    public function boolReadsTheSpellingsTypo3Stores(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Cast::bool($value));
    }

    #[Test]
    public function mapStringifiesKeysAndDropsNonArrays(): void
    {
        self::assertSame(['1' => 'a', 'b' => 'c'], Cast::map([1 => 'a', 'b' => 'c']));
        self::assertSame([], Cast::map('not an array'));
        self::assertSame([], Cast::map(null));
    }

    #[Test]
    public function stringListStringifiesEveryEntry(): void
    {
        self::assertSame(['a', '2', ''], Cast::stringList(['a', 2, null]));
        self::assertSame([], Cast::stringList('x'));
    }

    #[Test]
    public function distributionKeepsAMapForAChoiceAndAListForAScore(): void
    {
        self::assertSame(['yes' => 0.9, 'no' => 0.1], Cast::distribution(['yes' => '0.9', 'no' => 0.1]));
        self::assertSame([0.2, 0.8], Cast::distribution([0.2, '0.8']));
        self::assertSame([], Cast::distribution('nope'));
    }
}
