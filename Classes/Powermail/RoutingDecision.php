<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use NumberFormatter;
use Webconsulting\WebconJev\Service\DecisionOutcome;

/**
 * Where one submission goes, and why: what routing decided for the mail being sent.
 */
final readonly class RoutingDecision
{
    /**
     * @param list<string> $receivers
     */
    public function __construct(
        public DecisionOutcome $outcome,
        public string $question,
        public array $receivers,
        public int $mailUid,
        public string $summary,
    ) {}

    /**
     * Which sentence tells the visitor what happened: Jev picked the receiver, was not sure
     * enough, could not answer at all, or picked an option without an address of its own.
     */
    public function messageKey(): string
    {
        return match (true) {
            $this->outcome->isFallback() => 'routing.fallback',
            $this->outcome->confidentAnswer($this->question) === null => 'routing.unsure',
            $this->outcome->usedDefaultFor($this->question) => 'routing.default',
            default => 'routing.sent',
        };
    }

    /**
     * How sure Jev was about the routing answer, as the visitor's locale writes a percentage.
     */
    public function confidence(string $locale): string
    {
        $confidence = $this->outcome->answer($this->question)->confidence ?? 0.0;
        if (class_exists(NumberFormatter::class)) {
            $formatted = (new NumberFormatter($locale, NumberFormatter::PERCENT))->format($confidence);
            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return round($confidence * 100) . ' %';
    }
}
