<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Service;

use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\DecisionResult;
use Webconsulting\WebconJev\Domain\Model\Decision;

/**
 * What a decision came to, and whether it is certain enough to act on.
 */
final readonly class DecisionOutcome
{
    public function __construct(
        public Decision $decision,
        public DecisionResult $result,
    ) {}

    public function answer(string $question): ?Answer
    {
        return $this->result->get($question);
    }

    /**
     * The answer, but only if it clears the decision's confidence threshold. Below it, the caller
     * is meant to use the default and let a human look at the submission.
     */
    public function confidentAnswer(string $question): ?Answer
    {
        $answer = $this->answer($question);

        return $answer?->isConfidentEnough($this->decision->confidenceThreshold) === true ? $answer : null;
    }

    /**
     * What the winning option means to the integration — a receiver address, a queue name, a
     * label. Falls back to the decision's default outcome when the answer is missing, not a
     * choice, or not certain enough.
     */
    public function outcomeFor(string $question): string
    {
        $answer = $this->confidentAnswer($question);
        $choice = is_string($answer?->choice) ? $answer->choice : null;

        if ($choice !== null) {
            $outcome = $this->decision->question($question)?->outcomeValueFor($choice);
            if ($outcome !== null) {
                return $outcome;
            }
        }

        return $this->decision->defaultOutcome;
    }

    public function isFallback(): bool
    {
        return $this->result->isFallback;
    }

    /**
     * True when nothing was decided with enough certainty — either Jev could not answer, or every
     * answer came back below the threshold.
     */
    public function needsHumanReview(): bool
    {
        if ($this->isFallback()) {
            return true;
        }

        foreach ($this->result->answers as $answer) {
            if ($answer->isConfidentEnough($this->decision->confidenceThreshold)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A short, human-readable account of what happened — for the run log and mail templates.
     */
    public function summary(): string
    {
        if ($this->isFallback()) {
            return sprintf('fallback (%s)', $this->result->fallbackReason ?? 'unknown');
        }

        $parts = [];
        foreach ($this->result->answers as $name => $answer) {
            $value = $answer->value();
            $parts[] = sprintf(
                '%s=%s (%.2f)',
                $name,
                is_float($value) ? number_format($value, 2) : (string)$value,
                $answer->confidence,
            );
        }

        return $parts === [] ? 'no answers' : implode(', ', $parts);
    }
}
