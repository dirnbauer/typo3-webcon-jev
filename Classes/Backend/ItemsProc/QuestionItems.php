<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Backend\ItemsProc;

use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Domain\Model\Criterion;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Domain\Model\DecisionQuestion;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Powermail\JevOperator;

/**
 * Fills the question and outcome selects with what the chosen decision actually asks, so an
 * editor picks from real options instead of retyping a question name and finding out at runtime.
 */
final readonly class QuestionItems
{
    public function __construct(private DecisionRepository $decisions) {}

    /**
     * Questions of the decision a powermail_cond rule points at, narrowed to the type its
     * operator can read.
     *
     * @param array{items: list<array{label: string, value: mixed}>, row: array<string, mixed>} $parameters
     */
    public function forRule(array &$parameters): void
    {
        $decision = $this->decision($parameters['row']['tx_webconjev_decision'] ?? null);
        if ($decision === null) {
            return;
        }

        $operator = JevOperator::tryFrom((int)$this->scalar($parameters['row']['ops'] ?? 0));
        foreach ($decision->questions as $question) {
            if ($operator !== null && $question->type !== $operator->expects()) {
                continue;
            }
            $parameters['items'][] = [
                'label' => $this->questionLabel($question),
                'value' => $question->name,
            ];
        }
    }

    /**
     * The options of the question a rule compares against.
     *
     * @param array{items: list<array{label: string, value: mixed}>, row: array<string, mixed>} $parameters
     */
    public function outcomesForRule(array &$parameters): void
    {
        $decision = $this->decision($parameters['row']['tx_webconjev_decision'] ?? null);
        $name = (string)$this->scalar($parameters['row']['tx_webconjev_question'] ?? '');
        $question = $decision?->question($name);
        if ($question === null) {
            return;
        }

        foreach ($question->criteria as $criterion) {
            $parameters['items'][] = [
                'label' => $this->criterionLabel($criterion),
                'value' => $criterion->identifier,
            ];
        }
    }

    /**
     * Choice questions of the decision a powermail form routes through — only a choice names a
     * receiver.
     *
     * @param array{items: list<array{label: string, value: mixed}>, row: array<string, mixed>} $parameters
     */
    public function forForm(array &$parameters): void
    {
        $decision = $this->decision($parameters['row']['tx_webconjev_routing_decision'] ?? null);
        if ($decision === null) {
            return;
        }

        foreach ($decision->questions as $question) {
            if ($question->type !== QuestionType::Choice) {
                continue;
            }
            $parameters['items'][] = [
                'label' => $this->questionLabel($question),
                'value' => $question->name,
            ];
        }
    }

    private function decision(mixed $value): ?Decision
    {
        $uid = (int)$this->scalar($value);

        return $uid > 0 ? $this->decisions->findByUid($uid, 0, true) : null;
    }

    /**
     * A FormEngine row value can arrive as a single value or as a one-element array.
     */
    private function scalar(mixed $value): string|int
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return is_scalar($value) ? (is_int($value) ? $value : (string)$value) : '';
    }

    private function questionLabel(DecisionQuestion $question): string
    {
        $instructions = $question->instructions;
        if (mb_strlen($instructions) > 60) {
            $instructions = mb_substr($instructions, 0, 57) . '…';
        }

        return sprintf('%s (%s) — %s', $question->name, $question->type->value, $instructions);
    }

    private function criterionLabel(Criterion $criterion): string
    {
        $description = $criterion->description;
        if (mb_strlen($description) > 60) {
            $description = mb_substr($description, 0, 57) . '…';
        }

        return $criterion->outcomeValue !== ''
            ? sprintf('%s — %s → %s', $criterion->identifier, $description, $criterion->outcomeValue)
            : sprintf('%s — %s', $criterion->identifier, $description);
    }
}
