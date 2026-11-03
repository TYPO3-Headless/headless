.. _images:

===================
Image rendering
===================

FileUtility
===========

File rendering in `EXT:headless` is handled by `FileUtility`, which by
default renders the following JSON for each file:

.. code-block:: json

   {
     "publicUrl": "https://www.example.org/fileadmin/_processed_/6/c/csm_my-image_51125112.jpg",
     "properties": {
       "mimeType": "image/jpeg",
       "type": "image",
       "filename": "csm_my-image_51125112.jpg",
       "link": null,
       "linkData": null,
       "originalUrl": "https://www.example.org/fileadmin/my-image.jpg",
       "uidLocal": "123",
       "fileReferenceUid": 234,
       "size": "50 KB",
       "title": null,
       "alternative": null,
       "description": null,
       "dimensions": {
         "width": "300",
         "height": "100"
       },
       "cropDimensions": {
         "width": 300,
         "height": 100
       },
       "crop": null,
       "autoplay": null,
       "extension": "jpg"
     }
   }

`crop` holds the reference's raw crop configuration — a JSON string, or
`null` when none is set.

Set `legacyReturn = 0` in the processing configuration for the simplified
shape:

.. code-block:: typoscript

   page.10.fields.ogImage = TEXT
   page.10.fields.ogImage {
     dataProcessing {
       10 = headless-files
       10 {
         as = media
         references.fieldName = og_image
         processingConfiguration {
           legacyReturn = 0
         }
       }
     }
   }

Each file in the resulting list becomes:

.. code-block:: json

   {
     "url": "https://www.example.org/fileadmin/_processed_/6/c/csm_my-image_51125112.jpg",
     "mimeType": "image/jpeg",
     "type": "image",
     "filename": "csm_my-image_51125112.jpg",
     "originalUrl": "https://www.example.org/fileadmin/my-image.jpg",
     "link": null,
     "uidLocal": "123",
     "fileReferenceUid": 234,
     "size": "50 KB",
     "title": null,
     "alternative": null,
     "description": null,
     "dimensions": {
       "width": "300",
       "height": "100"
     },
     "cropDimensions": {
       "width": 300,
       "height": 100
     },
     "cropVariants": { "default": "..." },
     "autoplay": null,
     "extension": "jpg"
   }

`cropVariants` only appears for images whose crop configuration
defines more than one crop variant.

To add custom keys to this payload (signed URLs, focus point, …), listen to
:php:`\FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent` — see
:ref:`developer-events`.

FilesProcessor
==============

`EXT:headless` provides its own `FilesProcessor` to render files; its
sources (`references`, `files`, `collections`, `folders`) and generic
options are listed in :ref:`dataprocessors-filesprocessor`. The example
above shows how the `og_image` of a page is rendered.

Processing configuration
------------------------

The `processingConfiguration` block accepts these options:

* `legacyReturn` (0|1, default 1) — `1` renders the legacy shape, `0` the
  simplified one shown above.
* `linkResult` (0|1) — only with `legacyReturn = 0`: return the whole
  `LinkResult` object of the file link as `link` instead of its URL.
* `cacheBusting` (0|1) — append `?<modification_date>` to every file URL,
  except YouTube and Vimeo media (enabled globally by the
  `headless.assetsCacheBusting` feature flag).
* `width`, `height`, `minWidth`, `minHeight`, `maxWidth`, `maxHeight` —
  image processing instructions passed to the core processor.
* `cropVariant` (name, default `default`) — render a specific crop variant.
* `processGif` (0|1, default 0) — process GIF files.
* `outputCropArea` (0|1) — add the crop configuration as `crop` to each
  `cropVariants` entry.
* `conditionalCropVariant` (0|1) — skip crop variants whose crop area is
  empty.
* `processPdfAsImage` (0|1, default 0) — process PDF files as images.
* `processSvg` (0|1, default 0) — process SVG files.
* `properties.byType` (0|1) — filter file properties by type (no video
  properties on images).
