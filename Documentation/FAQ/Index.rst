.. _faq:

===============
FAQ
===============

.. contents::
   :local:
   :depth: 3

How to use EXT:felogin?
-----------------------

Using `EXT:felogin` with the headless extension follows the standard setup as detailed in the `felogin documentation <https://docs.typo3.org/c/typo3/cms-felogin/main/en-us/Index.html>`__; the headless-specific JSON output is described in :ref:`integrations-felogin`.

To test the login without a frontend, first GET the login page to obtain the nonce-signed `__RequestToken` hidden field and its `typo3nonce_*` cookie, then POST the credentials together with both — if the response contains a `set-cookie` header for the session, the login was successful. See :ref:`integrations-felogin` for the full flow.

Does EXT:headless work with other extensions?
---------------------------------------------

Yes, the output of virtually any extension can be rendered into the JSON response. For detailed information, refer to the :ref:`integration of external plugins <developer-plugin-external>` section of this documentation. Additionally, you can review the code of `headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__ as an example of how this integration works.

How to handle redirects in a headless setup?
--------------------------------------------

The frontend application performs the actual redirect: a matched redirect from
`EXT:redirects` is returned as JSON (`{ "redirectUrl": "...", "statusCode": 301 }`)
instead of an HTTP `30x` response.

On headless 5.x (TYPO3 v14) this works automatically as soon as `EXT:redirects`
is installed — no feature flag. See :ref:`integrations-redirects` for the response
shape and customisation.

On headless 4.x and below, enable it with the `headless.redirectMiddlewares`
feature flag:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.redirectMiddlewares'] = true;

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
     - languageId: 0
       title: English
       enabled: true
       base: /en/
       typo3Language: default
       locale: en_US.UTF-8
       navigationTitle: English
       hreflang: en
       flag: global
     - languageId: 1
       title: German
       enabled: true
       base: /de/
       typo3Language: de
       locale: de_DE.UTF-8
       navigationTitle: Deutsch
       hreflang: de
       flag: de

The JSON API will respect these settings and provide the appropriate language versions of the content.

How to enable clean output for plugins in EXT:headless?
-------------------------------------------------------

To enable clean output middleware for plugins, which is available for POST/PUT/DELETE method requests, follow these steps:

1. Set the `headless.elementBodyResponse` feature flag in `config/system/settings.php`:

   .. code-block:: php

      $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['headless.elementBodyResponse'] = true;

2. Enable the headless mode in your site configuration's YAML file:

   .. code-block:: yaml

      headless: 1

3. Send the `responseElementId` field with the ID of the plugin in the body of the plugin data during requests.

On a mixed-mode site (`headless: 2`), the request must additionally carry the exact `Accept: application/json` header, or the middleware does not act.

For example, a POST request might look like this:

.. code-block:: none

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&tx_form_formframework[email]=email&tx_form_formframework[name]=test...

To handle nested elements, use the `responseElementRecursive` flag:

.. code-block:: none

   POST https://example.tld/path-to-form-plugin
   Content-Type: application/x-www-form-urlencoded

   responseElementId=#ELEMENT_ID#&responseElementRecursive=1&tx_form_formframework[email]=email&tx_form_formframework[name]=test...

