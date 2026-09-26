<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

use Webconsulting\WebconJev\Service\DecisionOutcome;

/**
 * One decision as one form asked it: what it read, what Jev answered, and what that did.
 */
final class DecisionTrace
{
    public const string CONDITIONS = 'conditions';
    public const string ROUTING = 'routing';

    /** @var array<int, RuleTrace> */
    private array $rules = [];

    private ?RoutingTrace $routing = null;

    /**
     * @param array<string, mixed> $context What the integration handed the decision, before the state template
     */
    public function __construct(
        public readonly string $kind,
        public readonly DecisionOutcome $outcome,
        public readonly array $context,
    ) {}

    /**
     * Keyed by rule, so a rule powermail_cond evaluates in two loops is listed once, as it ended.
     */
    public function addRule(RuleTrace $rule): void
    {
        $this->rules[$rule->ruleUid] = $rule;
    }

    /**
     * @return list<RuleTrace>
     */
    public function rules(): array
    {
        return array_values($this->rules);
    }

    public function setRouting(RoutingTrace $routing): void
    {
        $this->routing = $routing;
    }

    public function routing(): ?RoutingTrace
    {
        return $this->routing;
    }
}
