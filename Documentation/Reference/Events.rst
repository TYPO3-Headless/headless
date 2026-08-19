.. _ref-events:

=================
Reference: Events
=================

PSR-14 events dispatched by `EXT:headless`. For registration syntax
and a worked example see :ref:`developer-events`.

EnrichFileDataEvent
===================

`FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent`

Fired by `FileUtility::process()` once a file's default properties
have been collected, before crop variants and `autogenerate` derive
extra fields. The canonical hook for adding custom file fields
(signed URLs, focus point, alt-text overrides) to **every** file in
the response.

==============================  ============================================
Method                          Returns
==============================  ============================================
`getProperties(): array`        The current property bag (mutable via set).
`setProperties(array): void`    Replace the property bag.
`getProcessed(): FileInterface` The processed file (after FAL).
`getOriginal(): FileInterface`  The original file reference.
`getProcessingConfiguration()`  `ProcessingConfiguration` value object.
==============================  ============================================

FileDataAfterCropVariantProcessingEvent
=======================================

`FriendsOfTYPO3\Headless\Event\FileDataAfterCropVariantProcessingEvent`

Fired once per file, after all crop variants have been processed (and
also when the file has none). Use it to post-process the complete file
payload including its `cropVariants`. The event carries the base
per-file `ProcessingConfiguration` that was passed into
`processCropVariants()` — the per-variant configurations derived
inside the loop never reach the event.

==================================  ====================================
Method                              Returns
==================================  ====================================
`getProcessedFile(): array`         The whole processed-file payload.
`setProcessedFile(array): void`     Replace it.
`getOriginal(): FileInterface`      The original file reference.
`getProcessingConfiguration()`      The per-file `ProcessingConfiguration`
                                    (not per-variant).
==================================  ====================================

Core events you may also care about
===================================

These come from TYPO3 core but are commonly used alongside headless:

* `TYPO3\CMS\Redirects\Event\RedirectWasHitEvent` — headless's
  `HeadlessRedirectResponseListener` (identifier
  `headless/RedirectWasHit`) builds the JSON redirect envelope from
  this event. Register your own listener `after` it to replace the
  response — see :ref:`integrations-redirects`.
* `TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent` —
  the headless `AfterCacheableContentIsGeneratedListener` listens to
  this to bake SEO meta tags into the JSON response. Listen too if
  you need to post-process the full encoded JSON before it goes into
  the page cache.
* `TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent` — headless's
  `HeadlessHreflangGeneratorListener` rewrites hreflang URLs through
  `UrlUtility`.
* `TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent` — headless's
  `AfterLinkIsGeneratedListener` rewrites typolink URLs through
  `UrlUtility`.
* `TYPO3\CMS\Core\Routing\Event\AfterPageUriGeneratedEvent` —
  headless's `AfterPageUriGeneratedListener` rewrites the host on
  URIs produced by `PageRouter::generateUri()` — BE "view" links,
  workspace split-screen previews and similar admin tooling land on
  the configured `frontendBase`. It acts only on backend requests
  and only when headless mode is enabled for the resolved site.
* `TYPO3\CMS\Core\Resource\Event\GeneratePublicUrlForResourceEvent` —
  headless's `ProxyResourcePublicUrlListener` (identifier
  `headless/ProxyResourcePublicUrl`) rewrites public URLs of local,
  publicly capable storages through the configured storage proxy.
  Registered only when the `headless.storageProxy` feature flag is
  enabled, and acts only on frontend requests with headless mode on.
* `TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent` — headless
  assigns the `success` status to the JSON login response.

See :ref:`developer-events` for listener registration syntax.
