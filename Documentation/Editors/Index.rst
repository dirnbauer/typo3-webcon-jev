..  include:: /Includes.rst.txt
..  _editors:

==================
The backend module
==================

:guilabel:`Admin > Jev decisions`, for administrators. Three pages, switched with the module menu
at the top left of the document header: :guilabel:`Decisions`, :guilabel:`Run log` and
:guilabel:`Connection`. The module is a plain TYPO3 backend module — it follows the backend's light
or dark colour scheme and the user's language, English or German.

Decisions
=========

The list shows every decision with its questions, its threshold and its default outcome, how many
powermail forms route through it and how many condition rules read it, and how often it ran in the
last thirty days — with fallbacks counted next to the runs. A decision switched off shows a
:guilabel:`Disabled` badge: forms treat it as absent.

Each row has the same actions: edit, try it in the playground, open its run log, open it in the
record editor (for translations, history and access), and delete. Deleting asks first, and names
the forms and rules that still use the decision.

Writing a decision
------------------

:guilabel:`New decision` in the document header, or :guilabel:`Edit` on a row, opens the editor.

**What Jev reads.** The **state template** decides what Jev gets to read: ``{{field.marker}}``
inserts a form value, and the editor lists every value a template reads. Leaving it empty sends
every filled field as JSON.

An empty template is the safe default — a field added to the form later is included without anyone
editing the decision. A template is for narrowing: only what the question is actually about, which
is what the bill is calculated on, and what keeps an unrelated field from suggesting an answer.

**When the answer is unclear.** The confidence threshold, the default outcome that stands in
below it, the cache lifetime (``-1`` follows the extension configuration, ``0`` never caches) and,
if needed, a model other than the configured one.

**Questions.** Each needs a **name**, which is the key the answer comes back under and what
conditions and routing refer to, a **type**, and the question itself, phrased the way you would put
it to a colleague who can only see the state — no background, no examples of the answer you want.

Underneath, what the type needs:

*   a **choice** lists its options: an id, a sentence saying what the option means, and optionally
    an **outcome** — what should happen when that option wins. For the powermail routing, the
    outcome is the email address.
*   a **score** lists its levels from lowest to highest. The answer may land between two of them,
    so the position decides; levels have no id and no outcome.
*   a **noul** may describe what yes and no mean, with the ids ``yes`` and ``no`` — the editor
    offers them.

Questions and options can be moved up and down and removed; a removed question can be restored
from the notification that confirms it. Nothing is stored until you save.

Saving checks everything the API and the integrations rely on, and puts each problem next to the
field it is about: a choice with one option, two questions with the same name (answers come back by
name), two options with the same id (the request would carry only one of them), an identifier
another decision already uses. A list of the problems appears above the form, each one a link to
its field.

..  tip::

    **Ask about one thing.** A low confidence usually means the question is badly posed, not that
    the text was ambiguous — and the playground is where you see it.

    Measured on the job application example: asking *"is the writer willing to move **or** already
    nearby?"* against a letter reading "an internship I can do fully remotely from Graz" satisfied
    both halves at once and came back **0.42 at confidence 0.16**. Split into a single question —
    *"does the letter ask to work remotely?"* — the same letter answered **0.97 at confidence
    0.94**. Nothing else changed.

    Treat a confidence below about 0.5 in the playground as a note about your wording, and split
    the question before you reach for a lower threshold.

The playground
--------------

Next to the form on a wide screen, below it on a narrow one. It runs **what the form holds right
now, saved or not**, so a new wording can be tried before any visitor meets it.

It asks for a sample of every value the state template reads — ``{{field.subject}}`` and
``{{field.message}}`` get a field each. With no template it offers a list of form fields to fill,
named the way the form's markers name them.

You get the answer to every question with the probability behind every option, the confidence (an
answer below the threshold is marked, because a form would use the default outcome instead), where a
choice would have routed the submission, the state exactly as it was sent, and what the call took
and cost. Caching is bypassed here, so running the same sample again after an edit shows exactly
what the edit changed. Every run is logged, and costs a fraction of a cent.

Keyboard
--------

Everything is reachable with the keyboard, and every action is an ordinary button.

..  list-table::
    :header-rows: 1

    *   -   Keys
        -   What they do
    *   -   :kbd:`Ctrl` + :kbd:`S` (:kbd:`⌘` + :kbd:`S` on a Mac)
        -   Save the decision
    *   -   :kbd:`Ctrl` + :kbd:`Enter` (:kbd:`⌘` + :kbd:`Enter`)
        -   Run the playground

Leaving the editor with unsaved changes asks first.

Run log
=======

Every call: when, which decision, where from (a condition, the routing, the playground, the command
line or your own code), what it answered and how sure it was, how long it took, how many tokens it
read and what it cost. Filter by decision, by where it came from and by result; the filter is part of
the address, so a filtered view can be bookmarked or shared.

Two results matter. **Cached** means the answer was reused and the API was never called.
**Fallback** means the default outcome was used instead of an answer — the reason is next to it.

The log keeps what the extension configuration's retention says; schedule
``vendor/bin/typo3 webcon-jev:log:prune`` to apply it.

Connection
==========

Whether this installation can reach Jev: the endpoint, the model, where the token comes from,
whether a **frontend** request can read it — the powermail integrations run without a backend user,
so that is the check that decides whether visitors get a decision or a fallback — whether decisions
are switched on, and the timeout, cache and budget settings.

:guilabel:`Send a test question` makes one real call, so "is it working" has an answer that does
not depend on a form. Next to it, what the calls have cost over the last day and the last thirty
days, with fallbacks counted next to calls.

Translating a decision
======================

Decisions, questions and options are ordinary translatable records, so a German site can ask its
questions in German. The module edits the default language; translate in the record editor. The
default language carries the structure — identifiers, question names, types — and a translation
overrides the wording. A field left empty in the translation keeps the default language's text, so
a half-translated decision still asks a complete question.

A translation is not a decision of its own. It keeps its decision's identifier — the record editor
shows it read-only, and renaming the decision renames it too — so forms and condition rules find the
same decision in every language. The module lists and counts each decision once, and deleting a
decision deletes its translations.

Jev is multilingual, so translating is a choice rather than a requirement: an English question about
a German message works. Translate when the wording itself needs to be precise in the language of
the people writing it.
