.. _ref-events:

=================
Reference: Events
=================

PSR-14 events dispatched by `EXT:headless`, and the core events it
dispatches or listens to. For registration syntax and a worked example see
:ref:`developer-events`.

EnrichFileDataEvent
===================

:php:`\FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent`

Fired by `FileUtility::process()` once a file's default properties have
been collected. The canonical hook for adding custom file fields (signed
URLs, focus point, alt-text overrides) to **every** file in the response.

=========================================================  ===========================================
Method                                                     Returns
=========================================================  ===========================================
`getProperties(): array`                                   The current property bag.
`setProperties(array): void`                               Replace the property bag.
`getProcessed(): FileInterface`                            The processed file (after FAL).
`getOriginal(): FileInterface`                             The original file reference.
`getProcessingConfiguration(): ProcessingConfiguration`    The configuration of this `process()` call.
=========================================================  ===========================================

Caveats:

* `process()` recurses for every crop variant and `autogenerate`
  derivative, so the event fires again for each of them.
* A configured `properties.includeOnly` filter is applied *after* the
  event — list your custom keys there or they are dropped.
* The `url` (or legacy `publicUrl`) key, including the cache-busting
  suffix, is added after the event and is not in the property bag.

FileDataAfterCropVariantProcessingEvent
=======================================

:php:`\FriendsOfTYPO3\Headless\Event\FileDataAfterCropVariantProcessingEvent`

Fired by `FileUtility::processCropVariants()` once per file, after all crop
variants have been processed (also when the file has none). Use it to
post-process the complete file payload including its `cropVariants`.

=========================================================  ======================================
Method                                                     Returns
=========================================================  ======================================
`getProcessedFile(): array`                                The whole processed-file payload.
`setProcessedFile(array): void`                            Replace it.
`getOriginal(): FileInterface`                             The original file reference.
`getProcessingConfiguration(): ProcessingConfiguration`    The per-file configuration passed into
                                                           `processCropVariants()` — the
                                                           per-variant ones never reach the event.
=========================================================  ======================================

Core events dispatched by headless
==================================

* :php:`\TYPO3\CMS\Frontend\ContentObject\Event\ModifyRecordsAfterFetchingContentEvent`
  — dispatched by `CONTENT_JSON` (`JsonContentContentObject`) after each
  record fetch, as the core `CONTENT` object does. `getFinalContent()` is
  the JSON-encoded list collected so far; a replacement must be valid JSON
  (it is decoded with `JSON_THROW_ON_ERROR`).
* :php:`\TYPO3\CMS\FrontendLogin\Event\BeforeRedirectEvent` — dispatched by
  the felogin `LoginController` XClass before it returns the JSON redirect
  envelope. Setting an empty redirect URL suppresses the redirect; the plugin
  returns its regular JSON view instead. See :ref:`integrations-felogin`.

Core events headless listens to
===============================

* :php:`\TYPO3\CMS\Redirects\Event\RedirectWasHitEvent` —
  `HeadlessRedirectResponseListener` (identifier `headless/RedirectWasHit`)
  builds the JSON redirect envelope. Customising it:
  :ref:`integrations-redirects`.
* :php:`\TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent` —
  `AfterCacheableContentIsGeneratedListener` bakes the SEO data into the
  JSON response. Listen too if you need to post-process the full encoded
  JSON before it goes into the page cache.
* :php:`\TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent` —
  `HeadlessHreflangGeneratorListener` rewrites hreflang URLs through
  `UrlUtility`.
* :php:`\TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent` —
  `AfterLinkIsGeneratedListener` rewrites page typolinks to the target
  site's `frontendBase`.
* :php:`\TYPO3\CMS\Core\Routing\Event\AfterPageUriGeneratedEvent` —
  `AfterPageUriGeneratedListener` rewrites the host on URIs produced by
  `PageRouter::generateUri()` — BE "view" links, workspace split-screen
  previews and similar admin tooling land on the configured `frontendBase`.
  It acts only on backend requests and only when headless mode is enabled
  for the resolved site.
* :php:`\TYPO3\CMS\Core\Resource\Event\GeneratePublicUrlForResourceEvent` —
  `ProxyResourcePublicUrlListener` (identifier
  `headless/ProxyResourcePublicUrl`), registered only with
  :ref:`feature-flag-storageproxy`.
* :php:`\TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent` —
  `LoginConfirmedEventListener` assigns the `success` status to the JSON
  login response.
