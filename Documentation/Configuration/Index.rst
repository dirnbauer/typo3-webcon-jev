..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

:guilabel:`Admin Tools > Settings > Extension Configuration > webcon_jev`

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

    TypeSafe quote 70–500 ms. Measured from inside a container the round trip is **0.7–2.4 s**, and
    it has exceeded five seconds — which is why the default is not five. A timeout costs a
    decision and silently degrades the form, while the visitor is waiting on a background request
    either way, so erring long is the cheaper mistake here.

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

What a call costs
=================

Jev bills **input tokens only**, at $0.042 per million; output is free. A contact-form decision
sends a few hundred tokens, so a call costs a small fraction of a cent, and the run log adds it up
for you. The two things that actually move the bill are the size of the state and how often it is
sent — which is what the state template and the cache lifetime are for.
