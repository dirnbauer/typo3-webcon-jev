..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

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
