<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Turns the context an integration collected into the state Jev evaluates.
 *
 * With no state template, the whole context goes over as a JSON object — which is what Jev is
 * built for, and what keeps a form with a new field working without anyone editing the decision.
 * A template is for narrowing that down: only what the question is actually about, which is what
 * the bill is calculated on.
 */
final readonly class StateBuilder
{
    /**
     * @param array<string, mixed> $context
     *
     * @return string|array<string, mixed>
     */
    public function build(Decision $decision, array $context): string|array
    {
        $template = trim($decision->stateTemplate);
        if ($template === '') {
            return $this->prune($context);
        }

        return $this->render($template, $context);
    }

    /**
     * Replace {{dotted.path}} with the value from the context; an unknown path renders empty.
     *
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context): string
    {
        /** @var string $rendered */
        $rendered = preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/',
            fn(array $matches): string => $this->stringify($this->lookup($matches[1], $context)),
            $template,
        );

        return $rendered;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function lookup(string $path, array $context): mixed
    {
        $value = $context;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return $value === true ? 'yes' : ($value === false ? 'no' : '');
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string)$value;
        }
        if (is_array($value)) {
            return implode(', ', array_map($this->stringify(...), $value));
        }

        return '';
    }

    /**
     * Drop empty values, so an untouched form field does not cost tokens or suggest an answer.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function prune(array $context): array
    {
        $pruned = [];
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = $this->prune(Cast::map($value));
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $pruned[$key] = $value;
        }

        return $pruned;
    }
}
