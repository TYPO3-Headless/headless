.. _developer-custom-content:

================================
Custom content & plugin output
================================

How to put your own data into the JSON response, most common approach
first.

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

`fields` can be nested to any depth; the nesting is the JSON shape.
`dataProcessing` works here as in any other content element.

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
   );

**2. Register it for the backend** via
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

   namespace Vendor\MyExt\Controller;

   use Psr\Http\Message\ResponseInterface;
   use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

   final class DemoController extends ActionController
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
`EXTBASEPLUGIN` cObject (since v12.3), the replacement for the old
`USER` + `Bootstrap->run` form:

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
               test = The demo is working
             }
           }
         }
       }
     }
   }

`settings` are passed to the controller as plain values — a cObject such
as `TEXT` is not rendered there.

The `USER` cObject form still works, but `EXTBASEPLUGIN` is the
canonical pattern; it is what `ExtensionUtility::configurePlugin`
generates internally.
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
         "…": "…",
         "content": {
           "data": {
             "foo": "bar",
             "settings": { "test": "The demo is working" }
           }
         }
       }]
     }
   }

.. _developer-php-templates:

Raw-PHP templates for Extbase plugins
=====================================

Requires the `headless.overrideFluidTemplates` feature flag
(:ref:`feature-flag-overridefluidtemplates`). To render an Extbase plugin
through a raw-PHP template, set the plugin's request format in TypoScript
and place the template at
`<templateRootPaths>/<ControllerName>/<ActionName>.php` (highest-keyed root
path wins):

.. code-block:: typoscript

   plugin.tx_myext.view.templateRootPaths.100 = EXT:site_package/Resources/Private/Templates/
   plugin.tx_myext_demoplugin.format = php

An explicit `$view->render('Path/Name')` from your own code resolves
`Path/Name.php` against the same root paths.

`HeadlessViewFactory` serves the raw-PHP view only for a frontend request
in headless mode whose dispatched plugin sets `format = php` in
**TypoScript**. In every other case — feature flag off, backend or CLI
rendering, a request not in headless mode, or a plugin without that
TypoScript setting — the `php` format is dropped and Fluid renders the
regular `.html` template. If the plugin is also rendered outside headless
mode, ship an `.html` template next to the `.php` one.

Template selection is therefore not user-controllable: `format` is a
regular Extbase request parameter, but a request-supplied
`tx_myext_demoplugin[format]=php` on a plugin without the TypoScript
setting falls back to the `.html` template, so Fluid never parses a `.php`
file as a template. A visitor forcing `format=html` on a php-configured
plugin just gets the plugin's regular Fluid resolution — standard Extbase
format behavior.

Unlike Fluid, raw-PHP templates apply **no output escaping**. Build a PHP
array and `echo json_encode($data, JSON_THROW_ON_ERROR)` — never
concatenate JSON strings from record data, that is a JSON injection the
moment a value contains a quote.

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
           }
         }
       }
     }
   }

`EXTBASEPLUGIN` reads `plugin.tx_news` (view, persistence, settings) on its
own, so nothing has to be copied — copying with `<` would also freeze the
values at parse time and hide later changes. Then add your JSON templates
to the plugin's template paths:

.. code-block:: typoscript

   plugin.tx_news.view {
     templateRootPaths.100 = EXT:site_package/Resources/Private/News/Templates/
     partialRootPaths.100 = EXT:site_package/Resources/Private/News/Partials/
     layoutRootPaths.100 = EXT:site_package/Resources/Private/News/Layouts/
   }

`headless_news` ships a ready-made set of these templates.

The templates produce JSON instead of HTML. The structure is not
enforced; design it for your frontend.

Fluid escapes for HTML, not for JSON: `{newsItem.title}` comes out
entity-encoded (`&quot;`), and the same value behind `f:format.raw` breaks
the JSON as soon as it contains a quote. Encode values with `f:format.json`
and wrap only that output in `f:format.raw`, as the templates shipped with
EXT:headless do. `f:format.json` serialises scalars and arrays; format
objects such as `DateTime` first. Example `List.html`:

