<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use Webconsulting\WebconJev\Service\DecisionOutcome;

/**
 * Carries the routing outcome from the moment the submission is complete to the moment powermail
 * asks who the mail is for.
 *
 * Those are two different events, several objects apart, and powermail hands neither of them the
 * other's data. The store is the shortest honest path between them: one shared service, written
 * once per submission, read once.
 */
final class RoutingDecisionStore
{
    private ?DecisionOutcome $outcome = null;

    /** @var list<string> */
    private array $receivers = [];

    /**
     * @param list<string> $receivers
     */
    public function remember(DecisionOutcome $outcome, array $receivers): void
    {
        $this->outcome = $outcome;
        $this->receivers = array_values(array_filter($receivers, static fn(string $r): bool => trim($r) !== ''));
    }

    public function forget(): void
    {
        $this->outcome = null;
        $this->receivers = [];
    }

    public function hasReceivers(): bool
    {
        return $this->receivers !== [];
    }

    /**
     * @return list<string>
     */
    public function receivers(): array
    {
        return $this->receivers;
    }

    public function outcome(): ?DecisionOutcome
    {
        return $this->outcome;
    }
}
