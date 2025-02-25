.. _xmlsitemap:

===================
XML Sitemap
===================

Since `ext:headless` 4.0 there's no custom code regarding the XML sitemaps
apart from the templates used to render the XML sitemap's source code.

Troubleshooting
===============

In case the URLs listed in the "sitemap index" file (usually `/sitemap.xml`)
aren't "frontend URLs" (but their API variant), consider setting one of the two
following configurations:

* either `frontendApiProxy` being defined in the site's `config.yaml`
* or this in the site's `settings.yaml`:

.. code-block:: yaml

  headless:
    sitemap:
      key: frontendBase
