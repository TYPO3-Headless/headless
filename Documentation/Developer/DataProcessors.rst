.. _dataprocessors:

===================
Data Processors
===================

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

* `fields`
* `overrideFields`
* `returnFlattenObject` (Default: 0)
* `returnFlattenLegacy` (Default: 1)

.. _dataprocessors-extractpropertyprocessor:

ExtractPropertyProcessor
========================

Extract a single (maybe nested) property from a given array.

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

.. _dataprocessors-flexformprocessor:

FlexFormProcessor
=================

This DataProcessor allows to process a flexform field such as `tt_content.pi_flexform`
and optionally override its property values.

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

See :ref:`Images GalleryProcessor <images-galleryprocessor>`

.. _dataprocessors-menuprocessor:

MenuProcessor
=============

It's the `EXT:headless` equivalent of TYPO3's MenuProcessor.

Have a look at `lib.breadcrumbs` for example:

.. code-block:: typoscript

  lib.breadcrumbs = JSON
  lib.breadcrumbs {
    dataProcessing {
      10 = FriendsOfTYPO3\Headless\DataProcessing\MenuProcessor
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

RootSiteProcessor
=================

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
