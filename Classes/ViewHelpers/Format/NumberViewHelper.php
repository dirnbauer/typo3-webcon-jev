<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\ViewHelpers\Format;

use NumberFormatter;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Webconsulting\WebconJev\Support\Cast;

/**
 * A number the way the backend user reads numbers: "0,6" for a German user, "0.6" for an English one.
 *
 * The module shows thresholds, costs in fractions of a cent and token counts in the thousands, and
 * core's format.number takes its separators as arguments rather than from the user's language.
 *
 *     <jev:format.number value="{run.costUsd}" style="usd" />
 *     <jev:format.number value="{decision.confidenceThreshold}" decimals="2" />
 *     <jev:format.number value="{answer.confidence}" style="percent" />
 */
final class NumberViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'mixed', 'The number; the tag content when omitted');
        $this->registerArgument('decimals', 'int', 'Fraction digits, at most', false, 0);
        $this->registerArgument('style', 'string', 'decimal, percent (0.42 becomes 42 %) or usd', false, 'decimal');
    }

    public function render(): string
    {
        $value = Cast::float($this->arguments['value'] ?? $this->renderChildren());
        $decimals = max(0, Cast::int($this->arguments['decimals'] ?? null));

        return match (Cast::string($this->arguments['style'] ?? null)) {
            'percent' => $this->format($value, NumberFormatter::PERCENT, $decimals),
            // Jev bills fractions of a cent, so a cost keeps the digits that make it non-zero.
            'usd' => $this->format($value, NumberFormatter::CURRENCY, $value > 0.0 && $value < 0.01 ? 6 : 4, 'USD'),
            default => $this->format($value, NumberFormatter::DECIMAL, $decimals),
        };
    }

    private function format(float $value, int $style, int $decimals, string $currency = ''): string
    {
        $formatter = new NumberFormatter($this->locale(), $style);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $style === NumberFormatter::CURRENCY ? 2 : 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        $formatted = $currency !== ''
            ? $formatter->formatCurrency($value, $currency)
            : $formatter->format($value);

        return $formatted !== false ? $formatted : (string)$value;
    }

    /**
     * The same locale core's format.date uses in the backend: the user's interface language.
     */
    private function locale(): string
    {
        $user = $GLOBALS['BE_USER'] ?? null;
        $language = $user instanceof BackendUserAuthentication ? Cast::trimmed($user->user['lang'] ?? null) : '';

        return $language !== '' && $language !== 'default' ? $language : 'en';
    }
}
