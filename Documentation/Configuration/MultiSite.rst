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
`base`              Public URL of the TYPO3 site (the API), on its
                    own host.
`frontendBase`      Public URL of your SPA / frontend app. Used by
                    :php:`\FriendsOfTYPO3\Headless\Utility\UrlUtility`
                    to rewrite links in the JSON response.
`frontendApiProxy`  Public URL of the API as seen by browsers
                    going through the frontend's reverse proxy
                    (e.g. `https://example.com/headless`). Also
                    used for the sitemap index links
                    (:ref:`xmlsitemap`), `api.baseURL` of
                    `?type=835` and storage proxy URLs
                    (:ref:`storage-proxy`).
`frontendFileApi`   Fallback base URL for files of storages
                    without a base URI or relative base path,
                    with `headless.storageProxy` enabled
                    (:ref:`storage-proxy`).
`cookieDomain`      Domain to scope session cookies to. Read only
                    with the `headless.cookieDomainPerSite`
                    feature flag enabled (see below).
`baseVariants`      Per-environment overrides of any of the
                    above, gated by an expression-language
                    condition.
==================  ==============================================

Two-domain setup
================

API on `api.example.com`, frontend on `example.com`. The API site's `base`
is its own host; set `frontendBase` so URLs in the JSON response point at
the frontend. If the frontend server proxies a path such as
`https://example.com/headless` to the API, set it as `frontendApiProxy`
(see :ref:`cors`). Excerpt of the site configuration (`dependencies` and
`languages` as usual):

.. code-block:: yaml

   rootPageId: 1
   base: https://api.example.com
   headless: 1
   frontendBase: https://example.com
   # Optional: the API as seen through the frontend's proxy
   frontendApiProxy: https://example.com/headless

Every typolink and page URL in the JSON response is then rewritten from
`https://api.example.com/...` to `https://example.com/...`.

Multi-language overrides
========================

Each language can override the URL keys independently (excerpt):

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

Same site, different dev/stage/prod values (excerpt):

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

`condition` is evaluated by :php:`\TYPO3\CMS\Core\ExpressionLanguage\Resolver`
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

Requests made by the frontend server (server-side rendering) carry no
browser cookies, whatever the domain setup; forwarding them is described in
:ref:`cookies`.

Option A — set globally (simple, single-site instance; note the leading dot).
`SYS` covers both the frontend (`fe_typo_user`) and the backend
(`be_typo_user`) session cookie; `FE`/`BE` override it per context:

.. code-block:: php

   // config/system/additional.php
   $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = '.example.com';

Option B — per-site (multi-domain instance): enable
`headless.cookieDomainPerSite` and put `cookieDomain` in the site
config. The :php:`\FriendsOfTYPO3\Headless\Middleware\CookieDomainPerSite`
middleware looks up the site by
**exact request host** and injects its `cookieDomain` for the
duration of the request.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.cookieDomainPerSite'] = true;

.. code-block:: yaml

   base: https://api.example.com
   frontendBase: https://example.com
   frontendApiProxy: https://example.com/headless
   cookieDomain: .example.com

`cookieDomain` follows the same `baseVariants` rule as the URL keys: if a
variant matches, put `cookieDomain` into that variant next to its
`frontendBase` and `frontendApiProxy`. A variant carrying only
`cookieDomain` leaves both URL keys empty in that environment.

.. important::

   If you changed `cookieDomain` after a backend login, remove the
   stale `be_typo_user` cookie from your browser or you won't be able
   to log back in.

Preview of hidden pages and workspaces depends on this cookie flow — see
:ref:`preview`.

.. note::

   If you have a truly multi-domain setup (e.g. `api1.example1.com`
   and `api2.example2.com`, no shared root), per-domain cookies are
   not portable. Bridging authentication then needs custom middleware
   or token-based authentication in the frontend.

.. _cors:

Cross-origin requests (CORS)
============================

A browser on `https://example.com` calling `https://api.example.com` makes a
cross-origin request. There are two ways to handle it:

* **Proxy path.** The frontend server forwards `https://example.com/headless/*`
  to the API, and `frontendApiProxy` is set to that path. The browser only
  talks to its own origin: frontend-session cookies work without a shared
  `cookieDomain`, and no CORS headers are needed. Backend preview still
  needs `cookieDomain`, because `be_typo_user` is set on the API host at
  backend login. Prefer this whenever the frontend has a server component.
* **CORS headers.** When the browser calls the API host directly, TYPO3 has to
  answer with `Access-Control-Allow-Origin: https://example.com` — the exact
  origin, not `*`, as soon as cookies are involved — and
  `Access-Control-Allow-Credentials: true`, plus `Access-Control-Allow-Headers`
  for custom request headers. Form-encoded POSTs are simple requests and need
  no preflight; requests with custom headers or a JSON body trigger an
  `OPTIONS` preflight that must be answered as well.

Set the headers in the web server, which also covers redirect envelopes and
error responses. TypoScript covers page responses only (index `10` is taken
by the shipped `Content-Type` header, `40` by the `Vary` header suggested in
:ref:`caching`):

.. code-block:: typoscript

   page.config.additionalHeaders {
     20.header = Access-Control-Allow-Origin: https://example.com
     30.header = Access-Control-Allow-Credentials: true
   }

.. _storage-proxy:

Storage proxy (asset routing)
=============================

With the `headless.storageProxy` feature flag, file URLs (originals and
processed files) of local, public storages point at the frontend instead
of the TYPO3 host. Use it when the browser should fetch assets from the
same origin as the SPA.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.storageProxy'] = true;

The rewrite applies only to frontend requests in headless mode. The new
base URL is built per storage:

* Storage with a base URI or a relative base path (e.g. `fileadmin/`):
  host of `frontendBase` + path of `frontendApiProxy` + storage path —
  `https://example.com/headless/fileadmin/…` for the two-domain example
  above.
* Any other storage: `frontendFileApi`, if set; otherwise the URL is left
  unchanged.

The files stay where they are: the frontend server has to proxy these paths
to the TYPO3 host.
