.. _ref-viewhelpers:

======================
Reference: ViewHelpers
======================

EXT:headless registers the Fluid namespace `headless`
(`Configuration/Fluid/Namespaces.php`), so these ViewHelpers work in every
template without an `xmlns` declaration. They serve JSON Fluid templates: the
felogin and form templates shipped with the extension and JSON templates for
third-party plugins (:ref:`developer-plugin-external`).

=============================  ======================================================
ViewHelper                     Purpose
=============================  ======================================================
`headless:domain`              Returns a URL from the current site configuration.
                               `return` is `frontendBase`, `proxyUrl`
                               (`frontendApiProxy`) or `storageProxyUrl`
                               (`frontendFileApi`); any other value returns `null`.
`headless:loginForm`           Replacement for `f:form` in felogin templates. Instead
                               of a `<form>` tag it returns the form as JSON:
                               `action`, `method` and the hidden fields Extbase and
                               the request token need (`__referrer`,
                               `__trustedProperties`, `__RequestToken`) in
                               `elements`. Takes the arguments of `f:form`.
`headless:form.registerField`  Registers a field name for the `__trustedProperties`
                               token without rendering an input, for fields the
                               frontend renders itself. Arguments: `name`, `value`,
                               `property`, `checked`, `multiple`.
`headless:format.json.decode`  Decodes JSON (argument `json` or the tag content) into
                               an array. Invalid JSON returns `null`; with
                               `$GLOBALS['TYPO3_CONF_VARS']['FE']['debug']` enabled
                               the `JsonException` is thrown instead.
`headless:iterator.explode`    Splits a string by `glue` (default `,`; `constant:LF`
                               for a line feed) and returns the array. Use the inline
                               notation, e.g.
                               `{item.keywords -> headless:iterator.explode()}`; the
                               `as` argument is not functional.
=============================  ======================================================
