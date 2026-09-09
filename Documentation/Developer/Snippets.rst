.. _developer-snippets:

==========
Snippets
==========

Short recipes for common tasks. To add a field to every page response,
see the common overrides in :ref:`ref-typoscript`; to add custom keys to
file payloads, :ref:`developer-events`.

Override the JSON form output
=============================

Implement
:php:`\FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface`
or extend
:php:`\FriendsOfTYPO3\Headless\Form\Decorator\AbstractFormDefinitionDecorator`,
then point the form's `renderingOptions.formDecorator` at it. See
:ref:`integrations-form`.

Read the current site's frontend URL
====================================

Inject :php:`\FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface`
and call `withRequest($request)->getFrontendUrl()`. Returns the
configured `frontendBase` for the active site and language.

Detect headless mode, encode and decode JSON
============================================

Use the injectable services
:php:`\FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface`,
:php:`\FriendsOfTYPO3\Headless\Json\JsonEncoderInterface` and
:php:`\FriendsOfTYPO3\Headless\Json\JsonDecoderInterface`; their
behaviour is described in :ref:`ref-interfaces`.

Process a file with the same shape as the default response
==========================================================

Inject :php:`\FriendsOfTYPO3\Headless\Utility\FileUtilityInterface`
and repeat the steps the `FilesProcessor` performs for each file:

.. code-block:: php

   use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;

   $this->fileUtility->setRequest($request);
   $configuration = ProcessingConfiguration::fromOptions([
       'legacyReturn' => 0,
       'width' => '800',
   ]);
   $file = $this->fileUtility->process($fileReference, $configuration);
   $file = $this->fileUtility->processCropVariants($fileReference, $configuration, $file);

:php:`\FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration::fromOptions()`
takes the keys of the `processingConfiguration` TypoScript block
(:ref:`images`), sub-arrays with a trailing dot (`'properties.' => […]`).
With the same options, the result matches what content elements receive.

.. _configuration-categories:

Content element categories
==========================

The default `TYPO3 Headless` site set (`friendsoftypo3/headless`) does not render
categories at all: `lib.contentElement` does not define a `categories` field
(and therefore no content element carries one), and the page response does not contain
a `categories` field either. This avoids one `sys_category` join per content
element on every uncached render for projects that do not use categories.

If your project needs them, re-add the field in your site package TypoScript,
loaded after the set. Point `pidInList` at the place your categories are actually
stored — either the storage folder uid, or the current root page with `recursive`
as shown below.

Content element categories:

.. code-block:: typoscript

   lib.contentElement.fields.categories = COA
   lib.contentElement.fields.categories {
       10 = CONTENT
       10 {
           table = sys_category
           select {
               pidInList.data = leveluid : 0
               recursive = 99
               selectFields = sys_category.title
               join = sys_category_record_mm on sys_category_record_mm.uid_local = sys_category.uid
               where {
                   field = uid
                   wrap = AND sys_category_record_mm.tablenames = 'tt_content' AND sys_category_record_mm.uid_foreign=|
               }
           }
           renderObj = TEXT
           renderObj {
               field = title
               wrap = |###BREAK###
           }
       }
       stdWrap.split {
           token = ###BREAK###
           cObjNum = 1 |*|2|*| 3
           1 {
               current = 1
               stdWrap.wrap = |
           }
           2 {
               current = 1
               stdWrap.wrap = ,|
           }
           3 {
               current = 1
               stdWrap.wrap = |
           }
       }
   }

Page categories — the legacy `lib.categories` definition is still shipped
(the legacy and mixed sets load it; the default set does not). Import it,
fix the storage pid the same way, and add the field back to the page
response:

.. code-block:: typoscript

   @import 'EXT:headless/Configuration/TypoScript/Legacy/Categories.typoscript'

   lib.categories.10.select.pidInList >
   lib.categories.10.select.pidInList.data = leveluid : 0
   lib.categories.10.select.recursive = 99

   page.10.fields.categories =< lib.categories

Both snippets render a comma-separated string of category titles, matching the
output of the legacy set — use them when the consuming frontend should not need
any changes.

Alternative: categories as a JSON array
---------------------------------------

If your frontend does not depend on the legacy string format, prefer structured
output. This variant uses the `EXT:headless`
:ref:`DatabaseQueryProcessor <dataprocessors-databasequeryprocessor>` and renders
each category as an object, so the field becomes
`"categories": [{"id": 2, "title": "News"}, ...]` instead of `"News,Events"`:

.. code-block:: typoscript

   lib.contentElement.fields.categories = JSON
   lib.contentElement.fields.categories {
       dataProcessing {
           10 = headless-database-query
           10 {
               table = sys_category
               pidInList.data = leveluid : 0
               recursive = 99
               join = sys_category_record_mm ON sys_category_record_mm.uid_local = sys_category.uid
               where.data = field:uid
               where.wrap = sys_category_record_mm.tablenames = 'tt_content' AND sys_category_record_mm.uid_foreign=|
               orderBy = sys_category.sorting
               as = categories
               fields {
                   id = INT
                   id {
                       field = uid
                   }
                   title = TEXT
                   title {
                       field = title
                   }
               }
           }
       }
   }

For page categories use the same definition with
`where.wrap = sys_category_record_mm.tablenames = 'pages' AND sys_category_record_mm.uid_foreign=|`
and assign it to the page response instead:

.. code-block:: typoscript

   page.10.fields.categories = JSON
   page.10.fields.categories {
       dataProcessing {
           # same processor configuration as above, with tablenames = 'pages'
       }
   }

Note this changes the shape of the `categories` field — frontends migrating from
the legacy set must be updated accordingly.

