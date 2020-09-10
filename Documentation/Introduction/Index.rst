.. include:: ../Includes.txt

.. _introduction:

============
Introduction
============

.. Info::

   This extension is part of the `TYPO3 PWA Initiative <https://typo3.org/community/teams/typo3-development/initiatives/pwa/>`__
   and is still in an early stage of development.

.. _what-it-does:

What does it do?
================

The headless extension provides an JSON API which can serve as endpoint for any kind of application.
It's using the standard TYPO3 features to render the page-tree structure and page-content into a JSON format.
The JSON response object and the content elements are customizable with TypoScript.

**Features**

* JSON API for content elements
* JSON API for navigation page-tree structure
* Taking into account all language and translation configuration (e.g. fallback)
* Easily extensible with custom fields or custom CE's
* Support for EXT:felogin and EXT:form
* Support for EXT:news (see additional extension `EXT:headless_news <https://github.com/TYPO3-Initiatives/headless_news>`__)