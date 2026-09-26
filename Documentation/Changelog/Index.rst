..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

0.2.10 — 2026-09-26
===================

Changed
-------

*   The escalation note of Powermail 08 (support triage) gives +49 30 23125 999, a number from
    the block the Bundesnetzagentur keeps free for film and television, instead of
    webconsulting's real phone number. The examples run on the Desiderio demo site, whose
    contact data reaches no one. ``webcon-jev:examples:seed`` writes the new text.

0.2.9 — 2026-09-26
==================

Fixed
-----

*   ``--as-provisioner`` works again after the database moved. The configured nr-vault
    ``provisioningBeUserUid`` was used whenever it was set; on typo3-lab the database had been
    replaced by a development copy where the provisioner has another uid, and every provisioned
    command — ``webcon-jev:ping``, ``webcon-jev:token:import``, so any token rotation — failed in
    nr-vault with "does not resolve to a non-deleted be_users record". A configured uid that is
    gone, deleted, disabled or not at root level now falls back to ``vault_provisioner`` by name,
    and the commands say which one they used.

0.2.8 — 2026-09-26
==================

Added
-----

*   A frontend debug panel, switched by TypoScript
    :confval:`plugin.tx_webconjev.settings.debug <plugin.tx_webconjev.settings.debug>` (site set
    :yaml:`webconsulting/webcon-jev` or static template "Jev decisions"). It shows, for every
    decision a request asked, what Jev read, each answer with its confidence and distribution,
    how each powermail_cond rule used it and which field it shows or hides, and where a routed
    submission went, with model, time, tokens and cost. Under a form it updates as the visitor
    types; after a submission it sits on the thank-you page. It is one self-contained fragment
    with no inline style or script, in English and German.
*   Both Powermail listeners report what stopped them before Jev was asked — a rule without a
    decision, a decision that was deleted — to that panel, where the run log never sees it.

0.2.7 — 2026-09-25
==================

Fixed
-----

*   The escalation note of Powermail 08 (support triage) gives webconsulting's real phone number,
    +43 2626 20156, in English and German. It named a made-up support line, +43 1 234 5678.
    ``webcon-jev:examples:seed`` writes the new text.

0.2.6 — 2026-09-24
==================

Changed
-------

*   The extension and module icons follow the TYPO3 v14 line style.

0.2.5 — 2026-09-24
==================

Fixed
-----

*   The German intro and form of each example page show on ``/de/``. ``webcon-jev:examples:seed``
    stored them on the page's translation record, where TYPO3 never looks for content, so the
    German pages showed the English intro. They now sit on the English page as its translations,
    like the overview; a functional test checks where every German element is stored.

0.2.4 — 2026-09-23
==================

Added
-----

*   ``webcon-jev:examples:seed`` lists the five examples on the Powermail Lab page, below
    EXT:desiderio's list of its own six forms: a linked title and a one-line summary each, in
    English and German. The German section sits on the lab page itself, so ``/de/`` shows it. A
    reseed replaces the section rather than adding a second one; a functional test seeds twice and
    checks that.

0.2.3 — 2026-09-23
==================

Changed
-------

