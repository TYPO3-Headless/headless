.. _configuration:

===================
Configuration
===================


Site configuration
==================

Headless is enabled per site in `config/sites/<identifier>/config.yaml`:

.. code-block:: yaml

   dependencies:
     - friendsoftypo3/headless
   headless: 1

`dependencies` — pick one site set:

* `friendsoftypo3/headless` — trimmed default response (new projects).
* `friendsoftypo3/headless-legacy` — full 4.x-compatible response (upgrades).
* `friendsoftypo3/headless-mixed` — JSON only for requests sent with exactly
  `Accept: application/json`; everything else renders your own HTML page.

`headless` — run mode: `0` off, `1` always JSON, `2` mixed (Accept-driven —
pair it with the mixed set). A site using sets must not carry a root
sys_template record: its "Clear" flags wipe all set-provided TypoScript.

Headless 4.x ships sets as well (TYPO3 v13): `friendsoftypo3/headless`
(there: the full response) and `friendsoftypo3/headless-mixed`. On TYPO3
v12 include the `headless` static template in the root TypoScript record
instead.

URLs pointing at the frontend domain (`frontendBase`, `frontendApiProxy`,
per-language variants) are covered in :ref:`multisite`.

Automatic integrations
======================

Headless detects installed core extensions and integrates them without
further setup: `EXT:form` (JSON form definitions, form editor additions),
`EXT:felogin` (JSON login plugin), `EXT:redirects` (JSON redirect
envelopes, frontend-aware backend modules) and `EXT:seo`
(canonical/meta-tag managers, hreflang rewriting). Workspace preview needs
no setup either — core rendering plus the backend preview-URL rewrite
cover it. Whether a request gets the headless behaviour follows the
site's mode: always with `headless: 1`, only for exact
`Accept: application/json` requests with `headless: 2`.

Feature flags
=============

Set flags in `settings.php`/`additional.php`:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['<flag>'] = true;

**headless.storageProxy** — serve processed files through the site's
`frontendApiProxy`/`frontendFileApi` instead of the TYPO3 host.

**headless.elementBodyResponse** — on POST/PUT/DELETE requests return only
the element matching `responseElementId` from the request body — clean
plugin output for SPA form handling:

.. code-block:: none

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&tx_form_formframework[email]=email&...

Set `responseElementRecursive=1` to match a nested (child) element.

**headless.overrideFluidTemplates** — swap the core `ViewFactoryInterface`
for `HeadlessViewFactory`: Fluid views render JSON templates, raw-PHP
templates (`HeadlessPhpView`) are supported too.

**headless.cookieDomainPerSite** — derive the auth cookie domain per site
(FE & BE middleware); see :ref:`multisite`.

**headless.assetsCacheBusting** — append the file mtime as a query string
to processed-file URLs.

**headless.prettyPrint** — `JSON_PRETTY_PRINT` on every response (debugging).

Older versions: the per-release availability matrix lives in
:ref:`ref-feature-flags`. The only flag dropped between 4.x and 5.x is
`headless.redirectMiddlewares` — the redirects integration is now
auto-enabled when EXT:redirects is installed.

EXT:form
========

Form integration — JSON form definitions, i18n, decorators, validators and
the `JsonRedirect` finisher — is documented in :ref:`integrations-form`.

Content element categories
==========================

The default `TYPO3 Headless` site set (`friendsoftypo3/headless`) does not render
categories at all: `lib.contentElement` does not define a `categories` field
(and therefore no content element carries one), and the page response does not contain
a `categories` field either. This avoids one `sys_category` join per content
element on every uncached render for projects that do not use categories.

If your project needs them, re-add the field in your site package TypoScript,
loaded after the set. Point `pidInList` at the place your categories are actually
stored — either the storage folder uid, or the current root page with `recursive`
as shown below.

Content element categories:

