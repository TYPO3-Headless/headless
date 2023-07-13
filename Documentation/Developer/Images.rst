.. include:: ../Includes.txt

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

* `returnFlattenObject`: without that flag an array of (multiple) images is rendered. Set this if you're only rendering 1 image and want to reduce nesting.
* `delayProcessing`: ??
* `fileExtension`: can be used to convert the images to any desired format, e.g. `webp`.
* `autogenerate`:
  * `retina2x`: set this to render an additional image URI in high quality (200%).
  * `lqip`: set this to render an additional image URI with low quality (10%).

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
