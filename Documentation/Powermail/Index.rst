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
        -   an option of that question
    *   -   Jev did not choose
        -   a choice
        -   an option of that question
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

A rule whose answer is missing, or below the decision's confidence threshold, simply does not apply.
For a show/hide condition that is the harmless direction: the form stays as the editor built it
rather than collapsing around an answer nobody trusts.

Routing a submission
====================

A powermail form gains a :guilabel:`Jev routing` tab. Pick a decision and the choice question that
names the receiver; each option of that question carries an outcome value, which is the address. An
outcome may hold several addresses separated by commas or newlines.

The decision runs the moment the submission is saved and complete, and the answer is applied where
powermail assembles its receiver list. Below the threshold nothing is replaced and the form's own
receiver gets the mail, exactly as it would without this extension. What was decided, and how sure
it was, is written onto the mail record and visible in :guilabel:`Powermail > Mails`.

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

The demo forms
==============

..  code-block:: bash

    vendor/bin/typo3 webcon-jev:examples:seed

Builds five example forms — contact routing, support triage, a quality gate, a branching project
enquiry and a branching job application — with their decisions, their conditions and a page each in
English and German. It needs EXT:desiderio's Powermail Lab page to put them under, or a ``--page``.

Running it again replaces what the last run made rather than adding to it, and it only ever touches
records it marked as its own.
