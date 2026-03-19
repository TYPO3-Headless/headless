.. _images:

===================
Image rendering
===================

FileUtility
===========

The file rendering in `EXT:headless` is handled by `FileUtility` which renders the following JSON for each file:

.. code-block:: json

  "publicUrl": "https://www.example.org/fileadmin/_processed_/6/c/csm_my-image_51125112.jpg"
  "properties": {
    "mimeType": "image/jpeg",
    "type": "image",
    "filename": "csm_my-image_51125112.jpg",
    "link": null,
    "linkData": null,
    "originalUrl": "https://www.example.org/fileadmin/my-image.jpg",
    "uidLocal": "123",
    "fileReferenceUid": "234",
    "size": "50 KB",
    "dimensions": {
      "width": "300",
      "height": "100",
    },
    "cropDimensions": {
      "width": "300",
      "height": "100",
    },
    "crop": { ... },
    "autoplay": null,
    "extension": null
  }

The file rendering can be simplified via `legacyReturn` = 0 processing configuration flag

.. code-block:: typoscript

  lib.meta {
    fields {
      ogImage = TEXT
      ogImage {
        dataProcessing {
          10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
          10 {
            as = media
            references.fieldName = og_image
            processingConfiguration {
              legacyReturn = 0
            }
          }
        }
      }
    }
  }

Will output:

.. code-block:: json

  "url": "https://www.example.org/fileadmin/_processed_/6/c/csm_my-image_51125112.jpg"
  "mimeType": "image/jpeg",
  "type": "image",
  "filename": "csm_my-image_51125112.jpg",
  "originalUrl": "https://www.example.org/fileadmin/my-image.jpg",
  "link": null,
  "uidLocal": "123",
  "fileReferenceUid": "234",
  "size": "50 KB",
  "dimensions": {
     "width": "300",
      "height": "100",
  },
  "cropDimensions": {
    "width": "300",
    "height": "100",
  },
  "crop": { ... },
  "autoplay": null,
  "extension": null

EnrichFileDataEvent
-------------------

`FileUtility` is emitting the event `EnrichFileDataEvent` for manipulating the `properties` array.

To add a listener add this to your `Configuration/Services.yaml`:

.. code-block:: yaml

  My\Extension\EventListener\TweakFileData:
    tags:
      - name: event.listener
        identifier: 'tweak-file-data'
        event: FriendsOfTYPO3\Headless\Event\EnrichFileDataEvent

FilesProcessor
==============

`headless` provides its own `FilesProcessor` to render files.

Here's an example of how the `og_image` of a page is being rendered via TypoScript:

.. code-block:: typoscript

  lib.meta {
    fields {
      ogImage = TEXT
      ogImage {
        dataProcessing {
          10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
          10 {
            as = media
            references.fieldName = og_image
            processingConfiguration {
              returnFlattenObject = 1
            }
          }
        }
      }
    }
  }

Configuration
-------------

The rendering configuration can be set via the property `processingConfiguration` and provides the following sub-properties.

All properties support **stdWrap** processing. This means you can use stdWrap properties like `override`, `if`, `ifEmpty`, etc.
However, to use a **cObject** (e.g. `CASE`, `COA`, `TEXT`) for dynamic value resolution, you must use the `cObject` stdWrap property
explicitly and provide a dummy value:

.. code-block:: typoscript

   processingConfiguration {
      # Simple static value
      width = 800

      # Using stdWrap override
      width = 800
      width.override = 1200

      # Using a cObject for dynamic values — note the dummy value and .cObject syntax
      width = 1
      width.cObject = CASE
      width.cObject {
         key.field = layout
         1 = TEXT
         1.value = 400
         2 = TEXT
         2.value = 800
      }

      # This will NOT work ("CASE" is treated as a literal string):
      # width = CASE
      # width.key.field = layout
   }

Image processing
~~~~~~~~~~~~~~~~

* `width` (string): Width of the processed image (e.g. ``800``, ``800c``, ``800m``). Supports the same syntax as TYPO3's image processing.
* `height` (string): Height of the processed image.
* `minWidth` (int): Minimum width of the processed image.
* `minHeight` (int): Minimum height of the processed image.
* `maxWidth` (int): Maximum width of the processed image.
* `maxHeight` (int): Maximum height of the processed image.
* `fileExtension` (string): Convert the images to any desired format, e.g. ``webp``.
* `cropVariant` (string, default: ``default``): The crop variant to use for image processing.
* `outputCropArea` (0|1): Output the crop area in the response.

Output control
~~~~~~~~~~~~~~

* `legacyReturn` (0|1, default: 1): Controls new simplified output or old system.
* `linkResult` (0|1): Return the whole ``LinkResult`` object instead of a simple URL.
* `cacheBusting` (0|1): Enable cache-busting URLs for processed files.
* `returnFlattenObject` (0|1): Without this flag an array of (multiple) images is rendered. Set this if you're only rendering 1 image and want to reduce nesting.
* `delayProcessing` (0|1): Skip processing of images (have them simply collected by `FilesProcessor`), in order to have them processed by the next processor in line (generally `GalleryProcessor`).

Crop variants
~~~~~~~~~~~~~

* `conditionalCropVariant` (0|1): Conditionally autogenerate files with defined variants if set (if not all variants are returned).

File type handling
~~~~~~~~~~~~~~~~~~

* `processPdfAsImage` (0|1): Enable optional processing of PDF files as images (default off).
* `processSvg` (0|1): Enable optional processing of SVG files (default off).

Property filtering
~~~~~~~~~~~~~~~~~~

* `properties.byType` (0|1): Filter file properties by type (e.g. do not return video properties on images).
* `properties.defaultFieldsByType` (comma separated list of fields): Default fields when ``properties.byType`` is enabled.
* `properties.defaultImageFields` (comma separated list of fields): Default fields for image type when ``properties.byType`` is enabled.
* `properties.defaultVideoFields` (comma separated list of fields): Default fields for video type when ``properties.byType`` is enabled.
* `properties.includeOnly` (string, comma separated): Configure what file properties to return.
* `properties.flatten` (0|1): Flatten nested properties (dimensions array) to use with ``properties.includeOnly``.

Autogenerate variants
~~~~~~~~~~~~~~~~~~~~~

* `autogenerate`:
  * `retina2x`: set this to render an additional image URI in high quality (200%).
  * `lqip`: set this to render an additional image URI with low quality (10%).
  * also custom defined size & file formats see example below

.. code-block:: typoscript

   10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
   10 {
      ...
      processingConfiguration {
         delayProcessing = 1
      }
   }
   20 = FriendsOfTYPO3\Headless\DataProcessing\GalleryProcessor
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

   10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
   10 {
      ...
      processingConfiguration {
         # (1 by default, return new format of file object)
         legacyReturn = 0
         # Return whole LinkResult object instead simple url
         linkResult = 1
         # check if we need to conditionally check if we should generate crop variants
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

Configuration
-------------

The rendering configuration can be set directly. No `processingConfiguration` property available!

* `maxGalleryWidth`: set to the core constant `{$styles.content.textmedia.maxW}`
* `maxGalleryWidthInText`: set to the core constant `{$styles.content.textmedia.maxWInText}`
* `columnSpacing`: set to the core constant `{$styles.content.textmedia.columnSpacing}`
* `borderWidth`: set to the core constant `{$styles.content.textmedia.borderWidth}`
* `borderPadding`: set to the core constant `{$styles.content.textmedia.borderPadding}`
* `autogenerate`
  * `retina2x`
  * `lqip`
