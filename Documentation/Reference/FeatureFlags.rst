.. _ref-feature-flags:

========================
Reference: Feature Flags
========================

Every flag is a boolean, off by default. Enable one in
`settings.php`/`additional.php`:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.storageProxy'] = true;

.. important::

   Most flags are read when the DI container (`Configuration/Services.php`),
   the middleware stack (`Configuration/RequestMiddlewares.php`) and the
   site configuration TCA are built, so their cached state wins until the
   caches are rebuilt. `headless.assetsCacheBusting` (`FileUtility`) and
   `headless.prettyPrint` (`JsonEncoder`, instantiated per request) are read
   at runtime. Run `bin/typo3 cache:flush` after changing any flag.

Active in 5.x
=============

.. _feature-flag-storageproxy:

headless.storageProxy
---------------------

In headless frontend requests, rewrites the public URL of every file in a
local, public storage — originals as well as processed files — to the
frontend host. The `ProxyResourcePublicUrlListener` (on the core
`GeneratePublicUrlForResourceEvent`, identifier
`headless/ProxyResourcePublicUrl`) builds the URL from:

* the storage's `baseUri`, or its `basePath` when `pathType` is
  `relative`: `frontendBase` host + `frontendApiProxy` path + storage path,
  e.g. `https://www.example.com/api/fileadmin/…`;
* for storages with neither: `frontendFileApi` + file identifier.

A URL another listener already set is left alone. The flag also adds the
`frontendApiProxy` and `frontendFileApi` fields to the site configuration
module, and aliases :php:`\FriendsOfTYPO3\Headless\Resource\Service\HeadlessImageService`
over the core :php:`\TYPO3\CMS\Extbase\Service\ImageService`, which strips
the proxy prefix from image `src` values so proxied URLs still resolve to
files. Setup and per-site examples: :ref:`storage-proxy`.

.. _feature-flag-elementbodyresponse:

headless.elementBodyResponse
----------------------------

Adds the `headless/cms-frontend/element-body-response` middleware. On a
`POST`, `PUT` or `DELETE` request whose body carries `responseElementId`
(a content element uid), the response contains only that element instead
of the whole page — clean plugin output for SPA form handling:

.. code-block:: none

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&tx_form_formframework[email]=email&...

Set `responseElementRecursive=1` to match an element nested inside another
one. The middleware acts only when headless mode applies to the request; on
a mixed-mode site the POST must carry the Accept header described in
:ref:`configuration-mixed-accept`.

.. _feature-flag-overridefluidtemplates:

headless.overrideFluidTemplates
-------------------------------

Aliases :php:`\FriendsOfTYPO3\Headless\View\HeadlessViewFactory` over the
core :php:`\TYPO3\CMS\Core\View\ViewFactoryInterface`. The factory returns
:php:`\FriendsOfTYPO3\Headless\View\HeadlessPhpView` (raw-PHP templates)
only for views with format `php` in headless frontend requests — for
Extbase plugins only when the plugin's TypoScript sets `format = php`.
Whenever format `php` is requested but :php:`HeadlessPhpView` does not
apply (flag off, no request, backend context, headless mode not active, or
an Extbase request without TypoScript `format = php` — e.g. a
request-supplied `format=php`), the factory drops the format and the core
Fluid factory renders the regular `.html` template; a `.php` file is never
parsed by Fluid. Every other view is created by the core Fluid factory
unchanged. Raw-PHP templates apply no
output escaping; usage and safety rules: :ref:`developer-php-templates`.

.. _feature-flag-cookiedomainpersite:

headless.cookieDomainPerSite
----------------------------

Adds the `CookieDomainPerSite` middleware to the frontend and backend
stacks (before authentication) and a `cookieDomain` field to the site
configuration. The middleware picks the site whose `base` (or base variant)
host matches the request host and sets its `cookieDomain` for the request;
without a match the global `cookieDomain` settings apply. See
:ref:`multisite`.

.. _feature-flag-assetscachebusting:

headless.assetsCacheBusting
---------------------------

Appends `?<mtime>` (the file's `modification_date`, else `tstamp`) to the
`url`/`publicUrl` of every file rendered by `FileUtility`, except YouTube
and Vimeo media. Per processor, the same is available with
`processingConfiguration.cacheBusting = 1`.

.. _feature-flag-prettyprint:

headless.prettyPrint
--------------------

Adds `JSON_PRETTY_PRINT` to every `JsonEncoder` output. For debugging only.

Availability by version
=======================

Removed in 5.x: `headless.redirectMiddlewares` (the redirect middlewares are
always active), `headless.jsonViewModule` (module discontinued) and
`headless.nextMajor` (unused). See the
`upgrade guide <https://github.com/TYPO3-Headless/headless/blob/master/UPGRADE.md>`__.

.. t3-field-list-table::
   :header-rows: 1

   -  :Header1:   Flag
      :Header2:   2.x
      :Header3:   3.x
      :Header4:   4.x
      :Header5:   5.x

   -  :Header1:   FrontendBaseUrlInPagePreview
      :Header2:   available
      :Header3:   removed
      :Header4:   removed
      :Header5:   removed

   -  :Header1:   headless.frontendUrls
      :Header2:   >= 2.5
      :Header3:   available
      :Header4:   removed
      :Header5:   removed

   -  :Header1:   headless.storageProxy
      :Header2:   >= 2.4
      :Header3:   available
      :Header4:   available
      :Header5:   available

   -  :Header1:   headless.redirectMiddlewares
      :Header2:   >= 2.5
      :Header3:   available
      :Header4:   available
      :Header5:   removed (middlewares always active)

   -  :Header1:   headless.nextMajor
      :Header2:   >= 2.2
      :Header3:   currently not used
      :Header4:   currently not used
      :Header5:   removed

   -  :Header1:   headless.elementBodyResponse
      :Header2:   >= 2.6
      :Header3:   available
      :Header4:   available
      :Header5:   available

   -  :Header1:   headless.simplifiedLinkTarget
      :Header2:   >= 2.6
      :Header3:   removed
      :Header4:   not available
      :Header5:   not available

   -  :Header1:   headless.jsonViewModule
      :Header2:   not available
      :Header3:   >= 3.0
      :Header4:   >= 3.0
      :Header5:   removed (module discontinued)

   -  :Header1:   headless.workspaces
      :Header2:   not available
      :Header3:   >= 3.1
      :Header4:   removed (auto-on with EXT:workspaces)
      :Header5:   removed (works without a flag)

   -  :Header1:   headless.pageTitleProviders
      :Header2:   not available
      :Header3:   not available
      :Header4:   >= 4.2.3 <= 4.4
      :Header5:   removed

   -  :Header1:   headless.overrideFluidTemplates
      :Header2:   not available
      :Header3:   not available
      :Header4:   available
      :Header5:   available (reworked)

   -  :Header1:   headless.cookieDomainPerSite
      :Header2:   not available
      :Header3:   not available
      :Header4:   available
      :Header5:   available

   -  :Header1:   headless.assetsCacheBusting
      :Header2:   not available
      :Header3:   not available
      :Header4:   available
      :Header5:   available

   -  :Header1:   headless.prettyPrint
      :Header2:   not available
      :Header3:   not available
      :Header4:   available
      :Header5:   available
