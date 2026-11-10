.. _ref-interfaces:

=====================
Reference: Interfaces
=====================

Public interfaces of `EXT:headless`. Inject these rather than the concrete
classes.

Services
========

:php:`\FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface`
   Detect / switch headless mode for a request. Methods: `isEnabled()`,
   `isEnabledFor($request)`, `withRequest($request)`,
   `overrideBackendRequestBySite($site, $language)`.

:php:`\FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface`
   URL rewriting from backend to frontend. Methods: `withSite()`,
   `withRequest()`, `getFrontendUrl()`, `getFrontendUrlWithSite()`,
   `getFrontendUrlForPage()`, `getProxyUrl()`, `getStorageProxyUrl()`,
   `resolveKey()`, `prepareRelativeUrlIfPossible()`. (`withLanguage()`
   exists only on the concrete `UrlUtility`.)

:php:`\FriendsOfTYPO3\Headless\Utility\FileUtilityInterface`
   File / image rendering. Methods: `setRequest()`, `processFile()`,
   `process()`, `processImageFile()`, `processCropVariants()`,
   `getAbsoluteUrl()`, `getErrors()`. `process()` returns the same shape
   that ships in `content.*.media` by default.

:php:`\FriendsOfTYPO3\Headless\Json\JsonEncoderInterface`
   `encode($data, $options = 0)`. Wraps `json_encode` with
   `JSON_HEX_APOS | JSON_HEX_AMP` (not HTML-safe) and the
   :ref:`feature-flag-prettyprint` flag. Does **not** throw on invalid
   data: encoding errors are caught, logged as critical, and `"[]"` is
   returned.

:php:`\FriendsOfTYPO3\Headless\Json\JsonDecoderInterface`
   `decode(array)` recursively unwraps JSON strings nested inside arrays.
   `isJson($mixed)` detects JSON by fully decoding the value after cheap
   pre-checks.

:php:`\FriendsOfTYPO3\Headless\Seo\MetaHandlerInterface`
   `process($request, $content)` — runs the SEO meta-tag pipeline (page
   title, meta registry, hreflang) and merges it under `content.seo`.

Contracts you implement
=======================

:php:`\FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface`
   Site discovery for `RootSitesProcessor` (`siteProvider`). Methods:
   `prepare(array $config, int $siteUid): self`, `getSites(): array`,
   `getPages(): array`, `getCurrentRootPage(): Site`.

:php:`\FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface`
   Shapes the JSON produced per site (`siteSchema`). Method:
   `process(SiteProviderInterface $provider, array $options = []): array`.

:php:`\FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSortingInterface`
   Site order (`sortingImplementation`, used by the default `SiteProvider`).
   Method: `sort(): array`; instantiated with `($sites, $pages,
   $sortingField)`.

:php:`\FriendsOfTYPO3\Headless\Form\CustomOptionsInterface`
   Dynamic options for a form select/radio/checkbox. Method:
   `get(): array`. See :ref:`integrations-form`.

:php:`\FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface`
   Custom JSON shape for an EXT:form definition. Method:
   `__invoke(array $definition, int $currentPage): array`. See
   :ref:`integrations-form-decorators`.

DI wiring
=========

`FileUtilityInterface`, `HeadlessFrontendUrlInterface`,
`JsonEncoderInterface`, `JsonDecoderInterface` and
`MetaHandlerInterface` are aliased in the extension's `Services.php`;
`HeadlessModeInterface` via `#[AsAlias]` on `HeadlessMode`. Plain
constructor injection works for those:

.. code-block:: php

   use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
   use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
   use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;

   final readonly class MyService
   {
       public function __construct(
           private HeadlessModeInterface $headlessMode,
           private HeadlessFrontendUrlInterface $urlUtility,
           private JsonEncoderInterface $jsonEncoder,
       ) {}
   }

The contracts carry no container alias: you hand the class name to the
extension via TypoScript (`RootSitesProcessor`) or form YAML, and it is
instantiated with `GeneralUtility::makeInstance()`.

`UrlUtility` and `HeadlessMode` are shared services; state safety comes
from their wither methods (`withRequest()` / `withSite()` return
clones), not from `shared: false`.
