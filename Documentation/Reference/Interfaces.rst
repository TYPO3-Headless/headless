.. _ref-interfaces:

=====================
Reference: Interfaces
=====================

Public interfaces in the :php:`FriendsOfTYPO3\Headless` namespace. Inject
these rather than the concrete classes.

==============================================================  =======================================================
Interface                                                       What it gives you
==============================================================  =======================================================
:php:`Utility\HeadlessModeInterface`                            Detect / switch headless mode for a request. Methods:
                                                                `isEnabled()`, `isEnabledFor($request)`,
                                                                `withRequest($request)`,
                                                                `overrideBackendRequestBySite($site, $language)`.
:php:`Utility\HeadlessFrontendUrlInterface`                     URL rewriting from backend to frontend. Methods:
                                                                `withSite()`, `withRequest()`,
                                                                `getFrontendUrl()`, `getFrontendUrlWithSite()`,
                                                                `getFrontendUrlForPage()`, `getProxyUrl()`,
                                                                `getStorageProxyUrl()`, `resolveKey()`,
                                                                `prepareRelativeUrlIfPossible()`.
                                                                (`withLanguage()` exists only on the concrete
                                                                `UrlUtility`.)
:php:`Utility\FileUtilityInterface`                             File / image rendering. Methods: `setRequest()`,
                                                                `processFile()`, `process()`, `processImageFile()`,
                                                                `processCropVariants()`, `getAbsoluteUrl()`,
                                                                `getErrors()`. `process()` returns the same shape that
                                                                ships in `content.*.media` by default.
:php:`Json\JsonEncoderInterface`                                `encode($data, $options = 0)`. Wraps `json_encode` with
                                                                HTML-safe flags and the `headless.prettyPrint` feature
                                                                flag. Does **not** throw on invalid data: encoding
                                                                errors are caught, logged as critical, and `"[]"` is
                                                                returned.
:php:`Json\JsonDecoderInterface`                                `decode(array)` recursively unwraps JSON strings nested
                                                                inside arrays. `isJson($mixed)` detects JSON by fully
                                                                decoding the value after cheap pre-checks.
:php:`Seo\MetaHandlerInterface`                                 `process($request, $content)` — runs the SEO meta-tag
                                                                pipeline (page title, meta registry, hreflang) and
                                                                merges it under `content.seo`.
:php:`DataProcessing\RootSiteProcessing\SiteProviderInterface`  Strategy for `RootSitesProcessor` to discover sites.
:php:`DataProcessing\RootSiteProcessing\SiteSchemaInterface`    Strategy for shaping the JSON each site produces.
:php:`DataProcessing\RootSiteProcessing\SiteSortingInterface`   Strategy for sorting sites in the output.
:php:`Form\CustomOptionsInterface`                              Dynamic options for a form select/radio/checkbox. See
                                                                :ref:`integrations-form`.
:php:`Form\Decorator\DefinitionDecoratorInterface`              Custom JSON shape for an EXT:form definition.
==============================================================  =======================================================

DI wiring
=========

`FileUtilityInterface`, `HeadlessFrontendUrlInterface`,
`JsonEncoderInterface`, `JsonDecoderInterface` and
`MetaHandlerInterface` are aliased in the extension's `Services.php`;
`HeadlessModeInterface` via `#[AsAlias]` on `HeadlessMode`. Plain
constructor injection works for those:

.. code-block:: php

   final readonly class MyService
   {
       public function __construct(
           private HeadlessModeInterface $headlessMode,
           private HeadlessFrontendUrlInterface $urlUtility,
           private JsonEncoderInterface $jsonEncoder,
       ) {}
   }

The remaining interfaces (the three `RootSiteProcessing` strategies,
:php:`Form\CustomOptionsInterface`, `DefinitionDecoratorInterface`) are
contracts you *implement* and hand to the extension via TypoScript or
form YAML — they carry no container alias.

`UrlUtility` and `HeadlessMode` are shared services; state safety comes
from their wither methods (`withRequest()` / `withSite()` return
clones), not from `share: false`.
