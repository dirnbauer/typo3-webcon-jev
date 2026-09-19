..  include:: /Includes.rst.txt
..  _editors:

=======================
The backend module
=======================

:guilabel:`Admin Tools > Jev decisions`

Four tabs, each answering a different question.

Editor
======

Where a decision is written. The **state template** decides what Jev gets to read: ``{{field.marker}}``
inserts a form value, and leaving it empty sends every filled field as JSON.

An empty template is the safe default — a field added to the form later is included without anyone
editing the decision. A template is for narrowing: only what the question is actually about, which
is what the bill is calculated on, and what keeps an unrelated field from suggesting an answer.

Questions go underneath. Each needs a **name**, which is the key the answer comes back under and what
conditions and routing refer to, and a question phrased the way you would put it to a colleague who
can only see the state — no background, no examples of the answer you want.

For a choice, each option needs an id, a sentence saying what it means, and optionally an **outcome**:
what should happen when that option wins. For the powermail routing, the outcome is the email address.

Playground
==========

Paste a state and run the stored decision against it. You get the answer, the probability behind
every option, the confidence, what it cost, and — for choice questions — where the submission would
have gone.

This is the tab to use while writing a question. Change the wording, run the same state again, and
watch the distribution move. Caching is bypassed here so that comparison is honest.

Runs
====

Every call this decision has made: when, where from, what it answered, how sure it was, how long it
took and what it cost.

Two labels matter. **cached** means the answer was reused and the API was never called. **fallback**
means the default outcome was used instead of an answer — read the reason next to it.

Connection
==========

Whether this installation can reach Jev: the endpoint, the model, where the token comes from, and
whether decisions are enabled at all. :guilabel:`Send a test question` makes one real call, so
"is it working" has an answer that does not depend on a form.

Underneath, what it has cost over the last day and the last thirty days, with fallbacks counted
next to calls.

Translating a decision
======================

Decisions, questions and options are ordinary translatable records, so a German site can ask its
questions in German. The default language carries the structure — identifiers, question names,
types — and a translation overrides the wording. A field left empty in the translation keeps the
default language's text, so a half-translated decision still asks a complete question.

Jev is multilingual, so translating is a choice rather than a requirement: an English question about
a German message works. Translate when the wording itself needs to be precise in the language of
the people writing it.
