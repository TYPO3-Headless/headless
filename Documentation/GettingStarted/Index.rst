.. _installation:
.. _quickstart:

=================
Getting Started
=================

Five-minute path from a fresh TYPO3 install to your first JSON response.

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

Both switches live in the site configuration:

.. code-block:: yaml

   # config/sites/<identifier>/config.yaml
   rootPageId: 1
   base: https://api.example.com
   dependencies:
     - friendsoftypo3/headless
   headless: 1  # 0 = NONE, 1 = FULL (always JSON), 2 = MIXED (Accept-driven)

In MIXED mode the *first* `Accept` header value must be exactly
`application/json` — `Accept: application/json, text/plain, */*` (the
axios/fetch default) or `application/json; charset=utf-8` renders HTML.

Do not add a root sys_template record — sites using sets don't need one,
and its "Clear" flags would wipe the set TypoScript. Sites not using sets
can select the equivalent statics in a root TypoScript record instead:
"Headless", "Headless Legacy (4.x)" or "Headless - Mixed mode JSON response".

Headless 4.x ships sets too (TYPO3 v13): `friendsoftypo3/headless` (full
response) and `friendsoftypo3/headless-mixed`. On TYPO3 v12 include the
`headless` static template in the root TypoScript record instead.

Set and mode variants are described in :ref:`configuration`.

4. Drop a content element on the page
======================================

Add a *Text* element (or any standard CE) to the page. Save.

5. Fetch the page
==================

.. code-block:: bash

   curl https://api.example.com/

You should get something like:

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

What next?
==========

* Add a top-level field of your own: :ref:`developer-custom-typoscript`.
* Put your SPA on a separate domain (so links in the response point at
  your frontend, not the API): :ref:`multisite`.
* Listen to a headless event (e.g. inject a signed CDN URL into every
  file payload): :ref:`developer-events`.
* Stuck? :ref:`FAQ → Troubleshooting <faq>`.
