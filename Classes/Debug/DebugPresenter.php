<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Debug;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Domain\Model\Decision;
use Webconsulting\WebconJev\Powermail\JevOperator;
use Webconsulting\WebconJev\Service\StateBuilder;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Turns the debug log into plain arrays with every number already formatted, so the Fluid
 * template only has to lay them out.
 */
final readonly class DebugPresenter
{
    private const string RULE_TABLE = 'tx_powermailcond_domain_model_rule';
    private const string CONDITION_TABLE = 'tx_powermailcond_domain_model_condition';
    private const string FIELD_TABLE = 'tx_powermail_domain_model_field';

    public function __construct(
        private StateBuilder $stateBuilder,
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array{decisions: list<array<string, mixed>>, notes: list<string>}
     */
    public function present(DebugLog $log): array
    {
        $traces = $log->traces();
        $ruleUids = [];
        foreach ($traces as $trace) {
            foreach ($trace->rules() as $rule) {
                $ruleUids[] = $rule->ruleUid;
            }
        }
        $conditions = $this->conditionsFor($ruleUids);

        return [
            'decisions' => array_map(
                fn(DecisionTrace $trace): array => $this->decision($trace, $conditions),
                $traces,
            ),
            'notes' => $log->notes(),
        ];
    }

    /**
     * @param array<int, array{title: string, target: string, shows: bool}> $conditions
     *
     * @return array<string, mixed>
     */
    private function decision(DecisionTrace $trace, array $conditions): array
    {
        $outcome = $trace->outcome;
        $decision = $outcome->decision;
        $result = $outcome->result;

        return [
            'kind' => $trace->kind,
            'title' => $decision->title !== '' ? $decision->title : $decision->identifier,
            'identifier' => $decision->identifier !== '' ? $decision->identifier : 'ad-hoc',
            'model' => $result->model !== '' ? $result->model : ($decision->model !== '' ? $decision->model : 'jev-latest'),
            'status' => $result->isFallback ? 'fallback' : ($result->fromCache ? 'cache' : 'live'),
            'fallbackReason' => $result->fallbackReason ?? '',
            'duration' => $result->isFallback ? '' : number_format($result->durationMs, 0, '.', ' ') . ' ms',
            'tokens' => $result->usage->inputTokens,
            'cost' => $result->usage->inputTokens > 0 ? sprintf('$%.6f', $result->usage->costInUsd()) : '',
            'threshold' => self::decimal($decision->confidenceThreshold),
            'thresholdValue' => $decision->confidenceThreshold,
            'state' => $this->state($decision, $trace->context),
            'answers' => array_values(array_map(
                fn(Answer $answer): array => $this->answer($answer, $decision),
                $result->answers,
            )),
            'rules' => array_map(
                fn(RuleTrace $rule): array => $this->rule($rule, $decision, $conditions[$rule->ruleUid] ?? null),
                $trace->rules(),
            ),
            'routing' => $this->routing($trace->routing()),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function state(Decision $decision, array $context): string
    {
        $state = $this->stateBuilder->build($decision, $context);
        if (is_string($state)) {
            return $state;
        }

        return (string)json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(Answer $answer, Decision $decision): array
    {
        $criteria = [];
        foreach ($decision->question($answer->name)->criteria ?? [] as $index => $criterion) {
            $criteria[] = self::criterionLabel($index, $criterion->identifier, $criterion->description);
        }
        $confident = $answer->isConfidentEnough($decision->confidenceThreshold);

        return [
            'name' => $answer->name,
            'type' => $answer->type->value,
            'value' => $this->display($answer, $criteria),
            // A noul reports no confidence of its own; this one is derived from its distance to 0.5.
            'derived' => $answer->type === QuestionType::Noul,
            'confidence' => $answer->confidence,
            'confidencePercent' => self::percent($answer->confidence),
            'confident' => $confident,
            'options' => $this->options($answer, $criteria),
        ];
    }

    /**
     * @param list<string> $criteria
     */
    private function display(Answer $answer, array $criteria): string
    {
        $value = $answer->value();
        if ($value === null) {
            return '—';
        }
        if ($answer->type === QuestionType::Score && is_float($value)) {
            $nearest = $criteria[(int)round($value)] ?? null;

            return self::decimal($value) . ($nearest !== null ? ' ≈ ' . $nearest : '');
        }

        return is_float($value) ? self::decimal($value) : $value;
    }

    /**
     * The whole distribution, most likely first for a choice and in rubric order for a score.
     *
     * @param list<string> $criteria
     *
     * @return list<array{label: string, probability: float, percent: string, chosen: bool}>
     */
    private function options(Answer $answer, array $criteria): array
    {
        if ($answer->type === QuestionType::Noul || $answer->probabilities === []) {
            return [];
        }

        $options = [];
        foreach ($answer->probabilities as $key => $probability) {
            $label = is_int($key)
                ? ($criteria[$key] ?? $answer->legend[$key] ?? (string)$key)
                : $key;
            $options[] = [
                'label' => $label,
                'probability' => $probability,
                'percent' => self::percent($probability),
                'chosen' => $answer->type === QuestionType::Choice
                    ? $label === $answer->choice
                    : $key === (int)round((float)$answer->score),
            ];
        }

        if ($answer->type === QuestionType::Choice) {
            usort($options, static fn(array $a, array $b): int => $b['probability'] <=> $a['probability']);
        }

        return $options;
    }

    /**
     * @param array{title: string, target: string, shows: bool}|null $condition
     *
     * @return array<string, mixed>
     */
    private function rule(RuleTrace $rule, Decision $decision, ?array $condition): array
    {
        $noul = $rule->operator->expects() === QuestionType::Noul;
        $expected = $rule->operator->comparesNumerically()
            ? self::decimal(Cast::float($rule->expected))
            : $rule->expected;

        return [
            'uid' => $rule->ruleUid,
            'condition' => $condition['title'] ?? '',
            'target' => $condition['target'] ?? '',
            'shows' => $condition['shows'] ?? true,
            'test' => sprintf('%s %s %s', $rule->question, self::symbol($rule->operator), $expected),
            'answer' => $rule->answer instanceof Answer ? $this->display($rule->answer, []) : '—',
            'answered' => $rule->answer instanceof Answer && $rule->answer->value() !== null,
            // Choice and score act on the decision's threshold; a noul rule's own number is its gate.
            'gate' => $noul ? 'rule' : 'decision',
            'confidencePercent' => $rule->answer instanceof Answer ? self::percent($rule->answer->confidence) : '',
            'threshold' => self::decimal($decision->confidenceThreshold),
            'usable' => $rule->usable,
            'result' => $rule->result,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routing(?RoutingTrace $routing): ?array
    {
        if ($routing === null) {
            return null;
        }

        return [
            'question' => $routing->question,
            'outcome' => $routing->outcomeValue,
            'receivers' => $routing->receivers,
            'applied' => $routing->receivers !== [],
            'usedDefault' => $routing->usedDefault,
        ];
    }

    /**
     * The condition each rule belongs to and the field it shows or hides. A debug view only, so
     * it reads the rows directly rather than hydrating powermail_cond's models.
     *
     * @param list<int> $ruleUids
     *
     * @return array<int, array{title: string, target: string, shows: bool}>
     */
    private function conditionsFor(array $ruleUids): array
    {
        $ruleUids = array_values(array_unique(array_filter($ruleUids, static fn(int $uid): bool => $uid > 0)));
        if ($ruleUids === []) {
            return [];
        }

        // Short aliases: CONDITION is a reserved word in MariaDB.
        $query = $this->connectionPool->getQueryBuilderForTable(self::RULE_TABLE);
        $rows = $query
            ->select('r.uid AS rule_uid', 'c.title', 'c.actions', 'f.title AS field_title')
            ->from(self::RULE_TABLE, 'r')
            ->join('r', self::CONDITION_TABLE, 'c', $query->expr()->eq('c.uid', $query->quoteIdentifier('r.conditions')))
            ->leftJoin('c', self::FIELD_TABLE, 'f', $query->expr()->eq('f.uid', $query->quoteIdentifier('c.target_field')))
            ->where($query->expr()->in(
                'r.uid',
                $query->createNamedParameter($ruleUids, Connection::PARAM_INT_ARRAY),
            ))
            ->executeQuery()
            ->fetchAllAssociative();

        $conditions = [];
        foreach ($rows as $row) {
            $conditions[Cast::int($row['rule_uid'] ?? null)] = [
                'title' => Cast::trimmed($row['title'] ?? null),
                'target' => Cast::trimmed($row['field_title'] ?? null),
                // powermail_cond: 0 hides the target when the rules hold, 1 shows it.
                'shows' => Cast::int($row['actions'] ?? null) === 1,
            ];
        }

        return $conditions;
    }

    /**
     * A choice option is known by its id. A score step has none — Jev ranks steps by position — so
     * it is shown as its position and the start of its description ("3 · Completely").
     */
    private static function criterionLabel(int $index, string $identifier, string $description): string
    {
        if ($identifier !== '') {
            return $identifier;
        }
        $short = trim(explode(':', $description, 2)[0]);
        if (mb_strlen($short) > 28) {
            $short = rtrim(mb_substr($short, 0, 27)) . '…';
        }

        return $short !== '' ? sprintf('%d · %s', $index, $short) : (string)$index;
    }

    private static function symbol(JevOperator $operator): string
    {
        return match ($operator) {
            JevOperator::ChoiceIs => '=',
            JevOperator::ChoiceIsNot => '≠',
            JevOperator::ScoreAtLeast, JevOperator::NoulAbove => '≥',
            JevOperator::ScoreBelow, JevOperator::NoulBelow => '<',
        };
    }

    private static function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function percent(float $value): string
    {
        return number_format($value * 100, 0) . ' %';
    }
}
