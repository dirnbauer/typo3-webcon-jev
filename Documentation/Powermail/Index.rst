..  include:: /Includes.rst.txt
..  _powermail:

=========
Powermail
=========

Two integrations, both optional and both registered only when the extension they need is installed.

Conditions
==========

With the `TYPO3 v14 fork of powermail_cond <https://github.com/dirnbauer/powermail_cond>`__
installed, a rule gains six operators:

..  list-table::
    :header-rows: 1

    *   -   Operator
        -   Reads
        -   Compares against
    *   -   Jev chose
        -   a choice
        -   an option of that question, and how likely it has to be
    *   -   Jev did not choose
        -   a choice
        -   an option of that question, and how unlikely it has to be
    *   -   Jev scored at least
        -   a score
        -   a level, which may be fractional
    *   -   Jev scored below
        -   a score
        -   a level
    *   -   Jev says yes, at least
        -   a noul
        -   a probability from 0 to 1
    *   -   Jev says yes, below
        -   a noul
        -   a probability

Pick a decision, then one of its questions — only questions whose type the operator can read are
offered, and for a choice the expected value is a dropdown of that question's own options, so there
is nothing to mistype.

..  note::

    "Show this field only when …" is an **un-hide** condition. powermail_cond negates a condition
    whose rules do not match, which hides the target again — so an un-hide condition means "visible
    only while this holds".

One call, however many rules
----------------------------

Several rules reading the same decision cost one call: the outcome is memoised for the request. Six
rules branching off one routing question is one round trip per keystroke, not six.

A **choice** rule reads how likely its own option is and compares that with its own threshold:
"Jev chose *large*" at 0.6 needs *large* at 60 % or more, "Jev did not choose *project*" at 0.75
needs *project* at 25 % or less. Which option won does not matter, so a letter Jev cannot place
between design and engineering still counts as certainly not project management. An answer without
a distribution by option falls back to the winning option and the decision's threshold.

A **score** rule whose answer is missing, or below the decision's confidence threshold, simply does
not apply. For a show/hide condition that is the harmless direction: the form stays as the editor
built it rather than collapsing around an answer nobody trusts.

A **noul** rule is judged on its own threshold instead — see the warning under
:ref:`introduction`. Gating a derived confidence on top of the probability the rule already tests
makes noul rules nearly unusable, and does it silently.

..  note::

    Write complementary conditions off **one** rule in the same direction, not as two opposite
    "show when" conditions. Two opposite conditions both stop applying when the answer is unusable,
    and both then negate — so a field and the notice explaining its absence can disappear together.
    The quality-gate example does this deliberately: one ``ScoreBelow`` rule hides the submit
    button and shows the notice, so an uncertain answer leaves the button and drops the notice
    rather than leaving the visitor with neither.

Routing a submission
====================

A powermail form gains a :guilabel:`Jev routing` tab. Pick a decision and the choice question that
names the receiver; each option of that question carries an outcome value, which is the address. An
outcome may hold several addresses separated by commas or newlines.

The decision runs the moment the submission is saved and complete, and the answer is applied where
powermail assembles its receiver list. Below the threshold the decision's default outcome gets the
mail; a decision without one leaves the form's own receiver in place, exactly as it would be without
this extension. Powermail's development-context address and a TypoScript
``receiver.overwrite.email`` always win. What was decided, how sure it was and where the mail went
is written onto the mail record and visible in :guilabel:`Powermail > Mails`.

With double opt-in the receiver mail goes out when the visitor confirms, in a later request; the
decision is taken again there, from the saved mail (from the cache while it holds the answer).

The thank-you text and the mails can say where the submission went: ``{jev_routing}`` is one
sentence in the visitor's language (English, German, Chinese, Hungarian), for example "Jev passed it
to accounting@example.com, 97% sure." It also says when Jev was not sure enough and the default
address got it. The lab examples use it as their whole thank-you text.

..  note::

    This is not a powermail *finisher*, although that is the obvious place to look for it. Finishers
    run after the mail has already been sent, which is too late to address it. The integration is
    two listeners instead: one on
    :php:`FormControllerCreateActionAfterMailDbSavedEvent` to decide, one on
    :php:`ReceiverMailReceiverPropertiesServiceSetReceiverEmailsEvent` to apply.

Routing table
=============

The condition endpoint is a page type, so a site with a route enhancer needs it in the map, or
every request to it 404s:

..  code-block:: yaml

    routeEnhancers:
      PageTypeSuffix:
        type: PageType
        default: /
        index: ''
        map:
          condition.json: 3132

The site also needs the ``in2code/powermail-cond`` site set, which loads the condition JavaScript
and defines that page type.

A theme that brings its own Powermail form template has to keep Powermail's hook classes on the
form: ``powermail_form`` and ``powermail_form_{form.uid}`` on the ``<form>`` tag,
``powermail_form_uid`` on the hidden form uid, and ``powermail_fieldwrap_{marker}`` around each
field. powermail_cond's script only starts on a ``.powermail_form``. Without that class nothing
calls the condition endpoint and every field stays visible: the rules work, but nobody asks them.

Seeing what Jev decided
=======================

Switch on :confval:`plugin.tx_webconjev.settings.debug <plugin.tx_webconjev.settings.debug>` and
every form that asks Jev shows a panel with the answers, how sure Jev was, and which rule each one
turned on or off. The panel reads the same outcome the rules read, so when a field does not appear,
it says whether Jev answered differently, answered below the threshold, or was not asked at all.

The demo forms
==============

..  code-block:: bash

    vendor/bin/typo3 webcon-jev:examples:seed

Builds five example forms — contact routing, support triage, a quality gate, a branching project
enquiry and a branching job application — with their decisions, their conditions and a page each in
English and German. It needs EXT:desiderio's Powermail Lab page to put them under, or a ``--page``.

It also adds a section to the Powermail Lab page that links the five forms, in English and German,
below EXT:desiderio's list of its own six. The section is marked as the seeder's own in its
:guilabel:`Description` field (``rowDescription``).

Running it again replaces what the last run made rather than adding to it, and it only ever touches
records it marked as its own. A reseed of the Desiderio styleguide replaces all content on the lab
page, this section included, so run ``webcon-jev:examples:seed`` again after it.
