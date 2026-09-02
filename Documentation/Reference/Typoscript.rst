.. _ref-typoscript:

=============================
Reference: TypoScript helpers
=============================

`lib.*` keys provided by the headless TypoScript. Override any
of them from your site setup to customise the JSON response.

============================  ===========================================================
Key                           Purpose
============================  ===========================================================
`lib.headlessPage`            Bare PAGE skeleton (JSON headers, cache config, an
                              empty `10 = JSON`). The response `page` object is
                              created from it via parse-time copy
                              (`page < lib.headlessPage`) and the fields live on
                              `page.10.fields` — override those, not the lib.
`lib.content`                 `CONTENT_JSON` over `tt_content` grouped by `colPos`.
                              Referenced by `page.10.fields.content`.
`lib.contentElement`          Base JSON shape for every content element.
`lib.contentElementWithHeader`  `lib.contentElement` plus the standard header block.
                              Use this as the base for custom CEs that should keep
                              the title/subtitle/header link block.
`lib.galleryContentElement`   `lib.contentElementWithHeader` plus the shared
                              image-gallery block (`image`, `textpic`, `textmedia`).
`lib.meta`                    Legacy SEO meta object (legacy set only) — title,
                              description, keywords, robots, ogImage. The default
                              set uses the `seo` object instead.
`lib.appearance`              Per-content-element `appearance` block: `layout`
                              (`default`/`layout-1..3`), `frameClass`,
                              `spaceBefore`, `spaceAfter`.
`lib.pageAppearance`          Page-level `appearance` block: `layout`
                              (`layout-*`) and `backendLayout`.
`lib.breadcrumbs`             `MenuProcessor` with `special = rootline`.
`lib.categories`              `sys_category` lookup for the current page
                              (legacy set only).
`lib.backendEditor`           Backend "edit this" link object — attached to the
                              `initialData` page type (834) for logged-in BE
                              users only, not to the page response.
`lib.renderChildren`          Recursive content element renderer — used by CEs
                              that contain other CEs (containers, gridelements,
                              etc.).
============================  ===========================================================

Common overrides
================

Add a top-level field to every page response (the `page` object is a
parse-time *copy* of `lib.headlessPage`, so override `page`, not the lib):

.. code-block:: typoscript

   page.10.fields {
     buildId = TEXT
     buildId.value = {$myVendor.buildId}
   }

Replace the meta object on a specific route — see
:ref:`developer-meta-override`.

Make every content element carry a custom field:

.. code-block:: typoscript

   lib.contentElement.fields.myField = TEXT
   lib.contentElement.fields.myField.field = my_field

Read the shipped TypoScript to see the defaults — every override above
just *adds* to or *replaces* values inside the same structure.

Files
=====

The default TypoScript lives in
`EXT:headless/Configuration/TypoScript/` and is loaded by the site sets
under `EXT:headless/Configuration/Sets/`. Subdirectories:

* `Page/` — `lib.headlessPage` and its children.
* `ContentElement/` — `lib.contentElement` + per-type CEs (textmedia,
  bullets, menus, uploads, felogin, …).
* `Helpers/` — `lib.renderChildren`, `lib.parseFunc_links`. (`lib.parseFunc` and
  `lib.parseFunc_RTE` are provided globally by `EXT:frontend` since TYPO3 v13.2.)
* `Configuration/` — language/backend editor wiring.
* `Legacy/` — 4.x-only additions: `lib.meta`, `lib.categories` (page and
  content element variants) and the full legacy `page` object. Loaded by the
  legacy set, the static template and mixed mode — never by the default set.
* `PageResponse.typoscript` — the trimmed default `page` object, loaded only
  by the default set.

.. _ref-typoscript-default-set:

The default set
===============

`friendsoftypo3/headless` (label "TYPO3 Headless",
`EXT:headless/Configuration/Sets/Headless/`) ships a trimmed response by
default. The legacy set `friendsoftypo3/headless-legacy` (label
"TYPO3 Headless Legacy (4.x)") declares the default set as a dependency and
only loads the `Legacy/` delta on top: the full 4.x `page` object, `lib.meta`
and the `categories` fields. It is the quick upgrade path for existing
installs.

Sites not (yet) on sets can select the equivalent sys_template statics
instead: "Headless" (`EXT:headless/Configuration/TypoScript/Headless`, the
trimmed default response), "Headless Legacy (4.x)"
(`EXT:headless/Configuration/TypoScript`, the full 4.x response) and
"Headless - Mixed mode JSON response"
(`EXT:headless/Configuration/TypoScript/Mixed`). 4.x records keep working
unchanged — the 4.x "Headless" static stored the path that is now registered
as the legacy item, and the mixed static kept its path. Site packages that
`@import` `EXT:headless/Configuration/TypoScript/setup.typoscript` directly
also still get the full 4.x response. Note that a root sys_template record
with "Clear" flags wipes all set-provided TypoScript — delete the record when
switching a site to sets.

The default set imports the same `lib.*` helpers as the legacy setup —
everything in the table above still applies — with two exceptions: `lib.meta`
and `lib.categories` are not loaded, and `lib.contentElement` does not define
a `categories` field at all (the legacy delta re-adds it, which restores the
field on every shipped content element at once — all CEs reference
`lib.contentElement` at render time via `=<`).

Default page response
---------------------

.. code-block:: json

   {
     "id": 1,
     "type": "Standard",
     "slug": "/",
     "media": [],
     "seo": { "title": "…", "meta": [], "htmlAttrs": {}, "bodyAttrs": {} },
     "breadcrumbs": [],
     "appearance": { "layout": "layout-0", "backendLayout": "default" },
     "content": {},
     "i18n": []
   }

The `seo` object is populated at runtime: the TypoScript only ships a
placeholder with `seo.fields.title`, which the
`AfterCacheableContentIsGenerated` listener detects before handing the
response to the `MetaHandler`. Do not remove the placeholder — without a
`seo.title` key in the rendered JSON, no `seo` data is generated at all.
`page.meta.` (e.g. the `generator` entry) is likewise still read by the
`MetaHandler` to build `seo.meta`.

The additional page types of the legacy setup are available unchanged:
`initialData` (`typeNum = 834`) and `headless_domains` (`typeNum = 835`).

Differences to the legacy set
-----------------------------

====================================  =====================================================
Removed key                           Replacement / how to get it back
====================================  =====================================================
`page.10.fields.meta`                 Deprecated duplicate of `seo` — use `seo`, which the
                                      `MetaHandler` fills from the same page properties
                                      (title, description, OpenGraph/Twitter images, …).
`page.10.fields.categories`           Opt-in; see "Content element categories" in the
                                      Configuration chapter (covers page categories too).
`lib.contentElement.fields.categories`  Opt-in; same Configuration chapter section.
`plugin.tx_headless.staticTemplate`   Not set by this set. Only relevant for third-party
                                      extensions probing it to detect headless rendering.
====================================  =====================================================

Every removal saves work on uncached renders: `meta` cost two extra
`FilesProcessor` runs per page, and the `categories` fields cost one
`sys_category` join per page respectively per content element — while
returning an empty string unless the storage pid was reconfigured.
