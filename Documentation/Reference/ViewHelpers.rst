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
`headless:loginForm`           Replacement for `f:form` in felogin templates; takes
                               the arguments of `f:form`. Instead of a `<form>` tag
                               it returns a JSON list: the child content (the field
                               definitions, passed in already decoded) followed by
                               the hidden fields `{name, type: hidden, value}` that
                               Extbase and the request token need (`__referrer[...]`,
                               `__trustedProperties`, and `__RequestToken` when the
                               `requestToken` argument is set). `action`,
                               `method` and `title` of the shipped form come from
                               the template
                               (`Resources/Private/FeLogin/Templates/Login/Login.html`).
`headless:form.registerField`  Registers a field name for the `__trustedProperties`
                               token without rendering an input, for fields the
                               frontend renders itself. Arguments: `name`, `value`,
                               `property`, `checked`, `multiple`.
`headless:format.json.decode`  Decodes JSON (argument `json` or the tag content) into
                               an array. Invalid JSON returns `null`; with
                               `$GLOBALS['TYPO3_CONF_VARS']['FE']['debug']` enabled
                               the `JsonException` is thrown instead.
`headless:iterator.explode`    Splits `content` (or the tag content) by `glue`
                               (default `,`; `constant:LF` for a line feed) and
                               returns the array, e.g.
                               `{item.keywords -> headless:iterator.explode()}`.
                               `as` assigns no variable: combined with `content` it
                               makes the ViewHelper return the trimmed, unsplit
                               string; otherwise it is ignored.
=============================  ======================================================
