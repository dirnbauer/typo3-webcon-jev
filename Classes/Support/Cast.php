<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Support;

/**
 * Coercion for values that arrive as `mixed`: decoded JSON, database rows, request payloads.
 *
 * A plain `(int)$row['uid']` says nothing about what happens when the value is an array or null —
 * PHP has an answer, but not one anybody chose. These say it: a value of the wrong shape becomes
 * the default rather than a warning and a zero.
 */
final class Cast
{
    public static function string(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string)$value : $default;
    }

    public static function trimmed(mixed $value, string $default = ''): string
    {
        $string = trim(self::string($value, $default));

        return $string === '' ? $default : $string;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int)$value;
        }

        return is_string($value) && is_numeric($value) ? (int)$value : $default;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float)$value;
        }

        return is_string($value) && is_numeric($value) ? (float)$value : $default;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        return is_string($value) ? in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) : $default;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * A JSON object, with its keys as strings.
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $entry) {
            $map[(string)$key] = $entry;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $entry): string => self::string($entry), $value));
    }

    /**
     * A probability distribution, which Jev sends as a map for a choice and a list for a score.
     *
     * @return array<string, float>|list<float>
     */
    public static function distribution(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_map(static fn(mixed $entry): float => self::float($entry), $value));
        }

        $map = [];
        foreach ($value as $key => $entry) {
            $map[(string)$key] = self::float($entry);
        }

        return $map;
    }
}
