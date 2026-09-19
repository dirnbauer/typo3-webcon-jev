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
    :default: 5

    Seconds to wait for an answer. Jev answers in 70–500 ms, so this only trips on trouble, and a
    request that trips it falls back.

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
