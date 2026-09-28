<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

/**
 * Carries the routing outcome from the moment the submission is complete to the moments powermail
 * asks who the mail is for and renders what the visitor sees.
 *
 * Those are different events, several objects apart, and powermail hands none of them the others'
 * data. The store is the shortest honest path between them: one shared service, written once per
 * submission.
 */
final class RoutingDecisionStore
{
    private ?RoutingDecision $decision = null;

    public function remember(RoutingDecision $decision): void
    {
        $this->decision = $decision;
    }

    public function forget(): void
    {
        $this->decision = null;
    }

    public function decision(): ?RoutingDecision
    {
        return $this->decision;
    }
}
