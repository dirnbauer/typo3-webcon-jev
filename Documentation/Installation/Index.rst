..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

Requirements
============

..  list-table::
    :header-rows: 1

    *   -   Package
        -   Version
        -   Why
    *   -   TYPO3
        -   14.3 LTS
        -
    *   -   PHP
        -   8.4+
        -
    *   -   ``netresearch/nr-vault``
        -   ^0.16
        -   Holds the API token, encrypted and audited
    *   -   ``in2code/powermail``
        -   optional
        -   Enables routing a submission to a department
    *   -   ``in2code/powermail_cond``
        -   optional
        -   Enables the Jev rule operators. Needs the TYPO3 v14 fork
    *   -   ``webconsulting/typo3-shadcn-ui``
        -   optional
        -   Provides the backend module's runtime

Install
=======

..  code-block:: bash

    composer require webconsulting/webcon-jev
    vendor/bin/typo3 extension:setup

..  _installation-token:

The API token
=============

Jev is a closed, managed API. You need a key from `the TypeSafe console
<https://console.typesafe.ai/>`__.

Put it in the environment first. In DDEV, :file:`.ddev/config.local.yaml` is git-ignored:

..  code-block:: bash

    printf '  - TYPESAFE_API_KEY=%s\n' 'your-key' >> .ddev/config.local.yaml && ddev restart

Then move it into the vault, so it is encrypted and audited rather than sitting in a config file,
and check that it works:

..  code-block:: bash

    vendor/bin/typo3 webcon-jev:token:import
    vendor/bin/typo3 webcon-jev:ping

The command reads :envvar:`TYPESAFE_API_KEY` rather than taking the token as an argument, so it
never appears in a shell history, a process list or a terminal recording.

..  warning::

    The secret is stored **frontend-accessible**. Both powermail integrations run in a frontend
    request with no backend user — the condition endpoint while somebody types, the routing when a
    form is submitted — and nr-vault's ordinary ``retrieve()`` refuses there by design.

    The flag means any frontend code path in this installation can read the secret through the
    vault. The token is read server-side and never reaches a browser; only the decision does. But
    if that trade is not acceptable to you, the flag is what to leave off: without it the backend
    module, the CLI and the scheduler still work, and every frontend decision falls back to its
    default rather than failing.

The vault is read first and the environment variable is the development fallback, so a checkout
works before anyone seeds the vault, and production does not depend on an environment variable.

On a server, without switching CLI access on
==============================================

nr-vault ships with ``allowCliAccess`` off, and it should stay off: switching it on lets **any**
CLI process on that host create, rotate and use **every** secret in the vault, unattributed — the
OpenAI key and the Slack tokens next to this one. The import command therefore has a second mode
that needs no such switch:

..  code-block:: bash

    vendor/bin/typo3 webcon-jev:token:import --as-provisioner

The write is then attributed to the backend user named by nr-vault's ``provisioningBeUserUid``
setting. Set that up once:

#.  A backend group carrying the custom permission options
    ``tx_nrvault:secret.create,tx_nrvault:secret.rotate`` — and nothing else.
#.  A **non-admin** backend user at root level, in that group, with no usable password. It is an
    identity for the audit log, not an account anybody signs into.
#.  ``provisioningBeUserUid`` in the nr_vault extension configuration, set to that user's uid.

The uid comes from configuration and never from the command line: a flag that took it as an
argument would be an impersonation primitive for anyone with a shell.

..  note::

    The secret ends up **owned by the provisioning user**, deliberately. Rotating a secret is a
    per-secret ACL decision rather than a group permission, so a provisioner that handed ownership
    to uid 0 could create the token once and then be refused every rotation of it — a failure that
    surfaces the first time somebody tries to replace a leaked key. Run without
    ``--as-provisioner`` and ownership stays at 0, as before.
