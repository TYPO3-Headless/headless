.. _developer:

===============
Developer
===============

This chapter will explain different usecases for developer working with `headless` extension.

.. _developer-plugin-extbase:

New cObjects
============

EXT:headless comes with a bunch of new cObjects to be used via TypoScript:

* BOOL
* FLOAT
* INT
* JSON
* CONTENT_JSON

`BOOL`, `FLOAT` and `INT` are basically like `TEXT` (with `value` and `stdWrap` properties!) but make sure their result is being cast to bool, float or int.

JSON
----

To build and render a JSON object into your page output.

.. code-block:: typoscript

  lib.meta = JSON
  lib.meta {
    if.isTrue = 1
    fields {
      title = TEXT
      title {
        field = seo_title
        stdWrap.ifEmpty.cObject = TEXT
        stdWrap.ifEmpty.cObject {
          field = title
        }
      }
      robots {
        fields {
          noIndex = BOOL
          noIndex.field = no_index
        }
      }
      ogImage = TEXT
      ogImage {
        dataProcessing {
          10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
          10 {
            as = media
            references.fieldName = og_image
            processingConfiguration {
              returnFlattenObject = 1
            }
          }
        }
      }
    }
    dataProcessing {
    }
    stdWrap {
    }
  }

The JSON cObjects comes with a bunch of properties: `if`, `fields`, `dataProcessing` and `stdWrap`.

**Property `if`**

Can be used to decide whether or not to render the object.

**Property `fields`**

Array of cObjects. With special properties per item:

* `intval`/`floatval`/`boolval` to cast the result to int, float or bool.
* `ifEmptyReturnNull` to return null in case it's empty
* `ifEmptyUnsetKey` to remove the item in case it's empty
* `dataProcessing` to render data processors (have a look at `lib.meta.ogImage` for example)

**Property `dataProcessing`**

This property can be used to render data processors such as :ref:`MenuProcessor <dataprocessors-menuprocessor>`.

**Property `stdWrap`**

This property can be used to run `stdWrap` to the already rendered JSON output.

CONTENT_JSON
------------

This cObject basically behaves like TYPO3's `CONTENT`, the main difference is that content elements are grouped by `colPol` & encoded into JSON by default.

`CONTENT_JSON` has the same options as `CONTENT` but also offers two new options for edge cases in json context.

**merge**

This option allows to generate another `CONTENT_JSON` call in one definition & then merge both results into one dataset
(useful for handling slide feature of CONTENT cObject).

