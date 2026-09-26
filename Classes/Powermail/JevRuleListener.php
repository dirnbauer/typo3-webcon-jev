<?php

declare(strict_types=1);

namespace Webconsulting\WebconJev\Powermail;

use In2code\PowermailCond\Event\EvaluateRuleEvent;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use Webconsulting\WebconJev\Client\Dto\Answer;
use Webconsulting\WebconJev\Client\Dto\QuestionType;
use Webconsulting\WebconJev\Debug\DebugLog;
use Webconsulting\WebconJev\Debug\DecisionTrace;
use Webconsulting\WebconJev\Debug\RuleTrace;
use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
use Webconsulting\WebconJev\Service\DecisionOutcome;
use Webconsulting\WebconJev\Service\DecisionRunner;
use Webconsulting\WebconJev\Service\RunLogger;
use Webconsulting\WebconJev\Support\Cast;

/**
 * Answers the powermail_cond rule operators this extension registers.
 *
 * One form can carry several Jev rules against the same decision — six rules showing six
 * different follow-up blocks off one routing question is the normal shape. They are one call:
 * the outcome is memoised for the request, so the visitor's keystroke costs one round trip
 * however many rules read it.
 *
 * A rule whose answer is missing or below the decision's confidence threshold simply does not
 * apply. For hide/show conditions that is the harmless direction — the form stays as the editor
 * built it rather than collapsing around an answer nobody trusts.
 */
final class JevRuleListener
{
    /** @var array<string, DecisionOutcome> */
    private array $memo = [];

    public function __construct(
        private readonly RuleConfigurationRepository $ruleConfigurations,
        private readonly DecisionRepository $decisions,
        private readonly DecisionRunner $runner,
        private readonly FormStateCollector $stateCollector,
        private readonly LoggerInterface $logger,
        private readonly DebugLog $debugLog,
    ) {}

    public function __invoke(EvaluateRuleEvent $event): void
    {
        $operator = JevOperator::tryFrom($event->getOperation());
        if ($operator === null) {
            return;
        }

        $rule = $event->getRule();
        $configuration = $this->ruleConfigurations->forRule(Cast::int($rule->getUid()));
        if ($configuration === null || !$configuration->isComplete()) {
            $this->logger->warning('A Jev rule is not configured.', ['rule' => $rule->getUid()]);
            $this->debugLog->note(sprintf('Rule %d has no decision or question and never applies.', Cast::int($rule->getUid())));
            $event->setResult(false);

            return;
        }

        $decision = $this->decisions->findByUid($configuration->decisionUid, $this->languageId());
        if ($decision === null) {
            $this->logger->warning('A Jev rule points at a decision that is gone.', [
                'rule' => $rule->getUid(),
                'decision' => $configuration->decisionUid,
            ]);
            $this->debugLog->note(sprintf(
                'Rule %d points at decision %d, which is gone; it never applies.',
                Cast::int($rule->getUid()),
                $configuration->decisionUid,
            ));
            $event->setResult(false);

            return;
        }

        $form = $event->getForm();
        $formUid = Cast::int($form->getUid());
        $memoKey = $this->memoKey($decision->uid, $formUid);
        if (!isset($this->memo[$memoKey])) {
            $context = $this->stateCollector->collect($form);
            $this->memo[$memoKey] = $this->runner->run(
                $decision,
                $context,
                RunLogger::CONTEXT_CONDITION,
                sprintf('form %d, rule %d', $formUid, Cast::int($rule->getUid())),
            );
            $this->debugLog->trace(
                DecisionTrace::CONDITIONS . ':' . $memoKey,
                new DecisionTrace(DecisionTrace::CONDITIONS, $this->memo[$memoKey], $context),
            );
        }
        $outcome = $this->memo[$memoKey];

        $expected = $configuration->comparisonValue($operator);
        $answer = $this->usableAnswer($outcome, $operator, $configuration->questionName);
        $result = $answer !== null && $operator->matches($answer, $expected);

        $this->debugLog->find(DecisionTrace::CONDITIONS . ':' . $memoKey)?->addRule(new RuleTrace(
            ruleUid: Cast::int($rule->getUid()),
            operator: $operator,
            question: $configuration->questionName,
            expected: $expected,
            answer: $outcome->answer($configuration->questionName),
            usable: $answer !== null,
            result: $result,
        ));

        $event->setResult($result);
    }

    private function memoKey(int $decisionUid, int $formUid): string
    {
        return $decisionUid . ':' . $formUid;
    }

    private function languageId(): int
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return 0;
        }
        $language = $request->getAttribute('language');

        return $language instanceof SiteLanguage ? $language->getLanguageId() : 0;
    }

    /**
     * The answer, if this rule is allowed to act on it.
     *
     * Choice and score are gated on the decision's confidence, which is read off how concentrated
     * their probability distribution is.
     *
     * A noul has no such distribution. Its confidence is derived as |p - 0.5| * 2, so a decision
     * threshold of 0.75 can only ever be cleared by p <= 0.125 or p >= 0.875 — and a rule asking
     * "is the answer below 0.4" would then silently discard every match between 0.125 and 0.4.
     * Measured on the job application example: an applicant who wrote that they wanted to work
     * fully remotely scored 0.17, and the note addressing remote work was withheld from the one
     * person who had asked for it.
     *
     * A noul rule already states its own certainty — the threshold it compares against is the
     * whole question. So that threshold is the gate, and the decision's is not applied on top.
     * Routing is unaffected: {@see DecisionOutcome::outcomeFor()} still gates every outcome,
     * which is where a wrong answer costs something.
     */
    private function usableAnswer(DecisionOutcome $outcome, JevOperator $operator, string $question): ?Answer
    {
        if ($operator->expects() === QuestionType::Noul) {
            return $outcome->answer($question);
        }

        return $outcome->confidentAnswer($question);
    }
}
