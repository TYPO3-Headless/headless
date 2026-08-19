.. _multisite:

==============================
Multi-Site & URL Configuration
==============================

The headless setup typically has **two domains** — one for the API
(TYPO3 backend) and one for the frontend app. This page is the single
source of truth for how `EXT:headless` rewrites URLs, manages cookies
and routes assets across sites.

Glossary
========

============================  ================================================
Key                           Meaning
============================  ================================================
`base`                        Public URL of the TYPO3 site (the API).
`frontendBase`                Public URL of your SPA / frontend app. Used by
                              `UrlUtility` to rewrite links in the JSON
                              response.
`frontendApiProxy`            Public URL of the API as seen by browsers
                              going through the frontend's reverse proxy
                              (e.g. `https://example.com/headless`).
`frontendFileApi`             Public URL for processed files (images, PDFs).
                              Used together with the
                              `headless.storageProxy` feature flag.
`cookieDomain`                Domain to scope session cookies to. Needed
                              when API and frontend share a root domain.
`baseVariants`                Per-environment overrides of any of the
                              above, gated by an expression-language
                              condition.
============================  ================================================

Single-domain setup
===================

API and frontend both on the same host. No URL rewriting needed.

.. code-block:: yaml

   # config/sites/<identifier>/config.yaml
   rootPageId: 1
   base: https://example.com
   headless: 1

Two-domain setup (typical)
==========================

API on `api.example.com`, frontend on `example.com`. Set
`frontendBase` so URLs in the JSON response point at the frontend.

.. code-block:: yaml

   rootPageId: 1
   base: https://api.example.com
   headless: 1
   frontendBase: https://example.com
   # Optional, used with the headless.storageProxy feature flag:
   # processed-file URLs are served through these proxy paths
   # (page/typolink URLs always use frontendBase):
   frontendApiProxy: https://example.com/headless
   frontendFileApi:  https://example.com/headless/fileadmin

Now any `typolink` or page URL in the JSON response is rewritten from
`https://api.example.com/...` to `https://example.com/...`.

Multi-language overrides
========================

Each language can override the URL keys independently:

.. code-block:: yaml

   languages:
     - languageId: 0
       title: English
       base: /
       locale: en_US.UTF-8
       frontendBase: https://example.com
     - languageId: 1
       title: Deutsch
       base: /de/
       locale: de_DE.UTF-8
       frontendBase: https://example.de

Per-environment overrides (`baseVariants`)
==========================================

Same site, different dev/stage/prod values:

.. code-block:: yaml

   baseVariants:
     - base: https://api.dev.example.com
       condition: 'applicationContext == "Development"'
       frontendBase: https://dev.example.com
       frontendApiProxy: https://dev.example.com/headless
     - base: https://api.stage.example.com
       condition: 'applicationContext == "Staging"'
       frontendBase: https://stage.example.com
       frontendApiProxy: https://stage.example.com/headless

`condition` is evaluated by `TYPO3\CMS\Core\ExpressionLanguage\Resolver`
with the `site` scope — available are the default variables
(`applicationContext`, `typo3`, `date`, `features`) and functions like
`getenv("…")`; `request` is **not** available here.

The first variant whose condition matches wins, and its values are read
verbatim: a key missing from the matching variant resolves to an empty
string, it does **not** fall back to the site-level value — repeat every
key in every variant.

Shared root domain & cookies
============================

When `api.example.com` and `example.com` share a root domain
(`example.com`), the browser can carry session cookies between them
**only if** the cookie's `Domain` attribute is set to the shared root.

Option A — set globally (simple, single-site instance):

.. code-block:: php

   // config/system/additional.php
   $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain'] = '.example.com';

Option B — per-site (multi-domain instance): enable
`headless.cookieDomainPerSite` and put `cookieDomain` in the site
config. The `CookieDomainPerSite` middleware looks up the site by
**exact request host** and injects its `cookieDomain` for the
duration of the request.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.cookieDomainPerSite'] = true;

.. code-block:: yaml

   baseVariants:
     - base: https://api.example.com
       condition: 'applicationContext == "Production"'
       cookieDomain: .example.com

.. important::

   If you changed `cookieDomain` after a backend login, remove the
   stale `be_typo_user` cookie from your browser or you won't be able
   to log back in.

Hidden-page preview
===================

Preview of hidden pages relies on the same cookie flow as above. As
long as the frontend forwards all cookies to the API request, the
backend user's preview cookie reaches TYPO3 and hidden pages render.

.. note::

   If you have a truly multi-domain setup (e.g. `api1.example1.com`
   and `api2.example2.com`, no shared root), per-domain cookies are
   not portable. You'll need custom middleware to bridge auth, or
   token-based auth on the frontend side.

XML sitemap URLs
================

The links in the sitemap *index* (the `t3://page?uid=current&type=…`
sitemap-type links) resolve through `frontendApiProxy`; all other page
links use `frontendBase`. To make the index links use `frontendBase`
too, set:

.. code-block:: yaml

   # config/sites/<identifier>/settings.yaml
   headless:
     sitemap:
       key: frontendBase

Index links are matched by the sitemap page type. With a custom sitemap
typeNum, set `headless.sitemap.type` (default `1533906435`) as well, or
the index links keep pointing at the API host:

.. code-block:: yaml

   headless:
     sitemap:
       type: '2400000000'

Storage proxy (asset routing)
=============================

`headless.storageProxy` plus `frontendFileApi` in the site config
makes processed-file URLs (images, PDFs) point at the frontend's
proxy instead of the TYPO3 fileadmin. Useful when you want the
browser to fetch assets from the same origin as the SPA.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.storageProxy'] = true;
