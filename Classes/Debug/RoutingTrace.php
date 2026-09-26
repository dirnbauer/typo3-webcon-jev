<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

/**
 * Where a submission was sent, and why.
 */
final readonly class RoutingTrace
{
    /**
     * @param string       $outcomeValue What the decision resolved to, before it was read as addresses
     * @param list<string> $receivers    The addresses that replaced the form's receiver; empty when none did
     * @param bool         $usedDefault  True when the answer was missing, unsure or unmapped
     */
    public function __construct(
        public string $question,
        public string $outcomeValue,
        public array $receivers,
        public bool $usedDefault,
    ) {}
}