*   The copy of the Powermail 08, 10 and 11 example pages follows the lab's style guide: no
    sentence over 25 words and page titles of at most 50 characters, in English and German.
    Powermail 11 is now "Job application, routed by its text" ("Bewerbung, nach Inhalt
    zugeordnet"). ``webcon-jev:examples:seed`` writes the new copy.

0.2.2 — 2026-09-23
==================

Fixed
-----

*   The routing summary on a powermail mail (``tx_webconjev_routing_summary``) is no longer searched
    by the backend search. powermail 14.0.3.2 dropped its ``searchFields``, and TYPO3 v14 then
    searches every text-like column that does not opt out; the column now says
    ``'searchable' => false``. The other columns this extension adds to powermail and powermail_cond
    are selects and numbers, which are never searched, and a test keeps it that way.
*   The decision editor's :guilabel:`Close` and :guilabel:`Save` buttons are labelled in the
    editor's language. They came from the core, which labels them from its language packs, so a
    German backend without those packs showed "Close" and "Save" next to "Löschen". A test checks
    every document header button of the module in German.

Changed
-------

*   Development and CI run against powermail 14.0.3.2 and the powermail_cond fork's ``typo3-v14``
    branch at ``9b93c57``, whose condition-aware validator no longer passes an argument to a
    constructor that takes none.

0.2.1 — 2026-09-23
==================

Translated decisions, and decisions an integration builds in code.

Added
-----

*   **Decisions built in code.** :php:`DecisionRunner::run()` takes a :php:`Decision` with uid ``0``
    that an integration puts together at run time — questions it only knows then, such as one per
    part of an imported document. It gets the same switch, token check, budget guard, cache and
    fallback as a stored decision, and its runs are logged under uid ``0`` and its identifier. See
    :ref:`developers-decision-in-code`.
*   The :guilabel:`Run log` shows such a run as an **ad-hoc decision** with its identifier;
    "Deleted decision" is left for a stored decision that no longer exists.
*   **Labels for other extensions' run contexts.** An integration names the context it passes to the
    runner in ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['webcon_jev']['runContexts'][<context>]``
    — an ``LLL:`` or translation domain reference, or plain text. The run log's rows and filter use
    it; a context nobody labelled is shown as written, as before.

Fixed
-----

*   **A translation is never a decision of its own.** A German translation shares its decision's
    identifier, as it should: the identifier is excluded from translation, and it is unique per
    language, so neither the record editor nor the module reports it as taken. What was wrong:

    *   :php:`DecisionRepository::findByUid()` given a translation's uid returned the translation as a
        decision of its own — with no questions, since those hang off the default record. It now
        returns the decision the translation belongs to, in the language asked for.
    *   The decision list counted a translated form or condition rule once per language under
        "Used by", and the delete confirmation did the same. Each is counted once now.
    *   Deleting through the module with a translation's uid deleted only that translation. It
        deletes the decision, which takes its translations along.

*   The run log cuts an identifier, context or model name that is longer than its column instead of
    failing the insert — which, on MariaDB, failed the run the row was logging.
*   The answer cache takes the decision's model into account. Changing a decision's model used to
    serve the previous model's answers until they expired.

0.2.0 — 2026-09-23
==================

A native backend module, and the defects that rebuilding it uncovered. **Breaking**: the module no
longer runs on ``webconsulting/typo3-shadcn-ui``, its identifier changed, and its AJAX routes changed.

Added
-----

*   The backend module is built from TYPO3's own backend components: Fluid templates on the
    ``Module`` layout, a document header with a module menu, cards, tables and callouts, and two Lit
    elements that render into the page. It follows the backend's colour scheme and is labelled in
    English and German. Three pages under :guilabel:`Admin > Jev decisions`: :guilabel:`Decisions`,
    :guilabel:`Run log` and :guilabel:`Connection`.
*   **The playground runs the editor's unsaved state.** Trying a new wording no longer means putting
    it in front of every visitor first. It asks for a sample of every value the state template
    reads — the old playground only ever sent ``field.message``, so a decision reading
    ``{{field.company}}`` and ``{{field.project}}`` could not be tried at all — and shows which
    choice would have routed where, and whether the default stood in.
*   **Validation where an editor can act on it.** A save names each problem next to its field and
    lists them above the form: a choice with one option, two questions of one name, two options of
    one id, a taken identifier, a threshold outside 0 to 1.
*   The decision list shows, per decision, how many forms route through it and how many condition
    rules read it, how often it ran in the last thirty days and how often it fell back. Deleting a
    decision names what still uses it.
*   The run log has a filter (decision, where from, result) that is part of the address, and
    pagination.
*   The connection page shows whether a **frontend** request can read the token — the one check the
    powermail integrations depend on — and the run log's retention.
*   Keyboard: :kbd:`Ctrl`/:kbd:`⌘` + :kbd:`S` saves, :kbd:`Ctrl`/:kbd:`⌘` + :kbd:`Enter` runs the
    playground; questions and options move with buttons, a removed question can be restored, and
    leaving with unsaved changes asks first.
*   A decision can be switched off from the editor.

Changed
-------

*   The module moved from :guilabel:`Admin Tools` (``tools_webconjev``) to a group ``webcon_jev``
    under :guilabel:`Admin`, with the pages ``webcon_jev_decisions``, ``webcon_jev_runs`` and
    ``webcon_jev_connection``. ``tools_webconjev`` is an alias of the group, so old bookmarks work.
*   The module and its AJAX routes are available in the live workspace only: the decision tables
    are not workspace-aware.
*   The AJAX routes are ``webcon_jev_decision_save``, ``webcon_jev_decision_delete``,
    ``webcon_jev_playground`` (now takes a ``draft``) and ``webcon_jev_ping``. ``webcon_jev_status``,
    ``webcon_jev_runs`` and ``webcon_jev_decisions`` are gone; the pages render that data themselves.
*   The decision identifier is unique across the table (``eval: unique``) rather than per site: code
    finds a decision by identifier alone.
*   The TCA uses the v14 translation domains, and the core now adds the language and visibility
    columns from the ``ctrl`` section.
*   ``netresearch/nr-vault`` ``^0.16 || ^1.0`` (1.0.0 in the extension's own lock). The development
    toolchain moves to PHPUnit 13, PHPStan 2.2 and PHP-CS-Fixer 3.95; CI runs PHP 8.4 and 8.5.
*   PHP 8.4 throughout: typed class constants, ``#[Override]``, ``array_find()``, enums for the token
    import outcome and the token source.

Removed
-------

*   The dependency on ``webconsulting/typo3-shadcn-ui`` and its VCS graph (``hn/typo3-mcp-server``,
    ``webconsulting/typo3-abilities``, ``netresearch/nr-llm``), and with it React, Tailwind, the
    TypeScript sources, Vite and every npm dependency. ``typo3/cms-extbase``, ``typo3/cms-frontend``,
    ``psr/http-client`` and ``ext-json`` were required but never used.

Fixed
-----

*   **A question or option removed in the module came back.** The DataHandler does not delete an
    inline child that is merely left out of its parent's list, so the module's save left removed
    questions attached — and still asked at runtime. A save now deletes what the editor removed,
    with its translations.
*   **A decision the API would refuse threw at the form.** A choice left with one option — possible
    through the record editor — made ``DecisionRunner::run()`` throw before it reached its own
    fallback handling, straight through the powermail condition endpoint and the routing. It falls
    back now, with the reason in the run log.
*   **The record editor showed raw label keys** for every field of the three decision tables
    (``locallang_db.xlfx_webconjev_decision.title`` and so on): the paths were broken when the TCA
    was written.
*   A submitted question uid that belongs to another decision is treated as new rather than moved.
*   **"What it has cost" counted cached answers again.** A cached row keeps the tokens its answer
    was computed on, and the totals summed them — with their cost and their original latency — as
    if the API had been asked. Tokens, cost and average latency now count the calls that reached
    the API; the run log shows a cached run without any, and the connection page counts cached
    answers on a line of their own.
*   **"Frontend can read it" said no whenever the token came from the environment**, although a
    frontend request falls back to ``TYPESAFE_API_KEY`` like any other. The ping command and the
    connection page now report what a visitor would actually get.

Security
--------

*   The AJAX routes inherit the module's access. Before, any signed-in backend user could call
    them: run the playground — which spends money — and ping, and attempt saves and deletes that
    only the DataHandler's table permissions stood in front of.

0.1.11
======

*   A test suite and CI, where before there were only hand-run checks against the live API. Unit
    coverage for parsing every primitive, the derived noul confidence, the six rule operators, the
    state template, the confidence gate and every fallback path; functional coverage for the
    translation overlay, the run log, the provisioning identity and its setup command.
*   The first functional run found a real defect: **the extension did not boot without
    shadcn_ui.** The module controller was registered unconditionally, so a container compile with
    an optional dependency absent was fatal. Every service that needs an optional extension is now
    defined behind a ``class_exists()`` guard, and the backend module and its import map are
    registered only when the runtime is there.

*   ``class_exists()`` turned out to be the wrong guard for a *service* dependency: in Composer mode
    an installed-but-inactive extension is still autoloadable, so the error merely changed to "no
    such service exists". The controller now takes its renderer as an optional constructor
    argument — how Symfony autowiring says "use it if some active extension defines it" — and the
    module and its import map are registered only when the runtime is loaded.
*   The suite found a second defect on its way to green: ``webcon-jev:vault:setup-provisioner``
    could not see a provisioning user somebody had **disabled**, because ``Connection::select()``
    applies TYPO3's default restrictions and ``disable`` is one of them. It then created a second
    ``vault_provisioner`` instead of repairing the first, and two users of one name make the lookup
    by name ambiguous. The identity lookup now reads the row as it is.

0.1.10
======

*   Honest latency figures in the manual. The earlier "0.7–2.4 s" understated a tail of roughly
    one call in six at ~7 s and one over 10 s, and the timeout guidance depends on that number
    being right. Also records what a cold cache adds: a page with no Jev in it takes 4.3–5.1 s on
    the same installation, so a first request after a deploy is the platform's bootstrap plus the
    model, not this extension being slow.

0.1.9
=====

*   ``webcon-jev:ping`` reports whether a **frontend** request could read the token. No other check
    answered that, and it is the only one the powermail integrations depend on: they run with no
    backend user and resolve the secret through its ``frontend_accessible`` flag alone. A ping that
    succeeds as an admin or a provisioner proves the token exists and the API is reachable, and
    says nothing about whether a visitor gets a decision or a fallback.
*   Two live calls during the same runs took 5.8 s and 6.8 s — both would have failed under the
    5-second timeout shipped until 0.1.5.

0.1.8
=====

*   Documentation only: a changed default in ``ext_conf_template.txt`` does **not** reach an
    installation that already stored a value. Raising the timeout to 10 s in 0.1.5 left this
    installation on 5 s, and the next request timed out at exactly 5001 ms. Check the stored value
    on every long-lived install; a container that regenerates ``settings.php`` from the image is
    the one that does not need checking.

0.1.7
=====

*   The provisioning identity is found by name when nr-vault's ``provisioningBeUserUid`` is gone.
    Measured on a container deployment: the setting was written, the next deploy replaced
    ``config/system/settings.php`` — part of the image, not a volume — and the uid was back to 0.
    The token kept working, because it lives in the database, but the next rotation would have
    failed months later with nothing obviously changed. A backend user is a database row and
    survives.

0.1.6
=====

*   ``webcon-jev:ping --as-provisioner`` verifies a server that keeps nr-vault's CLI access off.
    Without it the check reported "No token" on an installation whose frontend was resolving the
    token perfectly well: ``exists()`` needs no read permission and ``retrieve()`` does, so CLI
    reads are gated exactly like CLI writes.
*   The status line no longer contradicts the result. It said the token was in the vault directly
    above an error saying there was none, and it credited nr-vault for a value that had actually
    come from the environment.

0.1.5
=====

*   ``webcon-jev:vault:setup-provisioner`` creates the backend group and user nr-vault writes
    secrets as, and points ``provisioningBeUserUid`` at it. Installing the token on a server is now
    two commands rather than three steps in a production backend.
*   The request timeout default goes from 5 to 10 seconds. Measured round trip from a container is
    0.7–2.4 s against a quoted 70–500 ms, and a real request exceeded five seconds and fell back.
    **An installation that already stored a timeout keeps it** — check the value rather than
    assuming this reached you.

0.1.4
=====

*   ``webcon-jev:token:import --as-provisioner`` writes the token through nr-vault's technical
    actor, so a server does not have to switch ``allowCliAccess`` on — which would hand every CLI
    process on the host create/rotate/use over every secret in the vault. The secret is owned by
    the provisioning user so the same command can rotate it later, and stays frontend-accessible,
    because a technical actor is a trusted caller and is not coerced.
*   The command no longer reports "Stored" for a secret it left alone. Skipping, creating and
    rotating had been collapsed into one falsy value.

0.1.3
=====

*   The job application example's noul asked two things at once — whether the writer would move
    **or** was already nearby — and a letter saying "fully remotely from Graz" satisfied both,
    answering 0.42 at confidence 0.16. Split into one question it answers 0.97 at 0.94 on the same
    letter. The manual now says so, because a low confidence is usually a note about the wording.

0.1.2
=====

The first run against the live API, and the two things it found.

*   A **noul rule in a condition is now judged on its own threshold**, not additionally on the
    decision's confidence. Derived noul confidence is ``|p - 0.5| * 2``, so a 0.75 threshold
    discarded every match between 0.125 and 0.4 — measured on the job application example, the
    applicant who asked for fully remote work was the one person who did not see the note about
    remote work. Routing is unchanged and still gated.
*   The **quality gate no longer strands a visitor**. Its submit button and the notice explaining
    its absence were two opposite "show when" conditions; an answer too uncertain to use negated
    both, leaving no send button and nothing saying why. Both now read one rule in the same
    direction, so an uncertain answer leaves the button and drops the notice.
*   The job application's relocation note no longer tells the applicant what their own letter said.

0.1.1
=====

*   The manual.

0.1.0
=====

First release.

*   Jev client for the System One API, with all three primitives and typed answers.
*   Decisions, questions and options as translatable TYPO3 records.
*   Backend module on the shadcn/ui base: editor, playground, run log, connection status.
*   powermail_cond rule operators, via the v14 fork's ``EvaluateRuleEvent``.
*   powermail submission routing, with a confidence gate and the form's own receiver as the fallback.
*   API token in nr-vault, seeded from the environment by ``webcon-jev:token:import``.
*   Five demo forms built by ``webcon-jev:examples:seed``.
