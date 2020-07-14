.. include:: ../Includes.txt

.. _developer:

===============
Developer
===============

This chapter will explain different usecases for developer working with `headless` extension.

.. _developer-plugin-extbase:

Internal Extbase plugins
========================

To integrate a custom frontend plugin which return its data inside the JSON object, we have to do the following:

Follow the standard proceeding to `register and configure extbase plugins <https://docs.typo3.org/m/typo3/book-extbasefluid/master/en-us/4-FirstExtension/7-configuring-the-plugin.html>`__:

.. code-block:: php

  \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'Vendor.ExtName',
    'DemoPlugin', [
      'Demo' => 'index',
    ],
    []
  );

  \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
     'ext_key',
     'DemoPlugin',
     'My Demo Plugin'
  );

Create the `DemoController.php`:

.. code-block:: php

  class DemoController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
    public function indexAction() {
      return json_encode($this->settings);
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
            "test": {
              "value": "The demo is working",
              "_typoScriptNodeValue": "TEXT"
            },
          }
        }
      }]
    }
  }

.. _developer-plugin-external:

Integrating external plugins
============================

See issue `#138 <https://github.com/TYPO3-Initiatives/headless/issues/138>`__

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
            parseFunc =< lib.parseFunc_links
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

.. _developer-snippets:

Snippets
========

See issue `#136 <https://github.com/TYPO3-Initiatives/headless/issues/136>`__
