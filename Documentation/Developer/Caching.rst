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

A page with a `USER_INT` field is still stored in the page cache; only that
field is computed per request. The `seo` object is rebuilt on those requests
too, because page title and meta tags may depend on the uncached plugin. Such
a page gets no client cache headers, see below.

HTTP caching
============

`lib.headlessPage` sends `Content-Type: application/json; charset=utf-8` on
every response and sets `config.sendCacheHeaders = 1`. Cacheable pages
requested without a logged-in frontend or backend user are sent with
`Expires`, `ETag`, `Cache-Control: max-age=<lifetime>` and `Pragma: public`,
so browsers, reverse proxies and CDNs may cache them for the page cache
lifetime. No `Last-Modified` header is sent. The core option
`config.sendCacheHeadersForSharedCaches` switches to
`Cache-Control: max-age=0, s-maxage=<lifetime>`, so only shared caches keep
the page: `force` always, `auto` only when the request comes through a
configured reverse proxy (`SYS/reverseProxyIP`).

On a `headless: 2` site one URL returns HTML or JSON depending on the
`Accept` header, so a shared cache has to key on that header. Headless does
not send `Vary: Accept` itself; add it in the site package, where it applies
to the JSON and the HTML `page` alike:

.. code-block:: typoscript

   page.config.additionalHeaders.40.header = Vary: Accept

Every other response — logged-in users, workspace previews, and pages with
`USER_INT` fields — gets `Cache-Control: private, no-store`; only the server
page cache applies to them.

Per-field caching
=================

An expensive field inside a `JSON` object can cache its own result with the
core cObject property `cache`, independent of the page cache. The cache is
checked before the field is rendered, so for a `JSON` field a hit skips the
data processors; the cached value is the encoded JSON fragment. On any
other cObject, e.g. `TEXT` with `dataProcessing`, the processors run after
the cObject has been rendered and are not covered by its cache — make the
field a `JSON` object to cache them. Do not use `stdWrap.cache`
for this: the `stdWrap` of a `JSON` object runs only after the processors
and the encoding, so it saves no work.

.. code-block:: typoscript

   page.10.fields.related = JSON
   page.10.fields.related {
     dataProcessing.10 = headless-database-query
     dataProcessing.10 {
       table = tx_myext_domain_model_item
       pidInList = 42
       as = items
       fields {
         title = TEXT
         title.field = title
       }
     }
     cache {
       key = related-items-{page:uid}-{siteLanguage:languageId}
       key.insertData = 1
       lifetime = 3600
     }
   }

Include everything the output depends on in the key — at least the
language, as above.

Do not cache fields that contain `USER_INT` output this way: the markers
would be stored instead of the rendered value.
