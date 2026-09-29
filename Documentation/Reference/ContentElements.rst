.. _ref-content-elements:

===========================
Reference: Content elements
===========================

Every element in `content.colPos<n>` is rendered through `lib.contentElement`;
the shipped `CType` definitions add their fields under `content`. Frontends
can rely on the outer shape and switch on `type`.

Base shape
==========

.. code-block:: json

   {
     "id": 12,
     "type": "text",
     "colPos": 0,
     "appearance": {
       "layout": "default",
       "frameClass": "default",
       "spaceBefore": "",
       "spaceAfter": ""
     },
     "content": {}
   }

* `id`, `type`, `colPos` — `uid`, `CType` and `colPos` of the record.
* `appearance` — `lib.appearance`: `layout` is `default` or `layout-1` to
  `layout-3`; `frameClass`, `spaceBefore` and `spaceAfter` are the raw
  `frame_class`, `space_before_class` and `space_after_class` values.
* `content` — element-specific, see the table. Elements based on
  `lib.contentElementWithHeader` start with the header block:

.. code-block:: json

   {
     "header": "Headline",
     "subheader": "",
     "headerLayout": 0,
     "headerPosition": "",
     "headerLink": ""
   }

`headerLink` is a typolink result: an empty string without a link, otherwise
an object with `href`, `target`, `class`, `title`, `linkText` and
`additionalAttributes`.

Shipped content elements
========================

"Menu items" below means the :ref:`MenuProcessor <dataprocessors-menuprocessor>`
item shape. Where `appendData = 1` is set, every item also keeps the page
record under `data`, and file fields (`media`, `images`) hold
:ref:`FilesProcessor <dataprocessors-filesprocessor>` output.

==========================  ====================================================
`type`                      Keys in `content`
==========================  ====================================================
`text`                      header block, `bodytext` (RTE, `lib.parseFunc_RTE`).
`textpic`                   header block, `bodytext`, `enlargeImageOnClick`
                            (bool), `gallery` from the `image` field.
`textmedia`                 Like `textpic`, `gallery` from the `assets` field.
`image`                     header block, `enlargeImageOnClick`, `gallery`.
`bullets`                   header block, `bulletsType` (int), `bodytext` as
                            array: one entry per line, for `bulletsType = 2`
                            one `[term, description]` row per line.
`table`                     header block, `bodytext` as array of rows (cells
                            split by `table_delimiter`/`table_enclosure`),
                            `tableCaption`, `cols` (int), `tableHeaderPosition`
                            (int), `tableClass`, `tableTfoot`.
`uploads`                   header block, `media` (files from the `media` field
                            and the selected file collections, sorted by
                            `filelink_sorting`), `target`,
                            `displayFileSizeInformation`, `displayDescription`,
                            `displayInformation`.
`html`                      `header`, `bodytext` (raw, unprocessed HTML).
`div`                       `header`.
`header`                    header block only.
`shortcut`                  `shortcut`: array of the referenced content
                            elements, each rendered with its own definition
                            (`lib.renderChildren`).
`menu_pages`                header block, `menu`: menu items of the selected
                            pages.
`menu_subpages`             header block, `menu`: menu items of the subpages of
                            the selected pages.
`menu_abstract`             header block, `menu`: subpages with `abstract` and
                            `media` on every item, `appendData = 1`.
`menu_section`              header block, `menu`: pages with `media` and a
                            `content` array (`uid`, `header`, `media`) of their
                            section-index elements, `appendData = 1`.
`menu_section_pages`        header block, `menu`: subpages with `media` and a
                            `content` array of all their elements,
                            `appendData = 1`.
`menu_categorized_pages`    header block, `menu`: pages of the selected
                            categories with `media`, `appendData = 1`.
`menu_categorized_content`  header block, `menu`: content elements (`uid`,
                            `header`, `media`) of the selected categories.
`menu_recently_updated`     header block, `menu`: pages changed in the last
                            seven days with `media`, `appendData = 1`.
`menu_related_pages`        header block, `menu`: pages sharing keywords with
                            `media`, `appendData = 1`.
`menu_sitemap`              header block, `menu`: page tree of the current
                            site, seven levels, `images` per item,
                            `appendData = 1`.
`menu_sitemap_pages`        header block, `menu`: page tree below the selected
                            pages, seven levels, `images` per item,
                            `appendData = 1`.
`felogin_login`             header block, `data`: the login JSON, see
                            :ref:`integrations-felogin`.
`form_formframework`        `link` (submit URL), `form` (uncached form
                            definition), see :ref:`integrations-form`.
`list`                      header block; `type` carries `list_type`. Kept for
                            4.x compatibility, `list_type` plugins no longer
                            exist in TYPO3 v14.
==========================  ====================================================

A `CType` without a definition renders through `tt_content.default`:
`content.error` contains
`Content Element with uid "<uid>" and type "<CType>" has no rendering definition!`.
Define `tt_content.<ctype>` as shown in :ref:`developer-custom-content`.

The legacy set adds a `categories` string to every element after
`appearance`; see the Configuration chapter for re-adding it on the default
set.
