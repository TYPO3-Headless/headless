.. _developer:

===============
Developer
===============

How to shape and extend the JSON output. Each topic has its own page:

* :ref:`cObjects <developer-cobjects>` — the headless TypoScript
  content objects: `JSON`, `CONTENT_JSON`, plus the casting objects
  `BOOL`/`INT`/`FLOAT`.
* :ref:`Custom content <developer-custom-content>` — custom content
  elements, internal Extbase plugins, integrating external plugins
  (EXT:news et al.), custom TypoScript objects and meta-data overrides.
* :ref:`Data processors <dataprocessors>` — `DatabaseQueryProcessor`,
  `FilesProcessor`, `MenuProcessor`, `GalleryProcessor` and friends,
  with their headless-specific options.
* :ref:`Events <developer-events>` — PSR-14 extension points and how to
  register listeners; full list in :ref:`ref-events`.
* :ref:`Images & files <images>` — file/image payloads and processing
  configuration (the storage proxy itself is a feature flag, see
  :ref:`configuration`).
* :ref:`Snippets <developer-snippets>` — drop-in recipes for common tasks.

Form output decorators are documented with the rest of the form
integration in :ref:`integrations-form`; the default response shape and
every shipped `lib.*` object in :ref:`ref-typoscript`.
