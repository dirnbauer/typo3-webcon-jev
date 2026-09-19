..  include:: /Includes.rst.txt
..  _developers:

==========
Developers
==========

Running a decision
==================

..  code-block:: php

    use Webconsulting\WebconJev\Domain\Repository\DecisionRepository;
    use Webconsulting\WebconJev\Service\DecisionRunner;

    final readonly class MyService
    {
        public function __construct(
            private DecisionRepository $decisions,
            private DecisionRunner $runner,
        ) {}

        public function route(string $text): string
        {
            $decision = $this->decisions->findByIdentifier('jev_contact_department');
            if ($decision === null) {
                return 'office@example.com';
            }

            $outcome = $this->runner->run(
                $decision,
                ['field' => ['message' => $text]],
                'my_extension',
            );

            if ($outcome->needsHumanReview()) {
                // Nothing was decided with enough certainty.
            }

            return $outcome->outcomeFor('department');
        }
    }

:php:`DecisionRunner::run()` **never throws**. Every failure becomes a fallback outcome carrying the
decision's default, and a row in the run log. Use it unless you want to handle failure yourself.

:php:`JevClientInterface::ask()` is the layer underneath: no decisions, no cache, no budget guard,
no logging, and it throws :php:`JevException` subclasses — :php:`AuthenticationException`,
:php:`RateLimitException`, :php:`UnavailableException`, :php:`InvalidQuestionException`,
:php:`InvalidResponseException`, :php:`NotConfiguredException`.

Asking without a decision record
================================

..  code-block:: php

    use Webconsulting\WebconJev\Client\Dto\Question;
    use Webconsulting\WebconJev\Client\Dto\QuestionType;

    $result = $client->ask('The delivery arrived three days late.', [
        'mood' => new Question(
            name: 'mood',
            type: QuestionType::Choice,
            instructions: 'How does the writer feel about what happened?',
            criteria: [
                'happy' => 'Pleased with how it went',
                'annoyed' => 'Unhappy about a problem',
            ],
        ),
    ]);

    $answer = $result->get('mood');
    $answer?->choice;          // 'annoyed'
    $answer?->confidence;      // 0.0 to 1.0
    $answer?->probabilities;   // ['happy' => 0.04, 'annoyed' => 0.96]

Put every question about one state in **one** call. Jev evaluates them in a single parallel pass, so
splitting them costs a multiple of the tokens and the latency for the same answers.

Adding your own rule operator to powermail_cond
===============================================

The v14 fork reserves rule operator values from
:php:`Rule::OPERATOR_THIRD_PARTY_OFFSET` (100) upwards for other extensions. A rule carrying one is
handed to listeners of :php:`In2code\PowermailCond\Event\EvaluateRuleEvent`, which sees the rule,
the whole form with its submitted values, and the start field:

..  code-block:: php

    public function __invoke(EvaluateRuleEvent $event): void
    {
        if ($event->getOperation() !== self::MY_OPERATOR) {
            return;   // not mine
        }

        $event->setResult($this->evaluate($event->getForm()));
    }

Register the operator in the TCA of ``tx_powermailcond_domain_model_rule`` and add whatever columns
it needs there. A rule whose operator no listener recognises never applies.

This is how the Jev operators work, and it is why no Jev knowledge lives in the fork.

Sensitive fields
================

:php:`FormStateCollector` and :php:`MailStateCollector` skip ``password``, ``file``, ``captcha`` and
``friendlycaptcha`` fields, whatever the form calls them, so they are never sent to a third-party
API. Empty values are dropped too: an untouched field costs nothing and suggests nothing.

If a form has a field that must not leave the server, give the decision a state template naming only
the fields it needs.
