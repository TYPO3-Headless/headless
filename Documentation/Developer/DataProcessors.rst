.. _dataprocessors:

===============
Data processors
===============

Common behaviour
================

**Identifiers** — every processor is registered under a short
identifier, used throughout this documentation and in the shipped
TypoScript; the class name works as well:

=============================  ==========================
Identifier                     Processor
=============================  ==========================
`headless-database-query`      `DatabaseQueryProcessor`
`headless-extract-property`    `ExtractPropertyProcessor`
`headless-files`               `FilesProcessor`
`headless-flex-form`           `FlexFormProcessor`
`headless-gallery`             `GalleryProcessor`
`headless-language-menu`       `LanguageMenuProcessor`
`headless-menu`                `MenuProcessor`
`headless-root-sites`          `RootSitesProcessor`
=============================  ==========================

All of them live in the namespace
:php:`\FriendsOfTYPO3\Headless\DataProcessing`.

**appendData** — the menu, language-menu, gallery and files
processors share a destructive default: with `appendData` absent or
`0` the processor removes `data` from the processed result *and*
strips `data` from every item in the target (`as`) array — for menus
recursively through all `children`. Set `appendData = 1` to keep the
raw record data.

**Default target names** — when `as` is omitted, each processor falls back
to its default target name:

======================  ==============
Processor               Default `as`
======================  ==============
MenuProcessor           `menu`
LanguageMenuProcessor   `languagemenu`
GalleryProcessor        `gallery`
FilesProcessor          `media`
DatabaseQueryProcessor  `records`
RootSitesProcessor      `sites`
======================  ==============

.. _dataprocessors-databasequeryprocessor:

DatabaseQueryProcessor
======================

The `EXT:headless` equivalent of the core DatabaseQueryProcessor.

.. code-block:: typoscript

   10 = headless-database-query
   10 {
     table = tt_content
     pidInList = 123
     as = contents
     fields {
       header = TEXT
       header {
         field = header
       }
       bodytext = TEXT
       bodytext {
         field = bodytext
         parseFunc =< lib.parseFunc_RTE
       }
     }
   }

Besides `if`, `table`, `as` and nested `dataProcessing`, it accepts the
core `select` properties (`pidInList`, `recursive`, `uidInList`,
`where`, `join`, `orderBy`, `max`, `begin`, …) directly on the
processor, plus:

* `fields` — JSON-style field map applied to every row
* `overrideFields` — same, merged over already-processed rows
* `returnFlattenObject` (Default: 0) — return the single row directly
  instead of an array

`fields` and `overrideFields` are rendered through the
:ref:`JSON cObject <developer-cobjects>`, so every per-field option
documented there (`intval`, `ifEmptyReturnNull`, nested `fields`,
`dataProcessing`, …) works inside them.

.. warning::

   Without `fields` the full raw database row is serialised — every
   column of the table. For tables such as `fe_users` that exposes
   password hashes and personal data. Always set `fields` and list only
   the values the frontend needs.

.. _dataprocessors-extractpropertyprocessor:

ExtractPropertyProcessor
========================

Extracts a single, possibly nested, property from the processed data.

Both `as` and `key` are **required** — the processor throws an
exception when either is missing. Its result **replaces** the entire
processed data: only `[as => value]` survives, everything else the
previous processors produced is discarded.

See the FilesProcessor example below.

.. _dataprocessors-filesprocessor:

FilesProcessor
==============

.. code-block:: typoscript

   page.10.fields.ogImageUrl = TEXT
   page.10.fields.ogImageUrl {
     dataProcessing {
       # Use the column 'og_image' to render an array with all relevant
       # information (such as the publicUrl)
       10 = headless-files
       10.as = media
       10.references.fieldName = og_image
       10.processingConfiguration.returnFlattenObject = 1

       # Extract only property 'publicUrl' from the above created array,
       # so the field holds the URL string
       20 = headless-extract-property
       20.key = media.publicUrl
       20.as = media
     }
   }

File sources, as in the core processor; all are stdWrap-enabled and can
be combined:

* `references` — comma list of `sys_file_reference` uids.
* `references.fieldName` — fetch the file references of this relation
  field of the current record; `references.table` sets the record's
  table (default: the current table).
* `files` — `sys_file` uids.
* `collections` — file collection uids.
* `folders` — combined-identifier folder paths; `folders.recursive = 1`
  descends into subfolders.

Further options: `sorting` (file property, `sorting.direction`),
`appendData` (see `Common behaviour`_) and the `processingConfiguration`
block described in :ref:`images`.

