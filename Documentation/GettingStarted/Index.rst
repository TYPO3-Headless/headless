.. _installation:
.. _quickstart:

=================
Getting Started
=================

From a fresh TYPO3 installation to the first JSON response.

1. Install
==========

.. code-block:: bash

   composer require friendsoftypo3/headless

Composer is the recommended way. Classic mode still works but takes
manual steps: download the release, unpack it to
`typo3conf/ext/headless`, then activate it in the Extension Manager.

2. Create a root page + site config
====================================

In the backend, create a new page at the root level (the *site root*).
Then go to *Site Management → Sites* and add a site configuration
pointing at that page.

.. important::

   Serve the API from a dedicated URL like `https://api.example.com`.
   Paths on the main domain (`https://example.com/api`) can lead to
   unexpected behaviour.

3. Add the headless set and switch the site to JSON
====================================================

Both settings are part of the site configuration:

.. code-block:: yaml

   # config/sites/<identifier>/config.yaml
   rootPageId: 1
   base: https://api.example.com
   dependencies:
     - friendsoftypo3/headless
   headless: 1  # 0 = NONE, 1 = FULL (always JSON), 2 = MIXED (Accept-driven)

Do not add a root sys_template record: sites using sets don't need one,
and its "Clear" flags would wipe the set TypoScript.

The other sets (legacy 4.x response, mixed mode), the sys_template statics
for sites without sets and the exact `Accept` header rule of mixed mode
are described in :ref:`configuration`.

4. Drop a content element on the page
======================================

Add a *Text* element (or any other standard content element) to the page
and save.

5. Fetch the page
==================

.. code-block:: bash

   curl https://api.example.com/

The response looks like this:

.. code-block:: json

   {
     "id": 1,
     "slug": "/",
     "seo": { "title": "Home" },
     "breadcrumbs": [{ "title": "Home", "link": "/" }],
     "content": {
       "colPos0": [{ "id": 1, "type": "text", "content": { "bodytext": "…" } }]
     }
   }

See :ref:`ref-typoscript` for what each field means and where to
override it.

.. _requests:

Making requests
===============

The API is the TYPO3 site itself: every page URL of the site returns that
page as JSON, so the frontend requests exactly the URL a browser would —
including language prefixes (`/de/about`) and query strings for plugins.

.. code-block:: bash

   curl -H 'Accept: application/json' https://api.example.com/de/about

Headers
-------

* `Accept: application/json` — required on a `headless: 2` (mixed) site,
  and then it must be the *first* Accept value, exactly. Harmless on a
  `headless: 1` site, which answers JSON regardless.
* `Content-Type: application/x-www-form-urlencoded` (or
  `multipart/form-data` for uploads) for POST requests to forms and
  plugins — bodies are regular TYPO3 request bodies, not JSON, and every
  hidden field must be echoed back verbatim (see :ref:`integrations-form`
  and :ref:`integrations-felogin`).
* `Cookie` — see `Cookies`_ below.
* A browser calling the API host from another origin also needs CORS
  headers, or a proxy path via `frontendApiProxy` — see :ref:`cors`.

Endpoints
---------

Besides the page response, the shipped TypoScript answers two extra page
types on every page of the site:

============  =======================================================
Request       Returns
============  =======================================================
`/?type=834`  `initialData`: the primary navigation (rootline-based,
              seven levels) and the `i18n` language menu; for a
              logged-in frontend user additionally `user.logged`, for
              a logged-in backend user the `backendEditor` links.
              Fetch it once per language, not per page.
`/?type=835`  `headless_domains`: the sites of the instance that have
              headless enabled, with their frontend and API URLs
              (`RootSitesProcessor` with `DomainSchema`). Lets a
              multi-site frontend pick the right API.
============  =======================================================

Both are cached like regular pages. `?type=834` requests additionally
skip shortcut and mount-point redirect handling.

`/?type=834`, abbreviated (`user` and `backendEditor` appear only for
logged-in users):

.. code-block:: json

   {
     "navigation": [
       {
         "title": "Home", "link": "/", "target": "", "active": 1, "current": 1,
         "spacer": 0, "hasSubpages": 1,
         "children": [
           { "title": "About", "link": "/about", "target": "", "active": 0,
             "current": 0, "spacer": 0, "hasSubpages": 0 }
         ]
       }
     ],
     "i18n": [
       { "languageId": 0, "locale": "en_US.UTF-8", "title": "English",
         "navigationTitle": "English", "hreflang": "en-US", "direction": "ltr",
         "flag": "us", "link": "/", "active": 1, "current": 1, "available": 1 },
       { "languageId": 1, "locale": "de_DE.UTF-8", "title": "Deutsch",
         "navigationTitle": "Deutsch", "hreflang": "de-DE", "direction": "ltr",
         "flag": "de", "link": "/de/", "active": 0, "current": 0, "available": 1 }
     ],
     "user": { "logged": true },
     "backendEditor": { "record": "…", "page": "…" }
   }

`/?type=835` — one entry per site with `headless` enabled; `locales` are the
`typo3Language` keys, `api.baseURL` is the site's `frontendApiProxy`. What it
is for and how to extend it: :ref:`frontend-multisite`.

.. code-block:: json

   [
     {
       "name": "example.com",
       "baseURL": "https://example.com",
       "api": { "baseURL": "https://example.com/headless" },
       "i18n": { "locales": ["default", "de"], "defaultLocale": "default" }
     }
   ]

Cookies
-------

Three TYPO3 cookies matter for a headless frontend: `fe_typo_user`
(frontend login session), `be_typo_user` (backend session — enables the
preview of hidden pages and workspaces) and `typo3nonce_*` (signs the
`__RequestToken` of login and form submissions). A browser only sends
them to the host that set them, so:

* Requests the **browser** makes directly to the API host need
  `credentials: 'include'` (fetch, nuxt-typo3: `typo3.api.credentials`) or
  `withCredentials: true` (axios),
  and — when frontend and API are different hosts — a shared root domain
  with `cookieDomain` set; see :ref:`multisite`.
* Requests the **frontend server** makes (server-side rendering, e.g.
  nuxt-typo3) carry none of the browser's cookies. The frontend has to
  forward the incoming `Cookie` header to TYPO3 and relay TYPO3's
  `Set-Cookie` header back to the browser. nuxt-typo3 configures both
  directions in `nuxt.config.ts` (header names lower-case):

  .. code-block:: ts

     export default defineNuxtConfig({
       typo3: {
         api: {
           proxyReqHeaders: ['cookie'],   // browser -> Nuxt -> TYPO3
           proxyHeaders: ['set-cookie'],  // TYPO3 -> Nuxt -> browser
         },
       },
     })

Without the forwarded cookies a logged-in user looks anonymous to TYPO3,
login and form submissions fail on the request token, and hidden pages
stay hidden.

Next steps
==========

* Render the response in a frontend, with nuxt-typo3 examples: :ref:`frontend`.
* Add a top-level field of your own: :ref:`developer-custom-typoscript`.
* Put your SPA on a separate domain (so links in the response point at
  your frontend, not the API): :ref:`multisite`.
* Listen to a headless event (e.g. inject a signed CDN URL into every
  file payload): :ref:`developer-events`.
* Something does not work: :ref:`troubleshooting`.
