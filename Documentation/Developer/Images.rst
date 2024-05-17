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

The rendering configuration can be set via the property `processingConfiguration` and provides the following sub-properties:

* `legacyReturn` (0|1): Allows to control new simplified output or old system (old system by default)
* `linkResult` (0|1): Allows to define if file object should return only url of defined link or whole LinkResult object
* `cacheBusting` (0|1): Allows to enable cacheBusting urls for processed files
* `conditionalCropVariant` (0|1): Allows conditionally autogenerate files with defined variants if set (if not all variants are returned)
* `properties.byType` (0|1): Allows filter file properties by type (i.e. do not return video properties on images)
* `properties.includeOnly` (string, comma separated): Configure what file properties to return
* `properties.flatten` (0|1): Flatten nested properties (dimensions array) to use with `properties.includeOnly`
* `returnFlattenObject`: without that flag an array of (multiple) images is rendered. Set this if you're only rendering 1 image and want to reduce nesting.
* `delayProcessing`: can be used to skip processing of images (and have them simply collected with the `FilesProcessor`), in order to have them processed by the next processor in line (which is generally `GalleryProcessor`).
* `fileExtension`: can be used to convert the images to any desired format, e.g. `webp`.
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
