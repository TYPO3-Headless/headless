.. _caching:

=======
Caching
=======

Page cache
==========

A JSON page is cached like an HTML page: the encoded response is stored in
the `pages` cache with the lifetime derived from the page properties and
`config.cache_period`, and it is invalidated by the same operations (saving
records on the page, "Flush frontend caches", `cache:flush`). No
headless-specific cache exists.

Uncached fields
===============

Fields rendered by a `USER_INT` cObject — Extbase plugins with non-cacheable
actions, `EXT:form`, `EXT:felogin` — are not part of the cached page. TYPO3
stores an `<!--INT_SCRIPT.…-->` placeholder and fills it on every request.
Inside a JSON document that placeholder would sit in a string, so the `JSON`
cObject wraps it in `HEADLESS_INT_START<<…>>HEADLESS_INT_END` markers
(`HEADLESS_INT_NULL` with `ifEmptyReturnNull = 1`, nested variants for
`JSON` objects rendered inside `USER_INT` output). After TYPO3 has rendered
the uncached parts, the `headless/cms-frontend/prepare-user-int` middleware
replaces each marker with the JSON-encoded value: a string, `null`, or the
object itself when the plugin already returned JSON.

The middleware runs only when headless mode applies to the request. Markers
that show up verbatim in the output mean the site loads the headless
TypoScript but runs with `headless: 0`.

A page with a `USER_INT` field is still cached; only that field is computed
per request. The `seo` object is rebuilt on those requests too, because page
title and meta tags may depend on the uncached plugin.

HTTP caching
============

`lib.headlessPage` sets `config.sendCacheHeaders = 1`. Cacheable pages
requested without a user session are sent with `Cache-Control`, `Expires`,
`ETag` and `Last-Modified`, so browsers, reverse proxies and CDNs may cache
them for the remaining page cache lifetime. `lib.headlessPage` adds
`Content-Type: application/json; charset=utf-8` to that set.

On a `headless: 2` site one URL returns HTML or JSON depending on the
`Accept` header, so a shared cache has to key on that header. Headless does
not send `Vary: Accept` itself; add it in the site package, where it applies
to the JSON and the HTML `page` alike:

.. code-block:: typoscript

   page.config.additionalHeaders.20.header = Vary: Accept

Requests with a frontend or backend user session get
`Cache-Control: private, no-store`, which keeps previews and personalised
content out of shared caches.

Per-field caching
=================

An expensive field inside a `JSON` object can cache its own result with the
core `stdWrap.cache` property, independent of the page cache. The `stdWrap`
of a `JSON` object runs on the encoded string, so the cached value is the
JSON fragment:

.. code-block:: typoscript

   page.10.fields.related = JSON
   page.10.fields.related {
     dataProcessing.10 = headless-database-query
     dataProcessing.10 {
       table = tx_myext_domain_model_item
       pidInList = 42
       as = items
     }
     stdWrap.cache {
       key = related-items-{page:uid}
       key.insertData = 1
       lifetime = 3600
     }
   }

Do not cache fields that contain `USER_INT` output this way: the markers
would be stored instead of the rendered value.
