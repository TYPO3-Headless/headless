.. include:: ../Includes.txt

.. _configuration:

=============
Configuration
=============

How is the extension configured?
Aim to provide simple instructions detailing how the extension is configured.
Always assume that the user has no prior experience of using the extension.

Try and provide a typical use case for your extension
and detail each of the steps required to get the extension running.

Typical Example
===============

- Does the integrator need to include a static template?
- For example add a code snippet with comments

Minimal example of TypoScript:

- Code-blocks have support for syntax highlighting
- Use any supported language

.. code-block:: typoscript

   plugin.tx_myextension.settings {
      # configure basic email settings
      email {
         subject = Some subject
         from = someemail@domain.de
      }
   }

.. _configuration-typoscript:

TypoScript Reference
====================

Possible subsections: Reference of TypoScript options.
The construct below show the recommended structure for TypoScript properties listing and description.

When detailing data types or standard TypoScript features,
don't hesitate to cross-link to the TypoScript Reference as shown below.

See :ref:`h2document:how-to-document-hyperlinks` for information about how to use cross-references.

See the :file:`Settings.cfg` file for the declaration of cross-linking keys.
