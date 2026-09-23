..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

:guilabel:`System > Settings > Extension Configuration > webcon_jev`

..  confval:: tokenIdentifier
    :type: string
    :default: typesafe_api_key

    The nr-vault identifier the API token is stored under. See :ref:`installation-token`.

..  confval:: model
    :type: string
    :default: jev-latest

    Which model to ask. ``jev-latest`` follows the newest release; pin a version id here to stop
    that. A decision may override it.

..  confval:: endpoint
    :type: string
    :default: https://api.typesafe.ai/v1/systemone

    The System One endpoint.

..  confval:: enabled
    :type: boolean
    :default: 1

    Switches every decision off at once. Disabled, nothing is sent and every decision falls back to
    its default outcome — the fastest way to take Jev out of a live site without editing records.

..  confval:: timeout
    :type: integer
    :default: 10

    Seconds to wait for an answer before falling back.

    TypeSafe quote 70–500 ms. That is not what a TYPO3 installation measures. Over a run of real
    calls from a container:

    ..  list-table::
        :header-rows: 1

        *   -   What
            -   Observed
        *   -   Typical Jev call
            -   0.8–1.1 s
        *   -   Tail, roughly one call in six
            -   ~7 s
        *   -   Worst seen
            -   over 10 s (fell back)
        *   -   Network alone (TLS + an unauthenticated 403)
            -   0.85–1.1 s

    So the tail is the model, not the network, and it is long enough to matter. Ten seconds catches
    most of it; the rest falls back and says so in the run log. A timeout costs a decision and
    degrades the form silently, while the visitor is waiting on a background request either way,
    so erring long is the cheaper mistake.

    ..  warning::

        An installation that already **stored** a value keeps it: a changed default in
        ``ext_conf_template.txt`` only applies where nothing has been written yet. If this
        extension was installed before 0.1.5, check the value rather than assuming the new default
        reached you — a stale 5 is exactly the setting that produces occasional, silent fallbacks.

        A container deployment that regenerates ``config/system/settings.php`` from the image on
        every start is the exception: there the template default does land, because nothing
        persists to keep.

..  confval:: cacheLifetime
    :type: integer
    :default: 300

    How long an identical question about identical state reuses its answer. A visitor correcting a
    typo and changing it back asks the same question twice; the second one is free. ``0`` disables
    caching. A decision may override it, and the playground always bypasses it — a playground that
    answers from cache cannot tell you whether your edit to the wording changed anything.

..  confval:: maxCallsPerMinute
    :type: integer
    :default: 120

    The budget guard. Calls beyond this many in a minute fall back instead of being sent. It is not
    exact under concurrency and does not need to be: it exists so a runaway form cannot spend a
    month's budget in an afternoon. ``0`` disables it.

..  confval:: logRuns
    :type: boolean
    :default: 1

    Record every decision in the run log the module shows.

..  confval:: logRetentionDays
    :type: integer
    :default: 30

    What :bash:`webcon-jev:log:prune` keeps. Schedule that command.

..  confval:: storagePid
    :type: integer
    :default: 0

    Where decisions created in the backend module are stored. ``0`` keeps them at root level, which
    is where the :guilabel:`Records` module shows them.

A cold cache costs more than the call
=====================================

The condition endpoint is uncached by design — powermail_cond needs the current answers on every
keystroke — so each request is a full TYPO3 bootstrap. Measured on the same installation: a cold
page with no Jev in it takes **4.3–5.1 s**, and a warm condition request with a real Jev call takes
**0.8 s**. A cold condition request is both stacked, 6.7–10.7 s, and one connection died outright.

That is the platform, not this extension, and it lands on whoever hits a form first after a deploy
flushes the caches. If that matters for your site, warm the relevant pages after deploying rather
than reaching for the timeout.

What a call costs
=================

Jev bills **input tokens only**, at $0.042 per million; output is free. A contact-form decision
sends a few hundred tokens, so a call costs a small fraction of a cent, and the run log adds it up
for you. The two things that actually move the bill are the size of the state and how often it is
sent — which is what the state template and the cache lifetime are for.
