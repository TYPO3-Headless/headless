.. _faq:

===============
FAQ
===============

.. contents::
   :local:
   :depth: 3

How to use EXT:felogin?
-----------------------

Using `EXT:felogin` with the headless extension follows the standard setup as detailed in the `felogin documentation <https://docs.typo3.org/c/typo3/cms-felogin/master/en-us/Index.html>`__.

To test the login functionality without a frontend interface, you can use browser extensions such as `RESTer <https://addons.mozilla.org/de/firefox/addon/rester/>`__ (Firefox) or `Insomnia REST Client <https://chrome.google.com/webstore/detail/insomnia-rest-client/gmodihnfibbjdecbanmpmbmeffnmloel>`__ (Chrome) to simulate form submissions. If the response contains a `set-cookie` header, the login was successful.

Does EXT:headless work with other extensions?
---------------------------------------------

Yes, the output of virtually any extension can be rendered into the JSON response. For detailed information, refer to the :ref:`integration of external plugins <developer-plugin-external>` section of this documentation. Additionally, you can review the code of `headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__ as an example of how this integration works.

How to handle redirects in a headless setup?
--------------------------------------------

In a headless setup, redirects should be managed by the frontend application. You can enable and replace core middlewares for handling redirects by setting the `headless.redirectMiddlewares` feature flag:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.redirectMiddlewares'] = true;

Ensure that your frontend application is equipped to handle redirects as specified in the JSON responses.

Can I use custom fields or content elements with EXT:headless?
--------------------------------------------------------------

Yes, `EXT:headless` supports the customization of JSON responses using TypoScript. You can define custom fields or content elements and extend the JSON output to include these customizations.

For example, to add a custom field, you can modify the TypoScript setup like this:

.. code-block:: typoscript

   lib.customField = TEXT
   lib.customField.value = My Custom Field

This value can then be included in the JSON response as needed.

How to configure language and translation settings?
---------------------------------------------------

`EXT:headless` fully supports TYPO3's language and translation configurations, including fallback settings. To configure languages, follow these steps:

1. Define your languages in the site configuration YAML file.
2. Ensure that your content elements and page properties are translated according to TYPO3's multilingual guidelines.

For example, in your site configuration:

.. code-block:: yaml

   languages:
     - title: English
       enabled: true
       base: /en/
       typo3Language: default
       locale: en_US.UTF-8
       iso-639-1: en
       navigationTitle: English
       hreflang: en
       direction: ltr
       flag: global
     - title: German
       enabled: true
       base: /de/
       typo3Language: de
       locale: de_DE.UTF-8
       iso-639-1: de
       navigationTitle: Deutsch
       hreflang: de
       direction: ltr
       flag: de

The JSON API will respect these settings and provide the appropriate language versions of the content.

How to enable clean output for plugins in EXT:headless?
-------------------------------------------------------

To enable clean output middleware for plugins, which is available for POST/PUT/DELETE method requests, follow these steps:

1. Set the `headless.elementBodyResponse` feature flag in `LocalConfiguration.php`:

   .. code-block:: php

      $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.elementBodyResponse'] = true;

2. Add the `headless` flag to your site configuration's YAML file:

   .. code-block:: yaml

      headless: true

3. Send the `responseElementId` field with the ID of the plugin in the body of the plugin data during requests.

For example, a POST request might look like this:

.. code-block:: php

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&tx_form_formframework[email]=email&tx_form_formframework[name]=test...

To handle nested elements, use the `responseElementRecursive` flag:

.. code-block:: php

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&responseElementRecursive=1&tx_form_formframework[email]=email&tx_form_formframework[name]=test...

What is the JsonView backend module?
------------------------------------

The JsonView backend module is an experimental feature that allows previewing the JSON response of a page in different contexts such as pagetype, page arguments, usergroup, and language.

.. warning::

   This is an experimental module. Do not use it in a production environment.

To enable the JsonView backend module, set the `headless.jsonViewModule` feature flag:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.jsonViewModule'] = true;

You can then preview the JSON response directly from the TYPO3 backend.

.. image:: ../Images/Configuration/JsonViewModule.png
   :alt: JsonView Module icon with label
   :class: with-shadow
