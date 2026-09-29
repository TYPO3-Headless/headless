.. _multisite:

==============================
Multi-Site & URL Configuration
==============================

The headless setup typically has **two domains** — one for the API
(TYPO3 backend) and one for the frontend app. This page describes how
`EXT:headless` rewrites URLs, handles cookies and routes assets across
the two.

Glossary
========

==================  ==============================================
Key                 Meaning
==================  ==============================================
`base`              Public URL of the TYPO3 site (the API).
`frontendBase`      Public URL of your SPA / frontend app. Used by
                    `UrlUtility` to rewrite links in the JSON
                    response.
`frontendApiProxy`  Public URL of the API as seen by browsers
                    going through the frontend's reverse proxy
                    (e.g. `https://example.com/headless`).
`frontendFileApi`   Public URL for processed files (images, PDFs).
                    Used together with the
                    `headless.storageProxy` feature flag.
`cookieDomain`      Domain to scope session cookies to. Needed
                    when API and frontend share a root domain.
`baseVariants`      Per-environment overrides of any of the
                    above, gated by an expression-language
                    condition.
==================  ==============================================

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

Every typolink and page URL in the JSON response is then rewritten from
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

`condition` is evaluated by :php:`TYPO3\CMS\Core\ExpressionLanguage\Resolver`
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
**only if** the cookie's `Domain` attribute is set to the shared root, and
the browser request is sent with credentials (`credentials: 'include'` for
fetch and nuxt-typo3's `typo3.api.credentials`, `withCredentials: true` for
axios).

Requests made by the frontend server (server-side rendering) carry no browser
cookies, whatever the domain setup. The frontend has to forward the incoming
`Cookie` header to TYPO3 and relay TYPO3's `Set-Cookie` header back to the
browser. nuxt-typo3 does both through `nuxt.config.ts`:

.. code-block:: ts

   export default defineNuxtConfig({
     typo3: {
       api: {
         proxyReqHeaders: ['cookie'],   // browser -> Nuxt -> TYPO3
         proxyHeaders: ['set-cookie'],  // TYPO3 -> Nuxt -> browser
       },
     },
   })

Option A — set globally (simple, single-site instance; note the leading dot):

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

.. _cors:

Cross-origin requests (CORS)
============================

A browser on `https://example.com` calling `https://api.example.com` makes a
cross-origin request. There are two ways to handle it:

* **Proxy path.** The frontend server forwards `https://example.com/headless/*`
  to the API, and `frontendApiProxy` is set to that path. The browser only
  talks to its own origin: cookies work without a shared `cookieDomain`, and
  no CORS headers are needed. Prefer this whenever the frontend has a server
  component.
* **CORS headers.** When the browser calls the API host directly, TYPO3 has to
  answer with `Access-Control-Allow-Origin: https://example.com` — the exact
  origin, not `*`, as soon as cookies are involved — and
  `Access-Control-Allow-Credentials: true`, plus `Access-Control-Allow-Headers`
  for custom request headers. Form-encoded POSTs are simple requests and need
  no preflight; requests with custom headers or a JSON body trigger an
  `OPTIONS` preflight that must be answered as well.

Set the headers in the web server, which also covers redirect envelopes and
error responses. TypoScript covers page responses only (index `10` is taken
by the shipped `Content-Type` header):

.. code-block:: typoscript

   page.config.additionalHeaders {
     20.header = Access-Control-Allow-Origin: https://example.com
     30.header = Access-Control-Allow-Credentials: true
   }


Hidden-page preview
===================

Preview of hidden pages relies on the cookie flow above: the backend
user's `be_typo_user` cookie has to arrive with the API request, through the
shared `cookieDomain` for browser requests and through the forwarded `Cookie`
header for server-side requests. See :ref:`requests` for the full list of
cookies involved.

.. note::

   If you have a truly multi-domain setup (e.g. `api1.example1.com`
   and `api2.example2.com`, no shared root), per-domain cookies are
   not portable. Bridging authentication then needs custom middleware
   or token-based authentication in the frontend.

XML sitemap URLs
================

The links in the sitemap *index* resolve through `frontendApiProxy`; all
other page links use `frontendBase`. Switching the index links to
`frontendBase` and handling a custom sitemap typeNum are covered in
:ref:`xmlsitemap`.

Storage proxy (asset routing)
=============================

`headless.storageProxy` plus `frontendFileApi` in the site config
makes processed-file URLs (images, PDFs) point at the frontend's
proxy instead of the TYPO3 fileadmin. Use it when the browser should
fetch assets from the same origin as the SPA.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.storageProxy'] = true;