* `properties.defaultFieldsByType` (comma list) — default fields when
  `properties.byType` is enabled.
* `properties.defaultImageFields` (comma list) — default fields for images
  when `properties.byType` is enabled.
* `properties.defaultVideoFields` (comma list) — default fields for videos
  when `properties.byType` is enabled.
* `properties.includeOnly` (comma list) — file properties to return.
* `properties.flatten` (0|1) — flatten nested properties (the `dimensions`
  array) for use with `properties.includeOnly`.
* `returnFlattenObject` (0|1) — return a single file object instead of an
  array; for fields that hold one file.
* `delayProcessing` (0|1) — skip processing and only collect the files, so
  that the next processor (usually `GalleryProcessor`) processes them.
* `fileExtension` — convert images to another format, e.g. `webp`.
* `autogenerate` — additional renditions per image, each rendered at the
  target width/height times a factor (capped at the original size) and
  emitted as a URL string next to the file's own URL:

  * `retina2x = 1` — factor 2, emitted as `urlRetina`.
  * `lqip = 1` — factor 0.1, emitted as `urlLqip`.
  * custom keys with `factor` and optional `fileExtension`, emitted under
    the key's name, see the example below.

.. code-block:: typoscript

   10 = headless-files
   10 {
      references.fieldName = image
      as = files
      processingConfiguration {
         delayProcessing = 1
      }
   }
   20 = headless-gallery
   20 {
      ...
      autogenerate {
         retina2x = 1
         customFileWebp {
            fileExtension = webp
            factor = 1.0
         }
         customTinyJpg {
            fileExtension = jpg
            factor = 0.2
         }
      }
   }

.. code-block:: typoscript

   10 = headless-files
   10 {
      ...
      processingConfiguration {
         # (1 by default = legacy output; set 0 for the new simplified format)
         legacyReturn = 0
         # Return whole LinkResult object instead simple url
         linkResult = 1
         # skip crop variants whose crop area is empty
         conditionalCropVariant = 1
         # Generate cacheBusting urls for images and video files
         cacheBusting = 1
         properties {
            # return props by mimeType
            byType = 1
            # return only properties defined below
            includeOnly = alternative,width,height
            # you can also alias fields
            # includeOnly = alternative as alt,width,height
            # with includeOnly you can use option `flatten` to flatten dimensions array
            flatten = 1
         }
      }
   }

.. _images-galleryprocessor:

GalleryProcessor
================

The `EXT:headless` `GalleryProcessor` extends the core one and processes
the files a preceding `FilesProcessor` collected with
`delayProcessing = 1`. It reads them from `filesProcessedDataKey`
(default `files`), so set `as = files` on the `FilesProcessor` — its own
default `as` is `media` — as in the example above and the shipped
`lib.galleryContentElement`.

The rendering configuration is set directly on the processor: **all**
options of the `processingConfiguration` block documented above
(`legacyReturn`, `fileExtension`, `cropVariant`, `autogenerate`, …) are
read from the processor's top-level configuration — there is no
`processingConfiguration` sub-key. These options are read as plain
values, without stdWrap.

.. note::

   `legacyReturn` must have the same value on the `FilesProcessor` and
   the `GalleryProcessor`: the gallery reads the collected files in the
   shape its own `legacyReturn` describes.

The core gallery options (`mediaOrientation`, `numberOfColumns`,
`equalMediaHeight`, `equalMediaWidth`, `borderEnabled`, …) work as in
the core processor. The shipped `lib.galleryContentElement` sets the
following from `EXT:headless` constants
(:file:`Configuration/TypoScript/constants.typoscript`):

* `maxGalleryWidth`: `{$styles.content.textmedia.maxW}`
* `maxGalleryWidthInText`: `{$styles.content.textmedia.maxWInText}`
* `columnSpacing`: `{$styles.content.textmedia.columnSpacing}`
* `borderWidth`: `{$styles.content.textmedia.borderWidth}`
* `borderPadding`: `{$styles.content.textmedia.borderPadding}`