.. _dataprocessors-flexformprocessor:

FlexFormProcessor
=================

Processes a FlexForm field such as `tt_content.pi_flexform` and optionally
overrides values in it.

`fieldName` defaults to `pi_flexform`. When `as` is omitted, the
processed flexform is written back in place — into
`data.<fieldName>`, or the top-level `<fieldName>` key when the value
lives there.

.. code-block:: typoscript

   10 = headless-flex-form
   10 {
     fieldName = pi_flexform
     as = flexform
     overrideFields {
       fieldA = TEXT
       fieldA {
         value = 123
       }
     }
   }

.. _dataprocessors-galleryprocessor:

GalleryProcessor
================

See :ref:`Images GalleryProcessor <images-galleryprocessor>`.

.. _dataprocessors-languagemenuprocessor:

LanguageMenuProcessor
=====================

The `EXT:headless` equivalent of the core LanguageMenuProcessor: the same
items, with the raw record data stripped by default. Allowed options: `if`,
`languages`, `as`, `addQueryString` and `appendData`. Unknown
configuration keys throw an exception.

.. code-block:: typoscript

   lib.languageMenu = JSON
   lib.languageMenu {
     dataProcessing {
       10 = headless-language-menu
       10.as = languageMenu
     }
   }

.. _dataprocessors-menuprocessor:

MenuProcessor
=============

The `EXT:headless` equivalent of the core MenuProcessor.

On top of the core options it provides (configuration keys are
whitelisted — unknown keys throw an exception):

* `appendData` — keep the page record on every menu item under
  `data`, see `Common behaviour`_.
* `additionalFields` — comma list of record fields copied from each
  item's page record onto the item itself, recursively into
  `children`. Works without `appendData`.
* `overwriteMenuLevelConfig` (sub-array) — merged into the TMENU
  configuration of every menu level.
* `overwriteMenuConfig` (sub-array) — merged into the whole generated
  HMENU configuration.

Each menu item is rendered as:

.. code-block:: json

   {
     "title": "About",
     "link": "/about",
     "target": "",
     "active": 1,
     "current": 0,
     "spacer": 0,
     "hasSubpages": 1,
     "children": [ { "…": "…" } ]
   }

`children` is only present when the item has sub-items. Fields listed in
`additionalFields` appear as extra top-level keys on the item.

Example: `lib.breadcrumbs`.

.. code-block:: typoscript

   lib.breadcrumbs = JSON
   lib.breadcrumbs {
     dataProcessing {
       10 = headless-menu
       10 {
         special = rootline
         expandAll = 0
         includeSpacer = 1
         titleField = nav_title // title
         as = breadcrumbs
       }
     }
   }

.. _dataprocessors-rootsiteprocessor:

RootSitesProcessor
==================

.. code-block:: typoscript

   10 = headless-root-sites
   10 {
     as = sites
     allowedSites = 1,2,3
     sortingField = sorting
   }

Lists the root pages of all sites whose site configuration has
`headless` enabled. All options are optional; all except `as` are read as
plain values (no stdWrap):

=========================  ==================================================
Option                     Meaning
=========================  ==================================================
`siteProvider`             Class implementing
                           :php:`\FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface`
                           that discovers the sites. Default: `SiteProvider`.
`siteSchema`               Class implementing
                           :php:`\FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface`
                           that shapes each entry. Default: `SiteSchema`.
`sortingField`             `pages` column to sort the sites by. Default:
                           `sorting`. Must be listed in `dbColumns`.
`sortingImplementation`    Class implementing
                           :php:`\FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSortingInterface`.
                           Default: sort by `sortingField`.
`allowedSites`             Comma list of root page uids to list, e.g. to hide
                           sites that are not ready yet.
`sitesFromPid`             Page (or separator) whose subpages are the site
                           roots; only sites found there are listed. Replaces
                           `allowedSites` when set. Useful for multi-site
                           instances.
`dbColumns`                `pages` columns loaded for each root page and
                           handed to the schema. Default: `uid,title,sorting`.
`titleField`               `pages` column used as the site title. Default:
                           `title`. Must be listed in `dbColumns`.
                           `dbColumns` is read by the default
                           `SiteProvider`, `titleField` by the default
                           `SiteSchema`; `DomainSchema` ignores both.
`dataProcessing`           Further data processors, run per entry with the
                           entry itself as data. They cannot reach the `Site`
                           object — put site-derived fields into the schema
                           class.
=========================  ==================================================

`headless_domains` (`?type=835`) is built with this processor and
`DomainSchema`; an example schema extension is in
:ref:`frontend-multisite`.