.. code-block:: html

   <f:spaceless>
     {"list": [<f:for each="{news}" as="newsItem" iteration="i">
       <f:format.raw><f:format.json value="{
         uid: newsItem.uid,
         title: newsItem.title,
         teaser: newsItem.teaser,
         datetime: '{newsItem.datetime -> f:format.date(format: \'c\')}'
       }"/></f:format.raw>{f:if(condition: i.isLast, else: ',')}
     </f:for>]}
   </f:spaceless>

As everywhere in the headless pipeline, JSON encoding is not HTML safety:
the frontend still escapes the values on render.

.. note::

   Upgrading from headless 4.x: plugins registered via `list_type` were
   wired under `tt_content.list.20.<list_type>`. Move that TypoScript to
   `tt_content.<CType>` as shown above. Existing content records still
   carry `CType = list`; migrate them with an upgrade wizard extending
   :php:`\TYPO3\CMS\Core\Upgrades\AbstractListTypeToCTypeUpdate` (most
   third-party extensions ship one with their v14 release).

See `headless_news <https://github.com/TYPO3-Headless/headless_news>`__
for a complete worked example.

.. _developer-custom-typoscript:

Adding fields via raw TypoScript
================================

Sometimes a `CONTENT` (or any other cObject) needs to land inside the
JSON output without being a CE — e.g. a list of related records on
every page. Make TYPO3's text output valid JSON with `stdWrap.split`:

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
           link.typolink.parameter.dataWrap = t3://record?identifier=things&uid={field:uid}
           link.typolink.returnLast = url
         }
         stdWrap.wrap = |###BREAK###
       }
       stdWrap {
         innerWrap = [|]
         split {
           token = ###BREAK###
           cObjNum = 1 |*|2|*| 3
           1.current = 1
           2 < .1
           2.stdWrap.wrap = ,|
           3 < .1
         }
       }
     }
   }

`things` is a record link handler configured in `config.recordLinks`.
In most cases the :ref:`DatabaseQueryProcessor
<dataprocessors-databasequeryprocessor>` is the simpler choice.

.. _developer-meta-override:

Overriding SEO data on a route
==============================

On the default set the `seo` object is assembled by the `MetaHandler` after
the content has been rendered. A plugin that sets the page title through a
page title provider or registers meta tags with the core
`MetaTagManagerRegistry` while rendering is therefore picked up without any
TypoScript.

Where the plugin does not do that, override single tags with `page.meta` in
a condition. The values are stdWrap-enabled; `replace = 1` makes them win
over the values `EXT:seo` derived from the page record. Example for a news
detail page:

.. code-block:: typoscript

   [traverse(request.getQueryParams(), 'tx_news_pi1/news') > 0]
     page.meta {
       description.cObject = RECORDS
       description.cObject {
         tables = tx_news_domain_model_news
         source.data = GP:tx_news_pi1|news
         source.intval = 1
         dontCheckPid = 1
         conf.tx_news_domain_model_news = TEXT
         conf.tx_news_domain_model_news.field = teaser
       }
       description.replace = 1

       og:title.cObject = RECORDS
       og:title.cObject {
         tables = tx_news_domain_model_news
         source.data = GP:tx_news_pi1|news
         source.intval = 1
         dontCheckPid = 1
         conf.tx_news_domain_model_news = TEXT
         conf.tx_news_domain_model_news.field = title
       }
       og:title.attribute = property
       og:title.replace = 1
     }
   [END]

Legacy set: `lib.meta`
----------------------

`lib.meta` exists only in the legacy (4.x-compatible) response. There,
replace it wholesale on a specific route:

.. code-block:: typoscript

   lib.meta.stdWrap.override.cObject = JSON
   lib.meta.stdWrap.override.cObject {
     if.isTrue.data = GP:tx_news_pi1|news
     dataProcessing.10 = headless-database-query
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
