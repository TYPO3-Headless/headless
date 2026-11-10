.. _integrations-form:

============
EXT:form
============

If `EXT:form` is enabled in the TYPO3 instance, `EXT:headless` produces
JSON form definitions instead of HTML. Forms designed in the form
editor work without changes; this page documents the headless-specific
options.

.. note::

   On a mixed-mode site the form definition and every submission need the
   Accept header from :ref:`configuration-mixed-accept`.

Configuration (YAML)
====================

All options live in the form's YAML configuration file.

i18n strings
------------

Add translated UI strings (button labels, help messages) directly in
the form root. They land in the response's `i18n` section.

.. code-block:: yaml

   i18n:
     identifier: 'i18n'
     properties:
       someButtonLabel: 'Submit or Cancel'
       someHelpMessage: 'You need to fill out this form'
       requiredFields: 'These fields are required'

Strings are translated through the standard TYPO3 XLF pipeline.
Translation keys resolve against the form's *original* identifier
(kept in `renderingOptions._originalIdentifier`), not the runtime
identifier that headless suffixes with the content element uid — key
your XLF entries on the identifier from the form YAML.

On key collision, `i18n.properties` entries win over the
`renderingOptions.submitButtonLabel` shortcut — both end up in the
response's `i18n` object, YAML properties last.

Form decorator
--------------

Headless ships `FormDefinitionDecorator` as the default. Override per
form via `renderingOptions.formDecorator`:

.. code-block:: yaml

   renderingOptions:
     formDecorator: Your\Vendor\Form\CustomDefinitionDecorator

See :ref:`integrations-form-decorators` below for writing your own decorator.

Rich-text fields (TYPO3 v14.2+)
-------------------------------

TYPO3 v14.2 allows RTE content in form element labels and `StaticText`
elements. To ship that HTML safely in the JSON definition, opt in per form
to the shipped decorator:

.. code-block:: yaml

   renderingOptions:
     formDecorator: FriendsOfTYPO3\Headless\Form\Decorator\RichTextFormDefinitionDecorator

HTML-carrying labels and static texts are then run through
`lib.parseFunc_RTE` (resolving `t3://` links), falling back to
`lib.parseFunc_links` and finally to the plain HTML sanitizer when
neither lib exists, and flagged with `labelFormat`/`textFormat: html`
so the frontend knows to render them as markup. The decorator also
processes `api.actionAfterSuccess.message` the same way and flags it
with `messageFormat: html`.

Validator error codes
---------------------

Per validator, set `errorMessage` to a TYPO3 XLF error code — the
default form translation files resolve it:

.. code-block:: yaml

   validators:
     - identifier: 'NotEmpty'
       errorMessage: 1221560910

For `RegularExpression` validators, PHP and JS regex flavours differ.
Provide a JS-flavoured fallback via `FERegularExpression`:

.. code-block:: yaml

   validators:
     - identifier: 'RegularExpression'
       options:
         regularExpression: '/^[a-z]+$/'
       FERegularExpression:
         expression: '^[a-z]+$'
         flags: 'i'
       errorMessage: 1221565130

When the headless decorator sees `FERegularExpression`, it replaces
`options.regularExpression` with that value **as-is** in the JSON
response — the `{expression, flags}` pair maps directly to JavaScript's
`new RegExp(expression, flags)`.

Validators flagged `backendOnly: 1` are removed from the JSON definition
(the server still runs them on submit) — use it for checks the frontend
cannot replicate:

.. code-block:: yaml

   validators:
     - identifier: 'MyServerSideCheck'
       backendOnly: 1

Custom options (dynamic select/radio/checkbox)
----------------------------------------------

Implement :php:`\FriendsOfTYPO3\Headless\Form\CustomOptionsInterface` and
point the field at it:

.. code-block:: yaml

   - type: 'SingleSelectWithCountryList'
     identifier: 'country'
     label: 'Country'
     properties:
       customOptions: 'Your\Vendor\Form\CountryOptions'

`CountryOptions::get()` is called per render and its return value
replaces the field's `options`. Although the interface only declares
`get(): array`, implementations are instantiated with four constructor
arguments — `($field, $formFields, $identifier, $formRuntime)`: the
field's definition array, the fields on the same nesting level (its
siblings in the page, `Fieldset` or `GridRow`), the runtime form identifier
and the `FormRuntime` — so options can depend on the surrounding form
state.

If the frontend does not know a custom form type, override the *type* sent to the
frontend with `FEOverrideType`:

