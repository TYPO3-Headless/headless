.. _ref-feature-flags:

=======================
Reference: Feature Flags
=======================

Quick lookup. For per-flag descriptions and usage see
:ref:`configuration`.

Active in 5.x
=============

================================  ===================================================
Flag                              Effect
================================  ===================================================
`headless.storageProxy`           Route processed file URLs through `frontendFileApi`.
`headless.elementBodyResponse`    On POST/PUT/DELETE, return just the element
                                  matching `responseElementId` from the body.
`headless.overrideFluidTemplates` Swap `ViewFactoryInterface` for
                                  `HeadlessViewFactory`. Required to render
                                  raw-PHP templates per view.
`headless.cookieDomainPerSite`    Per-site `cookieDomain` injection middleware.
`headless.assetsCacheBusting`     Append `?<mtime>` to processed-file URLs.
`headless.prettyPrint`            `JSON_PRETTY_PRINT` on every encoder output.
================================  ===================================================

Availability by version
=======================

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
      :Header5:   removed (auto-on when EXT:redirects is installed)

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
      :Header5:   available (reworked, see UPGRADE.md)

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
