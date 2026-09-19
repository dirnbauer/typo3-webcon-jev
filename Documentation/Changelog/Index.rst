..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

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
