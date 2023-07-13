.. include:: ../Includes.txt

.. _images:

===================
Image rendering
===================

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

processingConfiguration
-----------------------

* `returnFlattenObject`: without that flag an array of (multiple) images is rendered. Set this to `1` if you're only rendering 1 image and want to reduce nesting.
* `delayProcessing`: ??
* `fileExtension`: can be used to convert the images to any desired format, e.g. `webp`.
* `autogenerate`: ...