.. code-block:: typoscript

   lib.contentElement.fields.categories = COA
   lib.contentElement.fields.categories {
       10 = CONTENT
       10 {
           table = sys_category
           select {
               pidInList.data = leveluid : 0
               recursive = 99
               selectFields = sys_category.title
               join = sys_category_record_mm on sys_category_record_mm.uid_local = sys_category.uid
               where {
                   field = uid
                   wrap = AND sys_category_record_mm.tablenames = 'tt_content' AND sys_category_record_mm.uid_foreign=|
               }
           }
           renderObj = TEXT
           renderObj {
               field = title
               wrap = |###BREAK###
           }
       }
       stdWrap.split {
           token = ###BREAK###
           cObjNum = 1 |*|2|*| 3
           1 {
               current = 1
               stdWrap.wrap = |
           }
           2 {
               current = 1
               stdWrap.wrap = ,|
           }
           3 {
               current = 1
               stdWrap.wrap = |
           }
       }
   }

Page categories — the legacy `lib.categories` definition is still shipped
(the legacy and mixed sets load it; the default set does not). Import it,
fix the storage pid the same way, and
add the field back to the page response:

.. code-block:: typoscript

   @import 'EXT:headless/Configuration/TypoScript/Legacy/Categories.typoscript'

   lib.categories.10.select.pidInList >
   lib.categories.10.select.pidInList.data = leveluid : 0
   lib.categories.10.select.recursive = 99

   page.10.fields.categories =< lib.categories

Both snippets render a comma-separated string of category titles, matching the
output of the legacy set — use them when the consuming frontend should not need
any changes.

Alternative: categories as a JSON array
---------------------------------------

If your frontend does not depend on the legacy string format, prefer structured
output. This variant uses the `EXT:headless` DatabaseQueryProcessor and renders
each category as an object, so the field becomes
`"categories": [{"id": 2, "title": "News"}, ...]` instead of `"News,Events"`:

.. code-block:: typoscript

   lib.contentElement.fields.categories = JSON
   lib.contentElement.fields.categories {
       dataProcessing {
           10 = FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor
           10 {
               table = sys_category
               pidInList.data = leveluid : 0
               recursive = 99
               join = sys_category_record_mm ON sys_category_record_mm.uid_local = sys_category.uid
               where.data = field:uid
               where.wrap = sys_category_record_mm.tablenames = 'tt_content' AND sys_category_record_mm.uid_foreign=|
               orderBy = sys_category.sorting
               as = categories
               fields {
                   id = INT
                   id {
                       field = uid
                   }
                   title = TEXT
                   title {
                       field = title
                   }
               }
           }
       }
   }

For page categories use the same definition with
`where.wrap = sys_category_record_mm.tablenames = 'pages' AND sys_category_record_mm.uid_foreign=|`
and assign it to the page response instead:

.. code-block:: typoscript

   page.10.fields.categories = JSON
   page.10.fields.categories {
       dataProcessing {
           # same processor configuration as above, with tablenames = 'pages'
       }
   }

Note this changes the shape of the `categories` field — frontends migrating from
the legacy set must be updated accordingly.



.. _preview:

Preview of hidden pages
=======================

The frontend can preview hidden pages if backend cookies reach it. Since
there are no cross-domain cookies, backend and frontend must share a root
domain (e.g. `api.domain.com` / `domain.com`); set it as `cookieDomain`
(note the leading dot):

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain'] = '.domain.com';

.. important::

   If you are logged into the backend when this changes, delete the
   `be_typo_user` cookie in your browser — the old cookie blocks login.

If the frontend forwards all backend cookies, previewing hidden content
works. For multi-domain setups (`api.domain1.com`/`domain1.com`,
`api.domain2.com`/`domain2.com`) use the `headless.cookieDomainPerSite`
feature flag instead of a static `cookieDomain`.

Workspace preview
-----------------

Previewing workspace versions from the Workspaces module works out of the
box — the JSON output renders the workspace overlay of the content, and
backend preview URLs are rewritten to the frontend domain. The cookie
requirements above apply here too. No feature flag is involved (in 4.x
this ran through workspace XClasses, likewise enabled automatically).

.. _xmlsitemap:

XML sitemap
===========

Since 4.0 the XML sitemap is plain core `EXT:seo` — headless only ships the
rendering templates. If URLs in the sitemap index (`/sitemap.xml`) point at
the API host instead of the frontend, set `frontendApiProxy` in the site's
`config.yaml` (the field shows up in the backend site module only with
`headless.storageProxy` enabled — editing the YAML directly always works),
or point the sitemap at `frontendBase` via `settings.yaml`:

.. code-block:: yaml

  headless:
    sitemap:
      key: frontendBase
