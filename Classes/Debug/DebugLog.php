<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

/**
 * Everything Jev was asked during this request, kept for the frontend debug panel.
 *
 * The listeners write to it unconditionally — a handful of references to objects that exist
 * anyway — and only {@see \Webconsulting\WebconJev\Middleware\DebugPanelMiddleware} decides,
 * from `plugin.tx_webconjev.settings.debug`, whether anybody sees it. One request is one PHP
 * process here, so a shared service is request-scoped; the middleware clears it all the same.
 */
final class DebugLog
{
    /** @var array<string, DecisionTrace> */
    private array $traces = [];

    /** @var list<string> */
    private array $notes = [];

    /**
     * The trace for $key, created from $trace the first time. powermail_cond runs its rules in
     * loops until the result is stable, so the same decision is reported more than once.
     */
    public function trace(string $key, DecisionTrace $trace): DecisionTrace
    {
        return $this->traces[$key] ??= $trace;
    }

    public function find(string $key): ?DecisionTrace
    {
        return $this->traces[$key] ?? null;
    }

    /**
     * Something that went wrong before Jev could be asked: a rule without a decision, a decision
     * that was deleted. Those never reach the run log, so they have to be said here.
     */
    public function note(string $message): void
    {
        if (!in_array($message, $this->notes, true)) {
            $this->notes[] = $message;
        }
    }

    /**
     * @return list<DecisionTrace>
     */
    public function traces(): array
    {
        return array_values($this->traces);
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function isEmpty(): bool
    {
        return $this->traces === [] && $this->notes === [];
    }

    public function clear(): void
    {
        $this->traces = [];
        $this->notes = [];
    }
}
