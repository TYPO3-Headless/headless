.. every .rst file should include Includes.txt
.. use correct path!

.. include:: Includes.txt

.. Every manual should have a start label for cross-referencing to
.. start page. Do not remove this!

.. _start:

=============================================================
TYPO3 PWA initiative – EXT:headless
=============================================================

:Version:
   |release|

:Language:
   en

:Authors:
   * Tymoteusz Motylewski (Macopedia)
   * Łukasz Uznański (Macopedia)
   * Adam Marcinkowski (Macopedia)
   * Vaclav Janoch (ITplusX)

:Email:
   extensions@macopedia.pl

:License:
   This extension documentation is published under the
   `CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>`__
   (Creative Commons) license

Headless allows you to render JSON from TYPO3 content. You can customize output
by changing types, names and nesting of fields.

This extension provides backend part (JSON API) for TYPO3 PWA solution.
Second part is a JavaScript application
`nuxt-typo3 <https://github.com/TYPO3-Initiatives/nuxt-typo3>`__
which consumes JSON API and renders the content using Vue.js and Nuxt.
You can find the frontend documentation here:
https://typo3-initiatives.github.io/nuxt-typo3/

**Features**

* JSON API for content elements
* JSON API for navigation, layouts
* taking into account all language/translation configuration (e.g. fallback)
* support for EXT:news (in additional extension:
  https://github.com/TYPO3-Initiatives/headless_news)
* easily extensible with custom fields or custom CE's

Development for this extension is happening as part of the TYPO3 PWA initiative,
see https://typo3.org/community/teams/typo3-development/initiatives/pwa/

If you have any questions just drop a line in
`#initiative-pwa <https://typo3.slack.com/archives/CDJK80WV6>`__ Slack channel.

A special thanks goes to `macopedia.com <https://macopedia.com>`__ company,
which is sponsoring development of this solution.

**TYPO3**

The content of this document is related to TYPO3 CMS,
a GNU/GPL CMS/Framework available from `typo3.org <https://typo3.org/>`_ .

**Community Documentation**

This documentation is for the TYPO3 PWA initiative extension
`headless <https://github.com/TYPO3-Initiatives/headless/>`__

It is maintained as part of this third party extension.

If you find an error or something is missing, please:
`Report a Problem <https://github.com/TYPO3-Initiatives/headless/issues/new>`__

**For Contributors**

You are welcome to help improve this guide.
Just click on "Edit me on GitHub" on the top right to submit your change request.

.. toctree::
   :maxdepth: 3

   Introduction/Index
   Editor/Index
   Installation/Index
   Configuration/Index
   Developer/Index
   KnownProblems/Index
   Changelog/Index
   Sitemap
