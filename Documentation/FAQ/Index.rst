.. _faq:

===============
FAQ
===============

.. contents::
   :local:
   :depth: 3

How to use EXT:felogin?
-----------------------

Using `EXT:felogin` with the headless extension follows the standard setup as detailed in the `felogin documentation <https://docs.typo3.org/c/typo3/cms-felogin/main/en-us/Index.html>`__. The headless-specific JSON output, and how to test the two-step login flow with `curl`, is described in :ref:`integrations-felogin`.

Does EXT:headless work with other extensions?
---------------------------------------------

Yes, the output of virtually any extension can be rendered into the JSON response. For detailed information, refer to the :ref:`integration of external plugins <developer-plugin-external>` section of this documentation. Additionally, you can review the code of `headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__ as an example of how this integration works.

How to handle redirects in a headless setup?
--------------------------------------------

The frontend application performs the actual redirect: TYPO3 returns JSON
(`{ "redirectUrl": "...", "statusCode": 301 }`) instead of an HTTP `30x`
response.

On headless 5.x (TYPO3 v14) this is always on for shortcut, mount-point and
site-base redirects; redirect-manager records additionally need
`EXT:redirects` installed — no feature flag. See :ref:`integrations-redirects`
for the response shape and customisation.

On headless 4.x and below, enable it with the `headless.redirectMiddlewares`
feature flag:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.redirectMiddlewares'] = true;

Can I use custom fields or content elements with EXT:headless?
--------------------------------------------------------------

Yes — the JSON shape is plain TypoScript. Adding a field to every page or
content element is shown in :ref:`developer-snippets`; custom content
elements, Extbase plugins and third-party plugins in
:ref:`developer-custom-content`.

How to configure language and translation settings?
---------------------------------------------------

Languages are the site configuration's `languages` — nothing headless-specific.
TYPO3 resolves the language from the URL (`/de/about`), not from an
`Accept-Language` header, so the frontend requests the language base it wants.
Every page response carries the available languages under `i18n` (rendered by
:ref:`LanguageMenuProcessor <dataprocessors-languagemenuprocessor>`), and
`?type=834` returns them without page content — see :ref:`requests`.
Translation fallback and overlay behaviour follow the core `fallbackType` /
`fallbacks` settings of each language.

How to enable clean output for plugins in EXT:headless?
-------------------------------------------------------

Enable the `headless.elementBodyResponse` feature flag and send
`responseElementId` (plus `responseElementRecursive=1` for nested
elements) in the body of the POST/PUT/DELETE request. The feature flag
section in :ref:`configuration` has the example request and the mixed-mode
caveat.


.. _troubleshooting:

Troubleshooting
---------------

**"No page configured for type=0"**
   The site has no `page` object: the set is missing from `dependencies`, a
   root sys_template record with "Clear" flags wipes the set TypoScript, or
   the site uses the mixed set and the request came without the JSON `Accept`
   header while no HTML `page` is configured.

**HTML instead of JSON**
   Mixed-mode site and the first `Accept` value is not exactly
   `application/json`; the axios/fetch default list renders HTML. Send the
   header explicitly. See :ref:`requests`.

**Forms, login or menus render HTML fragments, or `HEADLESS_INT_START` markers appear in the output**
   The headless TypoScript is loaded but the site runs with `headless: 0`, so
   the PHP-side integrations and the `USER_INT` middleware stay off. Set
   `headless: 1` or `2`.

**Links point at the API host**
   `frontendBase` is missing for that site, language or `baseVariants` entry.
   Variants do not fall back to the site-level value. See :ref:`multisite`.

**Real `30x` response instead of the JSON redirect envelope**
   The request was outside headless mode: mode `0`, or mixed mode without
   the `Accept` header. See :ref:`integrations-redirects`.

**`seo` is missing or empty**
   The `seo.fields.title` placeholder was removed from `page.10.fields`. The
   `MetaHandler` only runs when the rendered JSON has a `seo.title` key.

**The response is `[]`**
   The encoder caught a `JsonException`, usually invalid UTF-8 in a record,
   logged it as critical and returned `[]`. Check the TYPO3 log.

**Logged-in user is anonymous, hidden pages stay hidden, login works with curl but not through the frontend**
   The cookies do not reach TYPO3. Browser requests to the API host need
   `credentials: 'include'` (fetch) or `withCredentials: true` (axios) and,
   across hosts, a shared `cookieDomain`. Requests made by the frontend server
   (server-side rendering) carry no browser cookies at all: forward the
   incoming `Cookie` header to TYPO3 and relay `Set-Cookie` back. nuxt-typo3:
   `typo3.api.proxyReqHeaders: ['cookie']` and
   `typo3.api.proxyHeaders: ['set-cookie']`. See :ref:`requests`.

**Login or form submission is rejected before validation**
   A hidden field (`__RequestToken`, `__trustedProperties`, `__state`, the
   honeypot) was not echoed back, or the `typo3nonce_*` cookie from the GET
   did not travel with the POST. See :ref:`requests` and
   :ref:`integrations-form`.

**A CDN or proxy serves the wrong format on a mixed-mode site**
   The cache does not key on `Accept`. Add `Vary: Accept` to the page, for
   the JSON and the HTML representation. See :ref:`caching`.

**The browser console shows a CORS error**
   The API host answers without `Access-Control-Allow-Origin`. Use the proxy
   path or add the headers. See :ref:`cors`.

**Backend preview of a mixed-mode site shows HTML**
   That is the default. Set `headless.preview.overrideMode: 1` in the site's
   `settings.yaml` (:ref:`preview`).

**Images return 404 with `headless.storageProxy`**
   The URL is rewritten to `frontendFileApi`, the files stay where they are.
   The frontend server has to proxy that path to the TYPO3 `fileadmin`
   directory.
