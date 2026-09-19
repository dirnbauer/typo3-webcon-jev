:navigation-title: Jev decisions

..  include:: /Includes.rst.txt
..  _start:

=========================
Jev decisions for TYPO3
=========================

:Extension key:
    webcon_jev

:Package name:
    webconsulting/webcon-jev

:Version:
    0.1.0

:Language:
    en

:Author:
    webconsulting GmbH

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

`Jev <https://typesafe.ai/blog/introducing-system-one-models-and-jev>`__ is TypeSafe AI's
*System One* model. It does not generate text: it evaluates **typed questions against a state** and
returns a choice, a score or a probability, each with a calibrated confidence, in a single parallel
pass. It answers in 70–500 ms and bills input tokens only, which puts it somewhere a chat model
cannot go — inside a form, while somebody is still typing.

This extension is the TYPO3 end of that: decisions as records, a backend module to write and try
them, and two optional integrations with powermail.

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4

    ..  card:: :ref:`Introduction <introduction>`

        What a decision is, what the three primitives answer, and what confidence means.

    ..  card:: :ref:`Installation <installation>`

        Requirements, the API token, and where it is stored.

    ..  card:: :ref:`Configuration <configuration>`

        Extension configuration, caching and the budget guard.

    ..  card:: :ref:`Editors <editors>`

        The backend module: writing a decision, the playground, and the run log.

    ..  card:: :ref:`Powermail <powermail>`

        Rule operators for conditions, and routing a submission to a department.

    ..  card:: :ref:`Developers <developers>`

        Running a decision from your own code, and the events you can listen to.

..  toctree::
    :hidden:
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Editors/Index
    Powermail/Index
    Developers/Index
    Changelog/Index
