.. _introduction:

==============
Introduction
==============

EXT:headless renders TYPO3 pages and content as JSON. The response shape is
plain TypoScript — field names, types and nesting are customised with tools
TYPO3 integrators already know. Any frontend that speaks JSON can consume it;
`nuxt-typo3 <https://github.com/TYPO3-Headless/nuxt-typo3>`__ is the
reference implementation.

.. _what-it-does:

What you get
============

* JSON API for pages, content elements, menus, metadata — with full
  language/fallback handling
* Per-site modes: full JSON, mixed (JSON only for
  `Accept: application/json`), off
* Extensible via TypoScript: custom fields, custom content elements
* Installed core extensions integrate automatically: `EXT:form`,
  `EXT:felogin`, `EXT:redirects` (JSON envelopes), `EXT:seo`
  (meta tags, XML sitemap); workspace preview works out of the box
* Community add-ons: `news <https://github.com/TYPO3-Initiatives/headless_news>`__,
  `solr <https://github.com/TYPO3-Initiatives/headless_solr>`__,
  `powermail <https://github.com/TYPO3-Initiatives/headless_powermail>`__,
  `gridelements <https://github.com/itplusx/headless_gridelements>`__,
  `container <https://github.com/itplusx/headless-container>`__

Version support
===============

==========  ================  ==========  ==============================
headless    TYPO3             PHP         Status
==========  ================  ==========  ==============================
5.x         14                >= 8.2      Active development
4.x         12.4 – 13         >= 8.2      Bug & security fixes
3.x         11.5              —           End of life
==========  ================  ==========  ==============================

Upgrading? See `UPGRADE.md
<https://github.com/TYPO3-Headless/headless/blob/main/UPGRADE.md>`__.
