.. _installation:
.. _quickstart:

===============
Getting Started
===============

.. toctree::
   :hidden:

   Frontend

From a fresh TYPO3 installation to the first JSON response.

1. Install
==========

.. code-block:: bash

   composer require friendsoftypo3/headless

Classic mode: unpack the release into `typo3conf/ext/headless` and
activate it in the Extensions module. The extension ships
without `ext_emconf.php`; TYPO3 v14 reads its metadata from
`composer.json`.

2. Create a root page + site config
===================================

In the backend, create a new page at the root level (the *site root*).
Then go to :guilabel:`Sites > Setup` and add a site configuration
pointing at that page.

.. figure:: /Images/Installation/RootPage.png
   :alt: Page tree with the "Headless API" page at the root level

.. important::

   Give the API site its own host as `base`, like
   `https://api.example.com` — not a path on the frontend domain
   (`https://example.com/api`). If the browser should reach the API through
   the frontend's origin, let the frontend server proxy a path to the API
   host and set it as `frontendApiProxy` (:ref:`cors`).

3. Add the headless set and switch the site to JSON
===================================================

Both settings are part of the site configuration:

.. code-block:: yaml

   # config/sites/<identifier>/config.yaml
   rootPageId: 1
   base: https://api.example.com
   dependencies:
     - friendsoftypo3/headless
   # 0 = NONE, 1 = FULL (always JSON),
   # 2 = MIXED (Accept-driven, with the headless-mixed set)
   headless: 1

Do not add a root sys_template record (:ref:`configuration`). The other
sets, the statics for sites without sets and the mixed-mode `Accept` rule
(:ref:`configuration-mixed-accept`) are described there as well.

4. Drop a content element on the page
=====================================

Add a *Text* element (or any other standard content element) to the page
and save.

5. Fetch the page
=================

.. code-block:: bash

   curl https://api.example.com/

The response looks like this (abbreviated):

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
  as the whole header (:ref:`configuration-mixed-accept`). Harmless on a
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
`/?type=834`  `initialData`: the primary navigation, the language
              menu and the login state. Fetch it once per language,
              not per page — see :ref:`frontend-initial-data`.
`/?type=835`  `headless_domains`: the sites of the instance that have
              headless enabled, with their frontend and API URLs —
              see :ref:`frontend-multisite`.
============  =======================================================

.. _cookies:

Cookies
-------

Three TYPO3 cookies matter for a headless frontend: `fe_typo_user`
(frontend login session), `be_typo_user` (backend session — enables the
preview of hidden pages and workspaces) and `typo3nonce_*` (signs the
`__RequestToken` of login and form submissions). A browser only sends
them to the host that set them, so:

* Requests the **browser** makes directly to the API host need
  `credentials: 'include'` (fetch, nuxt-typo3: `typo3.api.credentials`) or
  `withCredentials: true` (axios). `api.example.com` and `example.com` are
  same-site, so the cookies travel with such requests either way. A shared
  `cookieDomain` is only needed when the same session has to be visible on
  the frontend host too — server-side rendering plus client requests, or
  backend preview; see :ref:`multisite`.
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
