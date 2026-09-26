# Jev decisions for TYPO3

[![TYPO3 14.3](https://img.shields.io/badge/TYPO3-14.3-orange.svg)](https://get.typo3.org/version/14)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4%2B-777bb3.svg)](https://www.php.net/)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

[Jev](https://typesafe.ai/blog/introducing-system-one-models-and-jev) is TypeSafe AI's *System One*
model. It does not generate text. It evaluates **typed questions against a state** and returns a
choice, a score or a probability — each with a calibrated confidence — in a single parallel pass,
at a latency and a price a chat model cannot reach. That makes it usable somewhere an LLM is not:
inside a form, while somebody is still typing.

This extension is the TYPO3 end of that.

## What it gives you

**A decision.** A named set of questions about one kind of state, a confidence threshold, and what
to do when the answer does not clear it. Decisions are ordinary TYPO3 records, so they are
translatable, keep a record history, and are edited by editors rather than developers.

**A backend module** (Admin → Jev decisions), built from TYPO3's own backend components and
labelled in English and German. Write the questions; try them in the playground next to the form —
before saving, against a sample of every value the decision reads — and see the probability
distribution behind each answer and where a submission would have gone; read every call that has
been made with its latency and its cost; and check whether the API is reachable at all.

**Two optional powermail integrations.** Rule operators so a
[powermail condition](https://github.com/dirnbauer/powermail_cond) can consult a decision — show
this field only when Jev says the message is a bug report — and a router that addresses a
submission to the department that should answer it.

## The three primitives

| Type | Answers | Use it for |
| --- | --- | --- |
| **choice** | one of N options, with the full distribution | routing, classification |
| **score** | a position on an ordered rubric, possibly between two levels | severity, effort, fit |
| **noul** | how likely a yes/no statement is, as a probability | "is this a bug?", "is this spam?" |

Every answer carries a confidence from 0 to 1. Below `0.5` the model is guessing; above `0.9` it has
a clear read. Each decision sets its own threshold, and an answer that does not clear it is not used.

## Requirements

| | |
| --- | --- |
| TYPO3 | 14.3 LTS |
| PHP | 8.4+ |
| netresearch/nr-vault | ^0.16 or ^1.0 — holds the API token |
| in2code/powermail | optional — enables the routing (needs the [v14 fork](https://github.com/dirnbauer/powermail)) |
| in2code/powermail_cond | optional — enables the rule operators (needs the [v14 fork](https://github.com/dirnbauer/powermail_cond)) |

Upgrading from 0.1: the module no longer needs `webconsulting/typo3-shadcn-ui` — remove it if
nothing else uses it — and it moved from Admin Tools to Admin → Jev decisions. Old bookmarks still
arrive there; nothing in the database changes.

## Install

```bash
composer require webconsulting/webcon-jev
vendor/bin/typo3 extension:setup
```

## The API token

Jev is a closed, managed API: you need a key from [TypeSafe](https://console.typesafe.ai/). Put it
in the environment first — in DDEV, `.ddev/config.local.yaml` is git-ignored:

```bash
printf '  - TYPESAFE_API_KEY=%s\n' 'your-key' >> .ddev/config.local.yaml && ddev restart
```

Then move it into the vault, so it is encrypted and audited rather than sitting in a config file:

```bash
vendor/bin/typo3 webcon-jev:token:import
vendor/bin/typo3 webcon-jev:ping
```

**On a server**, leave nr-vault's `allowCliAccess` off — switching it on lets any CLI process on
that host create, rotate and use *every* secret in the vault. Two commands instead:

```bash
vendor/bin/typo3 webcon-jev:vault:setup-provisioner
vendor/bin/typo3 webcon-jev:token:import --as-provisioner
```

The first creates a non-admin backend user that cannot sign in, carrying exactly
`tx_nrvault:secret.create` and `secret.rotate`, and points nr-vault's `provisioningBeUserUid` at
it. The second writes the token as that user, so every write is attributed to it by name.

The secret is stored **frontend-accessible**, because the powermail condition endpoint that consults
Jev runs with no backend user. The token is read server-side and never reaches a browser — only the
decision does. `TYPESAFE_API_KEY` stays as a development fallback; the vault is read first.

## Configure

**System → Settings → Extension Configuration → webcon_jev**

| Key | Default | What it does |
| --- | --- | --- |
| `tokenIdentifier` | `typesafe_api_key` | The vault identifier the token lives under |
| `model` | `jev-latest` | Which model to ask |
| `endpoint` | `https://api.typesafe.ai/v1/systemone` | The System One endpoint |
| `enabled` | `1` | Switch every decision off at once; they fall back to their defaults |
| `timeout` | `10` | Seconds to wait before falling back. Measured from a container, a call takes 0.7–2.4 s and has taken over 5 s |
| `cacheLifetime` | `300` | How long an identical question about identical state reuses its answer |
| `maxCallsPerMinute` | `120` | Budget guard; calls beyond it fall back instead of being sent |
| `logRuns` | `1` | Record every call in the run log |
| `logRetentionDays` | `30` | What `webcon-jev:log:prune` keeps |
| `storagePid` | `0` | Where decisions made in the module are stored |

## Nothing here throws at a form

No token, an outage, a rate limit, a malformed answer, an answer below the threshold: every one of
them becomes a **fallback** carrying the decision's own default outcome, and a row in the run log
saying why. A form never breaks because a model is having a bad minute.

That also means a silent failure is possible — a form that quietly routed everything to its default
receiver for a week looks exactly like a form that worked. The run log is where you see it, and the
module counts fallbacks next to runs — per decision in the list, and in total on the connection page
— for exactly that reason.

## powermail conditions

With the [v14 fork of powermail_cond](https://github.com/dirnbauer/powermail_cond) installed, a rule
gains six operators — *Jev chose*, *did not choose*, *scored at least*, *scored below*, *says yes at
least*, *says yes below*. Pick a decision and one of its questions; only questions whose type the
operator can read are offered.

Several rules reading the same decision cost **one** call: the outcome is memoised per request, so
six rules branching off one routing question is one round trip per keystroke, not six.

A rule whose answer is missing or below the threshold simply does not apply — for a show/hide
condition that is the harmless direction, leaving the form as the editor built it.

## powermail routing

A powermail form gains a **Jev routing** tab: pick a decision and the choice question that names the
receiver. Each option of that question carries an outcome value, which is the address.

The decision runs the moment the submission is saved and complete, and the answer is applied where
powermail assembles the receiver list. Below the confidence threshold nothing is replaced and the
form's own receiver gets the mail, exactly as it would without this extension. What was decided, and
how sure it was, is written onto the mail record.

(It is not a powermail *finisher*, although that is the obvious place to look for it: finishers run
after the mail has already gone out, which is too late to address it.)

## Seeing what Jev decided

Add the site set `webconsulting/webcon-jev` (or the static template "Jev decisions") and switch on

```typoscript
plugin.tx_webconjev.settings.debug = 1
```

— as a site setting, a constant, or inside a condition such as `[backend.user.isLoggedIn]`. Every
form that asks Jev then shows a panel: what Jev read, each answer with its confidence and full
distribution, whether it cleared the threshold, which field each rule shows or hides, where a
submission was routed, and model, time, tokens and cost. Under a form it updates while the visitor
types; after a submission it sits on the thank-you page. Visitors see it too — keep it off on a
live site.

A theme with its own Powermail form template must keep `powermail_form` on the `<form>` tag:
powermail_cond's script only starts on that class, and without it the conditions are never asked.

## Commands

```bash
vendor/bin/typo3 webcon-jev:vault:setup-provisioner  # create the identity a server writes secrets as
vendor/bin/typo3 webcon-jev:token:import     # move TYPESAFE_API_KEY into the vault
vendor/bin/typo3 webcon-jev:ping             # one real call, to prove token + endpoint + network
vendor/bin/typo3 webcon-jev:log:prune        # apply the run log retention (schedule this)
vendor/bin/typo3 webcon-jev:examples:seed    # build the five demo forms (needs EXT:desiderio's lab)
```

## For developers

```php
$outcome = $decisionRunner->run($decision, ['field' => ['message' => $text]], 'my_context');

if ($answer = $outcome->confidentAnswer('department')) {
    $address = $outcome->outcomeFor('department');   // falls back to the decision's default
}
if ($outcome->needsHumanReview()) {
    // nothing was decided with enough certainty
}
```

```bash
composer install && composer ci    # phpstan, coding standards, unit + functional tests
```

The module's JavaScript is plain ES modules served through TYPO3's import map — there is no npm and
no build step.

`DecisionRunner::run()` never throws. `JevClientInterface::ask()` does — use the runner unless you
want to handle `JevException` yourself.

## Licence

GPL-2.0-or-later.
