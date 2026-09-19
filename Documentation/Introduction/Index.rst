..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

What a decision is
==================

A **decision** is a named set of questions about one kind of state, plus what to do when the answer
is not certain enough to act on. It is an ordinary TYPO3 record, so it is translatable, has a
history, and can be edited by an editor rather than a developer.

The **state** is whatever the integration knows: the values of a half-filled form, a submitted mail,
anything you can serialise. Jev reads it and answers every question about it in one pass, which is
why a decision holds several questions rather than one — asking four separately costs four times the
input tokens and four times the latency for the same answers.

The three primitives
====================

..  confval:: choice
    :type: one of N options

    Returns the winning option, the probability of every option, and a confidence. This is the one
    that routes: which department, which queue, which team.

..  confval:: score
    :type: a position on an ordered rubric

    Returns a number that may fall *between* two levels, with the distribution and a confidence.
    Use it where the position matters: severity, effort, seniority, how well something fits.

..  confval:: noul
    :type: a probability from 0 to 1

    How likely a yes/no statement is to be true. Use it where the probability itself is the answer:
    "is this a bug report?", "is this spam?".

A choice and a score describe their options in the backend as a list of possibilities; a score's
order is what gives it meaning, so its levels go from lowest to highest. A noul may describe what
yes and no mean, which sharpens the answer, but does not have to.

Confidence
==========

Every answer carries a confidence from 0 to 1, derived from how concentrated the probability
distribution is. One clear winner gives a high number; a distribution spread across three options
gives a low one.

..  list-table::
    :header-rows: 1

    *   -   Confidence
        -   What TypeSafe recommends
    *   -   below 0.5
        -   Do not act. Route to a human, ask for clarification, or use a different system.
    *   -   0.5 to 0.9
        -   Proceed with caution: confirm, or flag for review.
    *   -   above 0.9
        -   Act automatically. The model has a clear read.

Each decision carries its own threshold, because the right one depends on what the answer is used
for. Hiding a field is cheap to get wrong; addressing somebody's job application is not. An answer
below the threshold is not used: the decision's default outcome is, and the run is recorded as one
that needs a human.

What happens when Jev cannot answer
===================================

Nothing in this extension throws at a form. No token, an outage, a rate limit, a malformed answer,
an answer below the threshold — each becomes a **fallback** carrying the decision's own default, and
a row in the run log saying why.

That is the right behaviour for a contact form, and it has a cost: a silent failure is possible. A
form that quietly routed every submission to its default receiver for a week looks exactly like a
form that worked. The run log is where you see the difference, and the module's cost panel counts
fallbacks next to calls for exactly that reason.