.. code-block:: typoscript

  lib.content = CONTENT_JSON
  lib.content {
    table = tt_content
    select {
      orderBy = sorting
      where = {#colPos} != 1
    }
    merge {
      table = tt_content
      select {
        orderBy = sorting
        where = {#colPos} = 1
      }
      slide = -1
    }
  }

**doNotGroupByColPos = 0(default)|1**

This option allows to return a flat array (without grouping by colPos) but still encoded into JSON.

.. code-block:: typoscript

  lib.content = CONTENT_JSON
  lib.content {
    table = tt_content
    select {
      orderBy = sorting
      where = {#colPos} != 1
    }
    doNotGroupByColPos = 1
  }

Internal Extbase plugins
========================

To integrate a custom frontend plugin which returns its data inside the JSON object, we have to do the following:

Follow the standard proceeding to `register and configure extbase plugins <https://docs.typo3.org/m/typo3/book-extbasefluid/master/en-us/4-FirstExtension/7-configuring-the-plugin.html>`__:

Create the `DemoController.php`:

.. code-block:: php

  class DemoController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
    public function indexAction() {
      return json_encode([
         'foo' => 'bar',
         'settings' => $this->settings
      ]);
    }
  }

Use the plugin through TypoScript:

.. code-block:: typoscript

  tt_content.list =< lib.contentElementWithHeader
  tt_content.list {
    fields {
      content {
        fields {
          data = CASE
          data {
            key.field = list_type
            demoplugin_type = USER
            demoplugin_type {
              userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
              vendorName = Vendor
              extensionName = ExtName
              pluginName = DemoPlugin
              controller = Demo
              settings {
                test = TEXT
                test.value = The demo is working
              }
            }
          }
        }
      }
    }
  }

Clear the cache and in the response we will see the following JSON output (shortened):

.. code-block:: json

  {
    "content": {
      "colPos0": [{
        "type": "demoplugin_type",
        "appearance": {...},
        "content": {
          "data": {
            "foo": "bar",
            "test": {
              "value": "The demo is working",
              "_typoScriptNodeValue": "TEXT"
            }
          }
        }
      }]
    }
  }

.. _developer-plugin-external:

Integrating external plugins
============================

The integration of other extension plugins is pretty simple. We're providing the `headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__
extension as an example of how it works.

Main part is a user function definition to run a plugin from TypoScript:

.. code-block:: typoscript

  tt_content.list =< lib.contentElementWithHeader
  tt_content.list {
    fields {
      content {
        fields {
          data = CASE
          data {
            key.field = list_type
            news_pi1 = USER
            news_pi1 {
              userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
              vendorName = GeorgRinger
              extensionName = News
              pluginName = Pi1
              controller = News
              view < plugin.tx_news.view
              persistence < plugin.tx_news.persistence
              settings < plugin.tx_news.settings
            }
          }
        }
      }
    }
  }

For any other plugin, just change the `vendorName`, `extensionName`, `pluginName` and `controller` options,
and import needed constant and setup values (like for view, persistence and settings in this case).

Then use the constants of that extension to overwrite the paths to the fluid templates:

.. code-block:: typoscript

  plugin.tx_news {
    view {
      templateRootPath = EXT:headless_news/Resources/Private/News/Templates/
      partialRootPath = EXT:headless_news/Resources/Private/News/Partials/
      layoutRootPath = EXT:headless_news/Resources/Private/News/Layouts/
    }
  }

As last step we need to re-implement the template logic to generate JSON instead of HTML structure.
We do this by creating Fluid templates at the location specified in the previous step.

Because we don't enforce any standard for the JSON structure, we are pretty free here to adjust the
structure to our needs (or to the requests of our frontend developer).

Here is the shortened `List.html` template which generates news items into a JSON array:

.. code-block:: html

  <f:spaceless>
    {"list": [<f:for each="{news}" as="newsItem" iteration="newsIterator">
    <f:if condition="{settings.excludeAlreadyDisplayedNews}">
      <f:then>
        <n:format.nothing>
          <n:excludeDisplayedNews newsItem="{newsItem}"/>
        </n:format.nothing>
      </f:then>
    </f:if>
    <f:render section="NewsListView" arguments="{newsItem: newsItem,settings:settings,iterator:iterator}" />
      {f:if(condition: newsIterator.isLast, else: ',')}
    </f:for>],
    "settings":
    <f:format.raw>
      <f:format.json value="{
        orderBy: settings.orderBy,
        orderDirection: settings.orderDirection,
        templateLayout: settings.templateLayout,
        action: 'list'
      }"/>
    </f:format.raw>
    }
  </f:spaceless>

.. _developer-custom-contentelements:

Create custom content elements
==============================

To add custom content elements we can straight follow the native approach of `TYPO3 and fluid_styled_content <https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/ContentElements/AddingYourOwnContentElements.html#adding-your-own-content-elements>`__.

The only difference to make it work with `headless` is the configuration of the frontend template in TypoScript.
There is an overwritten content object reference in `lib.contentElement` which we can use, as well as an extended
object with a header definition `lib.contentElementWithHeader`:

.. code-block:: typoscript

  tt_content.demo >
  tt_content.demo =< lib.contentElementWithHeader
  tt_content.demo {
    fields {
      content {
        fields {
          demoField = TEXT
          demoField.value = This is a demo content-element
          bodytext = TEXT
          bodytext {
            field = bodytext
            parseFunc =< lib.parseFunc_RTE
          }
          demoSubfields {
            fields {
              demoSubfield = TEXT
              demoSubfield.value = Nested field
            }
          }
        }
      }
    }
  }

The definition of `fields` can be nested until various depth to reflect our desired JSON structure. Also the use of
`dataProcessing <https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/ContentElements/AddingYourOwnContentElements.html#optional-use-data-processors>`__
is possible the native way like in any other content elements (see content element definitions of this extension).

.. _developer-custom-typoscript:

Create custom TypoScript
========================

To add a default TypoScript object (such as `CONTENT`) to the fields of your page object you need to make sure to render it a valid JSON.

Here's an example of how you can create a JSON array of multiple objects from a custom DB table:

.. code-block:: typoscript

  lib.page {
    fields {
      related = CONTENT
      related {
        table = tx_myextension_domain_model_things
        select {
          pidInList = this
        }
        renderObj = JSON
        renderObj {
          fields {
            title = TEXT
            title.field = title
            link = TEXT
            link.typolink.parameter.field = uid
            link.typolink.returnLast = url
          }
          # Add recognizable token at the end of this item
          stdWrap.wrap = |###BREAK###
        }
        stdWrap {
          # Wrap items into square brackets
          innerWrap = [|]

          # Replace 'inner tokens' by comma, remove others
          split {
            token = ###BREAK###
            cObjNum = 1 |*|2|*| 3
            1 {
              current = 1
              stdWrap.wrap = |
            }

            2 < .1
            2.stdWrap.wrap = ,|

            3 < .1
          }
        }
      }
    }
  }

.. _developer-meta-override:

Meta data override
==================

Here's an example of how to override the meta object by data from a DB record:

.. code-block:: typoscript

  lib.meta.stdWrap.override.cObject = JSON
  lib.meta.stdWrap.override.cObject {
    if.isTrue.data = GP:tx_news_pi1|news
    dataProcessing.10 = FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor
    dataProcessing.10 {
      table = tx_news_domain_model_news
      uidInList.data = GP:tx_news_pi1|news
      uidInList.intval = 1
      pidInList = 0
      max = 1
      as = records
      fields < lib.meta.fields
      fields {
        title = TEXT
        title.field = title
        subtitle = TEXT
        subtitle.field = teaser
        description = TEXT
        description.field = bodytext
      }

      returnFlattenObject = 1
    }
  }

.. _developer-dataprocessors:

TypoScript DataProcessors
=========================

This extension provides a couple of handy DataProcessors.

* :ref:`DatabaseQueryProcessor <dataprocessors-databasequeryprocessor>`
* :ref:`ExtractPropertyProcessor <dataprocessors-extractpropertyprocessor>`
* :ref:`FilesProcessor <dataprocessors-filesprocessor>`
* :ref:`FlexFormProcessor <dataprocessors-flexformprocessor>`
* :ref:`GalleryProcessor <dataprocessors-galleryprocessor>`
* :ref:`MenuProcessor <dataprocessors-menuprocessor>`
* :ref:`RootSiteProcessor <dataprocessors-rootsiteprocessor>`

For further information have a look into the default TypoScript to see them in action.

.. _developer-ext-form:

EXT:form & form output decorators
=================================

EXT:headless out of box provides for developers:

- `FriendsOfTYPO3\Headless\Form\Decorator\FormDefinitionDecorator`
- `FriendsOfTYPO3\Headless\Form\Decorator\AbstractFormDefinitionDecorator`
- `FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface`

`FormDefinitionDecorator` is default decorator and outputs

.. code-block:: json

  form: {
    id: "ContactForm-1",
    api: {
      status: null,
      errors: null,
      actionAfterSuccess: null,
        page: {
          current: 0,
          nextPage: null,
          pages: 1
        }
    },
    i18n: {},
    elements: []
  }

You can anytime extend & customize for your needs simply by creating a custom
decorator which implements `DefinitionDecoratorInterface` or extend the provided
`AbstractFormDefinitionDecorator` which provides the ability to override the
definition of each element or the whole form definition.

After creating a custom decorator you can attach it to your form simply by setting
`formDecorator` in the rendering options of the form, :ref:`see more <configuration-ext-form>`

.. _developer-snippets:

Snippets
========

See issue `#136 <https://github.com/TYPO3-Initiatives/headless/issues/136>`__