.. code-block:: yaml

   type: 'SingleSelectWithCountryList'
   renderingOptions:
     FEOverrideType: 'Select'

JSON redirect finisher
----------------------

The standard `RedirectFinisher` issues a real HTTP redirect. In
headless mode that breaks the SPA navigation. Use the `JsonRedirect`
finisher instead — it puts the redirect target into
`api.actionAfterSuccess` and lets the frontend decide what to do.

Since 5.0 the finisher is registered out of the box (form set
`friendsoftypo3/headless-form`,
`EXT:headless/Configuration/Form/Headless/config.yaml`) — use it
directly in your form definition:

.. code-block:: yaml

   finishers:
     -
       identifier: JsonRedirect
       options:
         pageUid: '2'
         message: 'Thanks! You will be redirected shortly.'

On success it emits `{ redirectUrl, statusCode, message }`
(`message` defaults to `null`, `statusCode` to `303` and is also an
option). It does not redirect by itself. The target is built with
`typoLink_URL()`, so headless's `AfterLinkIsGeneratedListener` rewrites it
to the target page's site `frontendBase`; `prepareRelativeUrlIfPossible()`
then makes it relative when it is on the current site's frontend host,
leaving an absolute `frontendBase` URL for a page on another site. Further
options: `additionalParameters` (appended to the target URL) and
`sameSiteOnly` — with it, a `pageUid` outside the current site (or an
unresolvable one) falls back to the finisher's default target
(`pageUid: 1`) instead of being used.

Submitting the form
===================

POST the form to the content element's `link` value — headless builds
it with `tx_form_formframework[action]=perform` and
`tx_form_formframework[controller]=FormFrontend` already appended.
Nothing inside the form definition itself is a valid submit target.

Every field is submitted under its exact `name`,
`tx_form_formframework[<formId>][<identifier>]`. Besides the visible
fields, the JSON `elements` contain Hidden elements that **must** be
posted back verbatim:

* `__state` — the HMAC-protected form state; round-trip it unchanged.
* `__currentPage` — the page being submitted, 1-based (unlike the
  0-based `api.page.current`); echo it back verbatim.
* `__trustedProperties` — the extbase property-mapping token,
  generated for the listed field names: posting a field that is not
  listed fails property mapping; send only the listed names.
* `__session` — present only once the form is performing (after the
  first POST); echo it back on subsequent steps.

Honeypot
--------

When `renderingOptions.honeypot.enable` is true, an extra field with a
**session-random identifier** appears in `elements` and its name is
baked into `__trustedProperties`. Render it invisible and leave it
**empty**: the core attaches an `EmptyValidator` to it, so an empty or
missing value passes and a filled one fails the submission.
When using a custom honeypot element, its type must match
`renderingOptions.honeypot.formElementToUse` (default `Honeypot`) for
headless to expose it correctly in the JSON definition.

.. _integrations-form-decorators:

Customising the JSON output (decorators)
========================================

EXT:headless provides three building blocks:

* :php:`\FriendsOfTYPO3\Headless\Form\Decorator\FormDefinitionDecorator` —
  default implementation.
* :php:`\FriendsOfTYPO3\Headless\Form\Decorator\AbstractFormDefinitionDecorator`
  — base class with hooks to override per-element or whole-form
  output.
* :php:`\FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface`
  — the contract a custom decorator implements.

Default output (`FormDefinitionDecorator`) — the decorator returns the
bare definition; in the page response it sits under the content
element's `content.form` key:

.. code-block:: json

   {
     "id": "ContactForm-1",
     "api": {
       "status": null,
       "errors": null,
       "actionAfterSuccess": null,
       "page": { "current": 0, "nextPage": null, "pages": 1 }
     },
     "i18n": { "submitButtonLabel": "Submit" },
     "elements": []
   }

`elements` is abbreviated here — in a real response it lists the
current page's fields plus the hidden round-trip fields described in
`Submitting the form`_ above.

Subclass `AbstractFormDefinitionDecorator` if you only need to tweak
one element type or one form root field (`overrideElement()`,
`overrideDefinition()`); implement `DefinitionDecoratorInterface`
directly if you want full control. Decorators are instantiated with
`GeneralUtility::makeInstance($class, $formStatus)`, so a custom
implementation receives the `api` status array as its first constructor
argument. A class not implementing the interface falls back to
`FormDefinitionDecorator`.

Attach via `renderingOptions.formDecorator` as shown above.
