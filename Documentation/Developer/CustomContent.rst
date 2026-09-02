.. _developer-custom-content:

================================
Custom content & plugin output
================================

How to put your own data into the JSON response. Three flavours,
ordered by what's most common.

.. _developer-custom-contentelements:

Custom content elements
=======================

Standard TYPO3 procedure — `register a CE`_ via TCA and add a frontend
template. For headless, the **frontend template** is TypoScript over
`lib.contentElement` (or `lib.contentElementWithHeader` when you want
the standard header block).

.. _register a CE: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/ContentElements/AddingYourOwnContentElements.html

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

`fields` can be nested to any depth — that's your JSON shape. Use
`dataProcessing` here just like in any other CE.

.. _developer-plugin-internal:

Internal Extbase plugins
========================

Modern Extbase plugins register as **content elements** (`CType`)
rather than via the legacy `list_type` mechanism (deprecated in TYPO3
v13.4, removed in v14). Registration is the standard v14 pattern; only
the frontend template is headless-specific.

**1. Register the plugin** in `ext_localconf.php`:

.. code-block:: php

   \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
       'MyExt',
       'DemoPlugin',
       [\Vendor\MyExt\Controller\DemoController::class => 'index'],
       // non-cacheable actions, if any:
       [\Vendor\MyExt\Controller\DemoController::class => ''],
   );

**2. Make it pickable in the BE** via
`Configuration/TCA/Overrides/tt_content.php`:

.. code-block:: php

   \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
       'MyExt',
       'DemoPlugin',
       'Demo Plugin',
       'extension-myext-demo',
   );

The plugin now shows up as a CType with signature
`myext_demoplugin` (extension key + lowercased plugin name).

**3. Return JSON from the controller**:

.. code-block:: php

   final class DemoController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
   {
       public function indexAction(): ResponseInterface
       {
           return $this->jsonResponse(json_encode([
               'foo' => 'bar',
               'settings' => $this->settings,
           ], JSON_THROW_ON_ERROR));
       }
   }

**4. Wire the headless TypoScript** per CType. TYPO3 ships an
`EXTBASEPLUGIN` cObject (since v12.3) — the modern, two-line
replacement for the old `USER + Bootstrap->run` form:

.. code-block:: typoscript

   tt_content.myext_demoplugin =< lib.contentElementWithHeader
   tt_content.myext_demoplugin {
     fields {
       content {
         fields {
           data = EXTBASEPLUGIN
           data {
             extensionName = MyExt
             pluginName = DemoPlugin
             settings {
               test = TEXT
               test.value = The demo is working
             }
           }
         }
       }
     }
   }

The `USER` cObject form still works for back-compat, but the
`EXTBASEPLUGIN` cObject is the canonical pattern (it's what
`ExtensionUtility::configurePlugin` auto-generates internally).
`vendorName` is obsolete either way — plugin registration works on
FQCN controllers, `extensionName` + `pluginName` suffice.
`controller` is only needed if a plugin exposes multiple controllers;
otherwise the one passed to `configurePlugin` is used.

After a cache flush the JSON appears under the matching content
element:

.. code-block:: json

   {
     "content": {
       "colPos0": [{
         "id": 12,
         "type": "myext_demoplugin",
         "colPos": 0,
         "appearance": { "...": "..." },
         "content": {
           "data": {
             "foo": "bar",
             "test": "The demo is working"
           }
         }
       }]
     }
   }

.. _developer-plugin-external:

External plugins (EXT:news etc.)
================================

Wire the foreign plugin's CType into `tt_content.<ctype>` and point
its templates at your own JSON Fluid templates. The CType signature
is whatever the foreign extension registers — for EXT:news it's
`news_pi1`.

.. code-block:: typoscript

   tt_content.news_pi1 =< lib.contentElementWithHeader
   tt_content.news_pi1 {
     fields {
       content {
         fields {
           data = EXTBASEPLUGIN
           data {
             extensionName = News
             pluginName = Pi1
             view < plugin.tx_news.view
             persistence < plugin.tx_news.persistence
             settings < plugin.tx_news.settings
           }
         }
       }
     }
   }

Then override the plugin's template paths so they point at your JSON
templates:

.. code-block:: typoscript

   plugin.tx_news {
     view {
       templateRootPath = EXT:headless_news/Resources/Private/News/Templates/
       partialRootPath  = EXT:headless_news/Resources/Private/News/Partials/
       layoutRootPath   = EXT:headless_news/Resources/Private/News/Layouts/
     }
   }

The templates produce JSON instead of HTML. There's no enforced
structure — design it for your frontend. Example `List.html`:

.. code-block:: html

   <f:spaceless>
     {"list": [<f:for each="{news}" as="newsItem" iteration="i">
       <f:render section="NewsListView" arguments="{newsItem: newsItem}"/>
       {f:if(condition: i.isLast, else: ',')}
     </f:for>]}
   </f:spaceless>

.. note::

   If you're still maintaining a legacy plugin that registers via
   `list_type`, switch the TypoScript key from `tt_content.list`
   (with a `CASE` on `list_type`) to the per-CType pattern above.
   The legacy `list_type` registration was removed in TYPO3 v14
   (deprecated since v13.4), so v14-compatible plugins are always
   CTypes.

See `headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__
for a complete worked example.

.. _developer-custom-typoscript:

Adding fields via raw TypoScript
================================

Sometimes a `CONTENT` (or any other cObject) needs to land inside the
JSON output without being a CE — e.g. a list of related records on
every page. The trick is to make TYPO3's text output valid JSON
through `stdWrap.split`:

.. code-block:: typoscript

  page.10.fields {
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
          stdWrap.wrap = |###BREAK###
        }
        stdWrap {
          innerWrap = [|]
          split {
            token = ###BREAK###
            cObjNum = 1 |*|2|*| 3
            1 { current = 1
                stdWrap.wrap = | }
            2 < .1
            2.stdWrap.wrap = ,|
            3 < .1
          }
        }
    }
  }

For most cases the much simpler :ref:`DatabaseQueryProcessor
<dataprocessors-databasequeryprocessor>` is a better fit.

.. _developer-meta-override:

Overriding the meta object (legacy set)
=======================================

`lib.meta` exists only in the legacy (4.x-compatible) response — on the
default set the `seo` object is populated by the `MetaHandler` from
page properties instead. On the legacy set, replace `lib.meta`
wholesale on a specific route (e.g. news detail):

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
