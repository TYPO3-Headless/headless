.. Helpful documentation links
   How to Document an Extension: https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/WritingDocForExtension/Index.html
   Sphinx syntax overview: https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/WritingReST/Reference.html
   Sphinx official docs: https://www.sphinx-doc.org/en/master/

.. To pre-render the document during writing (https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/RenderingDocs/Quickstart.html):
   $ source <(docker run --rm t3docs/render-documentation show-shell-commands)
   $ dockrun_t3rd makehtml

.. include:: Includes.txt

.. _start:

=============================================================
EXT:headless
=============================================================

:Version:
   |release|

:Language:
   en

:Description:
   Headless allows you to render JSON from TYPO3 content. You can customize output
   by changing types, names and nesting of fields.

   This extension provides the backend part (JSON API) for TYPO3 PWA solution.
   The frontend part exists as JavaScript application
   `nuxt-typo3 <https://github.com/TYPO3-Initiatives/nuxt-typo3>`__
   which consumes the JSON API and renders the content using Nuxt framework of VueJS.
   You can find the frontend documentation `here <https://typo3-initiatives.github.io/nuxt-typo3>`__.

:Keywords:
   headless, json, api

:Copyright:
   2020 by TYPO3 Association

:Authors:
   * Tymoteusz Motylewski (Macopedia)
   * Łukasz Uznański (Macopedia)
   * Adam Marcinkowski (Macopedia)
   * Vaclav Janoch (ITplusX)

:Email:
   extensions@macopedia.pl

:License:
   This extension is published under the
   `GNU General Public License v2.0 <https://www.gnu.org/licenses/old-licenses/gpl-2.0.html>`__

:Rendered:
	|today|

**Feedback & Credits**

If you have any questions just drop a line in our `#initiative-pwa <https://typo3.slack.com/archives/CDJK80WV6>`__ Slack channel.

Special thanks goes to `macopedia.com <https://macopedia.com>`__ company, which is sponsoring development of this solution.

**TYPO3**

The content of this document is related to TYPO3 CMS,
a GNU/GPL CMS/Framework available from `typo3.org <https://typo3.org/>`_ .

**For Contributors**

You are welcome to help improve this guide if you missing something.
Just click on "Edit me on GitHub" on the top right to submit your change request
or `report a problem <https://github.com/TYPO3-Initiatives/headless/issues/new>`__

**Table of Contents**

.. toctree::
   :maxdepth: 3
   :titlesonly:
   :glob:

   Introduction/Index
   Installation/Index
   Configuration/Index
   Developer/Index
   FAQ/Index
   Sitemap
