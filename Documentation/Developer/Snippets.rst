.. _developer-snippets:

==========
Snippets
==========

Drop-in recipes for the most common questions.

Customise file output (URL signing, focus point, etc.)
======================================================

Listen to `EnrichFileDataEvent`. See :ref:`developer-events`.

Override the JSON form output
=============================

Implement
`FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface`
or extend `AbstractFormDefinitionDecorator`, then point the form's
`renderingOptions.formDecorator` at it. See :ref:`integrations-form`.

Add a JSON field to every page response
=======================================

Extend the `page` object in TypoScript (loaded after the set):

.. code-block:: typoscript

   page.10.fields {
     myField = TEXT
     myField.value = static value
     myDynamic = TEXT
     myDynamic.data = TSFE:id
   }

Read the current site's frontend URL
====================================

Inject `FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface`
and call `withRequest($request)->getFrontendUrl()`. Returns the
configured `frontendBase` for the active site and language.

Detect headless mode in your own code
=====================================

Inject `FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface` and
call `isEnabledFor($request)`. FULL mode always returns `true`; MIXED
mode only when the request's first `Accept` header value is exactly
`application/json` (the strict match is the API contract).

Encode JSON safely from your own code
=====================================

Inject `FriendsOfTYPO3\Headless\Json\JsonEncoderInterface` instead of
calling `json_encode` directly. It applies HTML-attribute-safe hex
flags (`JSON_HEX_APOS | JSON_HEX_AMP`) and the `headless.prettyPrint`
feature flag for free. Note that it does **not** throw on encoding
failures — a `JsonException` is caught, logged as critical, and the
string `"[]"` is returned instead.

Decode nested JSON inside an array
==================================

Inject `FriendsOfTYPO3\Headless\Json\JsonDecoderInterface` and call
`decode($array)`. Any string value that looks like JSON gets decoded
(nested structures come back as `stdClass` objects, the outer array
stays an array). Useful when stitching multiple `JSON` cObjects
together.

Process a file with the same shape as the default response
==========================================================

Inject `FriendsOfTYPO3\Headless\Utility\FileUtilityInterface` and
call `process($fileReference, ProcessingConfiguration::fromOptions($opts))`.
The output matches what content elements receive — your custom
endpoints stay shape-compatible with the rest of the JSON API.
