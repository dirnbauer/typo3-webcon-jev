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

The backend module
==================

A native TYPO3 v14 module under :guilabel:`Admin`, with three third-level modules the document
header's module menu switches between: ``webcon_jev_decisions`` (list and editor), ``webcon_jev_runs``
and ``webcon_jev_connection``. The group ``webcon_jev`` has no page of its own; ``tools_webconjev``,
the identifier before 0.2.0, is registered as its alias.

The pages are Fluid templates on the backend's ``Module`` layout. The decision editor and its
playground are Lit elements that render into the page rather than a shadow root, so the backend's own
form, card and panel styles apply and follow its colour scheme. Their labels come from the
``webcon_jev.module`` translation domain, which the templates read with ``f:translate`` and the
JavaScript imports as ``~labels/webcon_jev.module``. There is no build step: the files in
:file:`Resources/Public/JavaScript/` are what the import map serves.

The editor saves, deletes and runs through four AJAX routes — ``webcon_jev_decision_save``,
``webcon_jev_decision_delete``, ``webcon_jev_playground`` and ``webcon_jev_ping``. Each declares
``inheritAccessFromModule``, so a backend user who cannot open the module cannot call them either.

A save goes through :php:`DecisionValidator` and :php:`DecisionWriter`. The writer uses the
DataHandler, so an edit made in the module gets the same history, permission check and hooks as one
made in the record editor — and it deletes the questions and options the editor removed, which the
DataHandler does not do for an inline child that is merely left out of its parent's list.

Tests
=====

..  code-block:: bash

    composer install          # the extension's own toolchain, into .Build/
    composer ci               # phpstan (level 8) + coding standards + unit + functional
    composer ci:tests:unit
    composer ci:tests:functional

The functional suite runs on sqlite locally and on MariaDB in CI. It loads only what
:file:`composer.json` requires — nr-vault and this extension — so it proves the extension boots
**without** powermail and powermail_cond, both of which are optional. That is not a convenience:
every service that needs one of them is defined in :file:`Configuration/Services.php` behind a
``class_exists()`` guard, because a service in :file:`Services.yaml` whose constructor names a class
the autoloader cannot find takes the whole container down while it compiles.

A second base case loads the optional extensions too and checks the guarded registrations appear.

The module is covered the same way: its registration and access, every AJAX endpoint (saving,
refusing field by field, deleting, the playground with a stand-in for the API, the connection
check), and each page rendered from its templates.

Live calls are deliberately not part of the suite. What the real API taught this extension is
recorded in the changelog; a test that needs a token would be green on nobody's machine but one.
