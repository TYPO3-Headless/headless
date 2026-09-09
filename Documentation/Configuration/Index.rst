.. _configuration:

=============
Configuration
=============

.. toctree::
   :hidden:

   MultiSite

Site configuration
==================

Headless is enabled per site in `config/sites/<identifier>/config.yaml`:

.. code-block:: yaml

   dependencies:
     - friendsoftypo3/headless
   headless: 1

`dependencies` — one of the three site sets
(details: :ref:`ref-typoscript-default-set`):

* `friendsoftypo3/headless` — trimmed default response (new projects).
* `friendsoftypo3/headless-legacy` — full 4.x-compatible response (upgrades).
* `friendsoftypo3/headless-mixed` — mixed mode, serving the legacy response.

`headless` — run mode: `0` off, `1` always JSON, `2` mixed (Accept-driven —
pair it with the mixed set, see :ref:`configuration-mixed-accept`). The 3.x
boolean values `headless: true|false` are still accepted (cast to `1`/`0`);
use the integers.

A site using sets must not carry a root sys_template record: its "Clear"
flags wipe all set-provided TypoScript. Sites not using sets can select the
equivalent sys_template statics instead ("Headless", "Headless Legacy
(4.x)", "Headless - Mixed mode JSON response").

URLs pointing at the frontend domain (`frontendBase`, `frontendApiProxy`,
per-language variants) are covered in :ref:`multisite`. Installed core
extensions integrate without further setup — see :ref:`what-it-does` and
:ref:`integrations`.

.. _configuration-mixed-accept:

Mixed mode and the `Accept` header
----------------------------------

On a `headless: 2` site a request gets JSON only when its whole `Accept`
header is exactly `application/json`. A list such as
`application/json, text/plain, */*` (the axios/fetch default) or a
parameter such as `application/json; charset=utf-8` renders HTML. The exact
match is intentional — it is the API contract for headless clients, so set
the header explicitly in the frontend.

One URL then has two representations; add `Vary: Accept` to the page so
shared caches keep them apart (:ref:`caching`).

Feature flags
=============

Set flags in `settings.php`/`additional.php`:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['<flag>'] = true;

Flags are read when the DI container and the middleware stack are built,
so flush the caches after changing one. All flags, their effect and their
availability per release: :ref:`ref-feature-flags`.

.. _preview:

Preview of hidden pages
=======================

The frontend can preview hidden pages when the backend cookie reaches the
API: backend and frontend must share a root domain, and the cookie must be
scoped to it via `cookieDomain` — globally, or per site with the
`headless.cookieDomainPerSite` feature flag. The cookie setup, including
the stale `be_typo_user` cookie pitfall, is described in :ref:`multisite`.
The frontend has to forward the backend cookie with its API requests —
server-side rendering frontends such as nuxt-typo3 must be configured to
do so, see :ref:`cookies`.

Workspace preview
-----------------

Previewing workspace versions from the Workspaces module works out of the
box — the JSON output renders the workspace overlay of the content, and
backend preview URLs are rewritten to the frontend domain. The cookie
requirements above apply here too. No feature flag is involved (in 4.x
this ran through workspace XClasses, likewise enabled automatically).

Backend preview of mixed-mode sites
-----------------------------------

On a `headless: 2` site, page links generated in the backend (such as the
"View webpage" preview) stay on the TYPO3 host by default. The browser sends no JSON `Accept` header, so the preview renders
as HTML. To open previews in the frontend application instead, set in the
site's `settings.yaml`:

.. code-block:: yaml

   headless:
     preview:
       overrideMode: 1

The setting does not pick a response format; it decides where
backend-generated page links point. With `1` they are rewritten to
`frontendBase`, and the frontend application renders the preview from the
JSON. With `0` (the default) they are left on the TYPO3 host. Sites with
`headless: 0` or `headless: 1` are not affected, and neither are source URLs
in the redirects module, which are rewritten for `headless: 1` sites only.

.. _xmlsitemap:

XML sitemap
===========

Since 4.0 the XML sitemap is plain core `EXT:seo` — headless only ships the
rendering templates. The links in the sitemap *index* (`/sitemap.xml`, the
`t3://page?uid=current&type=…` sitemap-type links) resolve through
`frontendApiProxy`; all other page links use `frontendBase`. If the index
links point at the API host instead of the frontend, set `frontendApiProxy`
in the site's `config.yaml` (the field shows up in the backend site module
only with `headless.storageProxy` enabled — editing the YAML directly always
works), or point the sitemap at `frontendBase` via `settings.yaml`:

.. code-block:: yaml

   headless:
     sitemap:
       key: frontendBase

On `headless: 2` sites the sitemap links are not rewritten at all: the
rewrite only runs for JSON requests, and a crawler fetching `/sitemap.xml`
sends no JSON `Accept` header (the mixed set also loads the sitemap
templates only for JSON requests).

The sitemap-index links are detected by their page type. If your sitemap
uses a custom typeNum, also set it (default `1533906435`) — otherwise the
index links are treated like any other page link and point at `frontendBase`
instead of `frontendApiProxy`:

.. code-block:: yaml

   headless:
     sitemap:
       type: '2400000000'
