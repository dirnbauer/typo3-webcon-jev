<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

/**
 * What a rule says about the decision it consults.
 */
final readonly class RuleConfiguration
{
    public function __construct(
        public int $decisionUid,
        public string $questionName,
        public string $expected,
        public float $threshold,
    ) {}

    public function isComplete(): bool
    {
        return $this->decisionUid > 0 && $this->questionName !== '';
    }

    /**
     * The value the operator compares against: an option id for a choice, the threshold otherwise.
     */
    public function comparisonValue(JevOperator $operator): string
    {
        return $operator->comparesNumerically() ? (string)$this->threshold : $this->expected;
    }
}
