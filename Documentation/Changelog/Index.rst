..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

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
