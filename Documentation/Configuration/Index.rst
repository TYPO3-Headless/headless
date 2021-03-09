.. include:: ../Includes.txt

.. _configuration:

===================
Configuration
===================

This extension has no configuration options yet.

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
