.. include:: ../Includes.txt

.. _configuration:

===================
Configuration
===================

Feature flags
========================

To change the setting for this extension feature either use Localconfiguration.php: or AdditionalConfiguration.php:

**headless.frontendUrls** or **FrontendBaseUrlInPagePreview** (deprecated)

This feature toggle extends current SiteConfiguration (and it's variants) with new field for Frontend Url
(url frontend of PWA app). This new field is used when there is a need to preview a page such as: "view" module or right click on a page + show, or the 'eye' icon in page view
& allow generating proper cross-domain links for headless instance.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.frontendUrls'] = true;


**headless.storageProxy**

Enable ability to set storage proxy in site configuration (and it's variants) & serve files via proxy from same domain

Feature flag requires TYPO3 >= 10.4.10

*WARNING* if you install `TYPO3 >= 10.4.18` please update also `ext:headless` to version `>= 2.5.3`

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.storageProxy'] = true;

**headless.redirectMiddlewares**

Enable new & replace core middlewares for handling redirects. Headless mode requires redirects to be handled by frontend app.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.redirectMiddlewares'] = true;

To enable headless support for `EXT:redirect` please also add to you site(s) configuration's yaml file following flag:

.. code-block:: yaml

   headless: true

**headless.nextMajor**

Enable new APIs/behaviors of ext:headless, but contains breaking changes & require upgrade path for you application. Use with caution.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.nextMajor'] = true;

**headless.elementBodyResponse**

Available since `2.6`

Enable clean output middleware for plugins. Clean output is available for POST/PUT/DELETE method requests.
For getting clean for plugins on page, please enable this flag and add `headless` to the site configuration, then send `responseElementId` field with ID of plugin in body with plugin data.

`LocalConfiguration.php`

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.elementBodyResponse'] = true;

`site configuration`:

Please also add to you site(s) configuration's yaml file following flag:

.. code-block:: yaml

   headless: true

Example POST request with plugin form. Please #ELEMENT_ID# replace with id of plugin from page response

.. code-block:: php

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&tx_form_formframework[email]=email&tx_form_formframework[name]=test...

If you would like to find nested element please use new flag `responseElementRecursive`,
where `responseElementId` is child (nested element) example request:

.. code-block:: php

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&responseElementRecursive=1&tx_form_formframework[email]=email&tx_form_formframework[name]=test...

**headless.simplifiedLinkTarget**

Available since `2.6`

Enable simplified target links' property

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.simplifiedLinkTarget'] = true;

Simplified output return only value i.e. `_blank` for target attribute instead of html string ` target="_blank"`

**headless.jsonViewModule**

Available since `3.0`

Enable experimental JsonView backend module which allows preview in backend module of page json response
when passing specific pageType, pageArguments, usergroups, language.

This flag requires additional extension `friendsoftypo3/headless-dev-tools`

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.jsonViewModule'] = true;

**headless.workspaces**

Enable EXT:workspaces preview support.

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.workspaces'] = true;

To enable headless support for `EXT:workspaces` please also add to you site(s) configuration's yaml file following flag:

.. code-block:: yaml

   headless: true

**Availability of feature toggles by version**

.. t3-field-list-table::
   :header-rows: 1

   -  :Header1:   Flag
      :Header2:   2.x
      :Header3:   3.x

   -  :Header1:   FrontendBaseUrlInPagePreview
      :Header2:   available
      :Header3:   removed

   -  :Header1:   headless.frontendUrls
      :Header2:   >= 2.5
      :Header3:   available

   -  :Header1:   headless.storageProxy
      :Header2:   >= 2.4
      :Header3:   available

   -  :Header1:   headless.redirectMiddlewares
      :Header2:   >= 2.5
      :Header3:   available

   -  :Header1:   headless.nextMajor
      :Header2:   >= 2.2
      :Header3:   currently not used

   -  :Header1:   headless.elementBodyResponse
      :Header2:   >= 2.6
      :Header3:   available

   -  :Header1:   headless.simplifiedLinkTarget
      :Header2:   >= 2.6
      :Header3:   removed

   -  :Header1:   headless.jsonViewModule
      :Header2:   not available
      :Header3:   >= 3.0

   -  :Header1:   headless.workspaces
      :Header2:   not available
      :Header3:   >= 3.1

.. _configuration-ext-form:

EXT:form
========================

If EXT:form is enabled in TYPO3 instance, EXT:headless provides support
for handling form in headless mode.

Standard forms designed in form editor in TYPO3 backend should work out of box,
but headless extension supports additional small tweaks/features to help frontend
developers better handle form on their end.

All options are added in yaml files with standard form configuration in TYPO3.

**i18n**

In many cases in headless mode, frontend developers need some translated strings
for common elements like buttons, help messages etc.

With EXT:headless you can add additional configuration in root line of form config:

.. code-block:: yaml

 i18n:
   identifier: 'i18n'
   properties:
      someButtonLabel: 'Submit or Cancel'
      someHelpMessage: 'You need fill this form'
      requiredFields: 'These fields are required'

Above block will be automatically translated by provided xlf files like standard form in fluid.

This block will be translated & available in "i18n" part of response.
More about form output see in Form Decorator section.

**Form Decorator**

Headless extensions provides out of box simple decorator for form definition output.
Decorator simplifies response, and provides API to customize your response for your specific needs.

In rendering options of form you can define your custom project/form decorator
If option is not defined, headless extension defaults to:

`FriendsOfTYPO3\Headless\Form\Decorator\FormDefinitionDecorator`

You can override any time simply by put in form's config yaml:

.. code-block:: yaml

   renderingOptions:
     formDecorator: Your-Vendor\YourExtension\Form\CustomDefinitionDecorator

More about form output decorator please see :ref:`customize form output <developer-ext-form>`

**Validators**

To help frontend developers to create validation handling in frontend context,
You can add small tweaks to form element definition to ease development for your frontend team.

In form element definition you can add option to `errorMessage`  your defined validators
with error code value. This code will be picked up and translated by standard TYPO3's xlf form files

i.e.

.. code-block:: yaml

   renderables:
      -
         type: 'Page'
         identifier: 'page-1'
         label: 'Step'
         renderables:
            -
               properties:
                  options:
                     Mr: 'Mr'
                     Mrs: 'Mrs'
                  elementDescription: ''
                  fluidAdditionalAttributes:
                     required: required
                type: 'RadioButton'
                identifier: 'salutation'
                label: 'Salutation'
                validators:
                    -
                      identifier: 'NotEmpty'
                      errorMessage: 1221560910

When creating RegexValidator, we have some differences
when handling regular expressions by PHP & JS,
to help frontend developers to create consistent frontend/backend validation
we introduced small option for regex validators in TYPO3

For example:

.. code-block:: yaml

   renderables:
      -
         type: Page
         renderables:
            -
              type: 'Text'
              identifier: 'testField'
              label: 'Test field'
              validators:
                -
                   identifier: RegularExpression
                   options:
                     regularExpression: '/^[a-z]+$/'
                   FERegularExpression:
                     expression: '^[a-z]+$'
                     flags: i
                   errorMessage: 1221565130

If Headless's form decorator finds option `FERegularExpression` in validator definition
will override options.regularExpression by value of `regularExpression` option
before sending output for frontend dev.

**Custom options**

When you need a select/radio/checkboxes with custom options, fetched for example
from database or other external source, you need to create Custom FormModel, but in
headless mode we do not render html and render all the options, so we introduced small interface

`FriendsOfTYPO3\Headless\Form\CustomOptionsInterface`

and `customOptions` in definition of form element

.. code-block:: yaml

 - defaultValue: ''
   type: 'SingleSelectWithCountryList'
   identifier: 'country'
   label: 'Country'
   properties:
      customOptions: 'YourVendor\Your-Ext\Domain\Model\YourCustomOptionClassImplementingInterface'

When above option is set with class which implemented correct interface, options of select
will be replaces by values returned by set class.

To make rendering of element easier for frontend developers we introduced option
to override type returned to the frontend developer for example when you
set `FEOverrideType` in renderingOptions of custom element

.. code-block:: yaml

   type: 'SingleSelectWithCountryList'
   renderingOptions:
     FEOverrideType: 'Select'

We use this value to override type, so response to the frontend dev will be

.. code-block:: yaml

   {
     "type": "Select"
   }

instead of

.. code-block:: yaml

   {
     "type": "SingleSelectWithCountryList"
   }

**JSON REDIRECT**

EXT:headless supports handling finishers, for example after handled correctly sent form data
you can use TYPO3 core's RedirectFinisher to redirect to thank you page.
But in order to have more control on frontend side we provide in headless extension

`JsonRedirectFinisher`

Which is based on core RedirectFinisher, but instead of delay & statusCode option
have option of message which can be handled by frontend dev
to display message for user before redirect to defined page.

Also JsonRedirect do not redirect by itself
but generates message (default is null) and uri for redirection by frontend developer

To use JsonRedirect you have to define it in setup.yaml of your extension form's setup

.. code-block:: yaml

   TYPO3:
     CMS:
       Form:
         prototypes:
           standard:
             finishersDefinition:
               JsonRedirect:
                 implementationClassName: 'FriendsOfTYPO3\Headless\Form\Finisher\JsonRedirectFinisher'


[BETA] JsonView backend module
========================


.. image:: ../Images/Configuration/JsonViewModule.png
    :alt: JsonView Module icon with label

|

JsonView module is experimental approach for previewing json response
of a page in different contexts like pagetype, page arguments,
usergroup, langauge, show/hide hidden content.

``!WARNING This is an experimental module, please don't use it on production environment at this time.``

.. image:: ../Images/Configuration/JsonViewModule-settings.png
  :alt: Root page for the API endpoint


.. image:: ../Images/Configuration/JsonViewModule-example.png
  :alt: Root page for the API endpoint

|

``PageTypeModes``

You can set context in which you want to preview a page.

By default there are 3 settings available:

- *default* - standard response with page data and content
- *initialData* - standard response from pageType=834
- *detailNews* (commented out) - example of calling detail action of news extension for test purposes

|

.. code-block:: yaml

    pageTypeModes:
      default:
        title: Default page view
        pageType: 0
        bootContent: 1
        parserClassname: FriendsOfTYPO3\Headless\Service\Parser\PageJsonParser

      initialData:
        title: Initial Data
        pageType: 834
        parserClassname: FriendsOfTYPO3\Headless\Service\Parser\DefaultJsonParser

    #  Example of detail news preset
    #
    #  detailNews:
    #    title: Detail news
    #    pageType: 0
    #    bootContent: 1
    #    arguments:
    #      tx_news_pi1:
    #        action: detail
    #        controller: News
    #        news: 1

|


``Custom YAML configuration``

You can always create your own yaml configuration and set it in extension configuration.

.. image:: ../Images/Configuration/JsonViewModule-extconf.png
  :alt: Root page for the API endpoint

