.. _dataprocessors:

===================
Data Processors
===================

Common behaviour
================

**`appendData`** — the menu, language-menu, gallery and files
processors share a destructive default: with `appendData` absent or
`0` the processor removes `data` from the processed result *and*
strips `data` from every item in the target (`as`) array — for menus
recursively through all `children`. Set `appendData = 1` to keep the
raw record data.

**`as` defaults** — when `as` is omitted, each processor falls back
to its default target name:

===========================  ================
Processor                    Default `as`
===========================  ================
MenuProcessor                `menu`
LanguageMenuProcessor        `languagemenu`
GalleryProcessor             `gallery`
FilesProcessor               `media`
DatabaseQueryProcessor       `records`
RootSitesProcessor           `sites`
===========================  ================

.. __dataprocessors-databasequeryprocessor:

DatabaseQueryProcessor
======================

It's the `EXT:headless` equivalent of TYPO3's own DatabaseQueryProcessor.

.. code-block:: typoscript

  10 = FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor
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

Apart from the properties of TYPO3's DatabaseQueryProcessor (`if`, `table`, `as` and `dataProcessing`)
it provides the following properties:

* `fields` — JSON-style field map applied to every row
* `overrideFields` — same, merged over already-processed rows
* `returnFlattenObject` (Default: 0) — return the single row directly
  instead of an array

`fields` and `overrideFields` are rendered through the
:ref:`JSON cObject <developer-cobjects>`, so every per-field option
documented there (`intval`, `ifEmptyReturnNull`, nested `fields`,
`dataProcessing`, …) works inside them.

.. _dataprocessors-extractpropertyprocessor:

ExtractPropertyProcessor
========================

Extract a single (maybe nested) property from a given array.

Both `as` and `key` are **required** — the processor throws an
exception when either is missing. Its result **replaces** the entire
processed data: only `[as => value]` survives, everything else the
previous processors produced is discarded.

Example see below in section on FilesProcessor.

.. _dataprocessors-filesprocessor:

FilesProcessor
==============

.. code-block:: typoscript

  lib.meta.fields.ogImage = TEXT
  lib.meta.fields.ogImage {
    dataProcessing {
      # Use the column 'og_image' to render an array with all relevant
      # information (such as the publicUrl)
      10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
      10.as = media
      10.references.fieldName = og_image
      10.processingConfiguration.returnFlattenObject = 1

      # Extract only property 'publicUrl' from the above created array
      20 = FriendsOfTYPO3\Headless\DataProcessing\ExtractPropertyProcessor
      20.key = media.publicUrl
      20.as = media
    }
  }

Sources besides `references.fieldName`: `references` (list of
`sys_file_reference` uids, optionally `references.table`), `files`
(`sys_file` uids), `collections` (file collection uids), `folders`
(combined-identifier folder paths, `folders.recursive = 1` to descend).
Further options: `sorting` (file property, `sorting.direction`),
`appendData` (`1` keeps the processed record data alongside the files;
absent or `0` — the default — strips `data` from the result, see
`Common behaviour`_) and the `processingConfiguration` block described
in :ref:`images`. Default `as` is `media`.

.. _dataprocessors-flexformprocessor:

FlexFormProcessor
=================

This DataProcessor allows to process a flexform field such as `tt_content.pi_flexform`
and optionally override its property values.

`fieldName` defaults to `pi_flexform`. When `as` is omitted, the
processed flexform is written back in place — into
`data.<fieldName>`, or the top-level `<fieldName>` key when the value
lives there.

.. code-block:: typoscript

  10 = FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor
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
Supports `appendData` (see `Common behaviour`_); default `as` is
`gallery`.

.. _dataprocessors-languagemenuprocessor:

LanguageMenuProcessor
=====================

It's the `EXT:headless` equivalent of TYPO3's LanguageMenuProcessor —
same output enriched for JSON consumption, with the raw data stripped
by default. Allowed options: `if`, `languages`, `as` (default
`languagemenu`), `addQueryString` and `appendData` (see
`Common behaviour`_). Unknown configuration keys throw an exception.

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

It's the `EXT:headless` equivalent of TYPO3's MenuProcessor.

On top of the core options it provides (configuration keys are
whitelisted — unknown keys throw an exception):

* `appendData` — keep the page record on every menu item under
  `data`; by default it is stripped recursively, including all
  `children` (see `Common behaviour`_).
* `additionalFields` — comma list of record fields copied from each
  item's page record onto the item itself, recursively into
  `children`. Works without `appendData`.
* `overwriteMenuLevelConfig.` — merged into the TMENU configuration
  of every menu level.
* `overwriteMenuConfig.` — merged into the whole generated HMENU
  configuration.

Default `as` is `menu`. Each menu item is rendered as:

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

`children` is only present when the item has sub-items. With
`appendData = 1` every item additionally keeps the full page record
under `data`; fields listed in `additionalFields` appear as extra
top-level keys on the item.

Have a look at `lib.breadcrumbs` for example (all shipped TypoScript
uses the registered short identifiers — `headless-menu`,
`headless-files`, `headless-gallery`, `headless-database-query`,
`headless-language-menu`, `headless-root-sites`, `headless-flex-form`,
`headless-extract-property` — the FQCNs work too):

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

  10 = FriendsOfTYPO3\Headless\DataProcessing\RootSitesProcessor
  10 {
     as = sites
     # allow to override provider of data for output processor, if empty defaults to FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider
     # your-class implementing FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface
     # example value: Vendor\Project\RootSiteProcessing\CustomSiteProvider
     siteProvider =
     # allow to override output of processor, if empty defaults to FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchema
     # your-class implementing FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface
     # example value: Vendor\Project\RootSiteProcessing\CustomSiteSchema
     siteSchema =
     # provider configuration, if empty defaults to 'sorting' field from pages table
     # example value = custom_sorting
     sortingField =
     # if empty defaults to sort by "sorting" field from `pages` table
     # your-class implementing FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSortingInterface
     # example value: Vendor\Project\RootSiteProcessing\CustomSorting
     sortingImplementation =
     # list of uid of root pages should be returned, i.e. you have 5 root pages(1,2,3,4,5), but two (4,5) of not ready to display, so you can hide it
     # example value = 1,2,3
     allowedSites =
     # automatically fetch root sites from another page/separator and filter sites yaml configs by returned list from database
     # very useful when you have multi site setup in one instance.
     # example value = 1
     sitesFromPid =
     # if empty defaults to uid,title,sorting - list of columns to fetch from database and provided for SiteSchema/DomainSchema to use
     # example value = uid,title,sorting
     dbColumns =
     # if empty defaults to "title" field from pages table, get site name from database
     # example value = your-custom-field-from-pages-table
     titleField =
  }
