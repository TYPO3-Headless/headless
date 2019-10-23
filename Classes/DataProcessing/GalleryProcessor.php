<?php
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use FriendsOfTYPO3\Headless\Utility\FileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * Class GalleryProcessor
 */
class GalleryProcessor extends \TYPO3\CMS\Frontend\DataProcessing\GalleryProcessor
{
    /**
     * Calculate the width/height of the media elements
     *
     * Based on the width of the gallery, defined equal width or height by a user, the spacing between columns and
     * the use of a border, defined by user, where the border width and padding are taken into account
     *
     * File objects MUST already be filtered. They need a height and width to be shown in the gallery
     */
    protected function calculateMediaWidthsAndHeights()
    {
        $columnSpacingTotal = ($this->galleryData['count']['columns'] - 1) * $this->columnSpacing;

        $galleryWidthMinusBorderAndSpacing = max($this->galleryData['width'] - $columnSpacingTotal, 1);

        if ($this->borderEnabled) {
            $borderPaddingTotal = ($this->galleryData['count']['columns'] * 2) * $this->borderPadding;
            $borderWidthTotal = ($this->galleryData['count']['columns'] * 2) * $this->borderWidth;
            $galleryWidthMinusBorderAndSpacing = $galleryWidthMinusBorderAndSpacing - $borderPaddingTotal - $borderWidthTotal;
        }

        // User entered a predefined height
        if ($this->equalMediaHeight) {
            $mediaScalingCorrection = 1;
            $maximumRowWidth = 0;

            // Calculate the scaling correction when the total of media elements is wider than the gallery width
            for ($row = 1; $row <= $this->galleryData['count']['rows']; $row++) {
                $totalRowWidth = 0;
                for ($column = 1; $column <= $this->galleryData['count']['columns']; $column++) {
                    $fileKey = (($row - 1) * $this->galleryData['count']['columns']) + $column - 1;
                    if ($fileKey > $this->galleryData['count']['files'] - 1) {
                        break 2;
                    }
                    $currentMediaScaling = $this->equalMediaHeight / max($this->fileObjects[$fileKey]['properties']['cropDimensions']['height'], 1);
                    $totalRowWidth += ['properties']['cropDimensions']['width'] * $currentMediaScaling;
                }
                $maximumRowWidth = max($totalRowWidth, $maximumRowWidth);
                $mediaInRowScaling = $totalRowWidth / $galleryWidthMinusBorderAndSpacing;
                $mediaScalingCorrection = max($mediaInRowScaling, $mediaScalingCorrection);
            }

            // Set the corrected dimensions for each media element
            foreach ($this->fileObjects as $key => $fileObject) {
                $mediaHeight = floor($this->equalMediaHeight / $mediaScalingCorrection);
                $mediaWidth = floor(
                    $fileObject['properties']['cropDimensions']['width'] * ($mediaHeight / max($fileObject['properties']['cropDimensions']['height'], 1))
                );
                $this->mediaDimensions[$key] = [
                    'width' => $mediaWidth,
                    'height' => $mediaHeight
                ];
            }

            // Recalculate gallery width
            $this->galleryData['width'] = floor($maximumRowWidth / $mediaScalingCorrection);

        // User entered a predefined width
        } elseif ($this->equalMediaWidth) {
            $mediaScalingCorrection = 1;

            // Calculate the scaling correction when the total of media elements is wider than the gallery width
            $totalRowWidth = $this->galleryData['count']['columns'] * $this->equalMediaWidth;
            $mediaInRowScaling = $totalRowWidth / $galleryWidthMinusBorderAndSpacing;
            $mediaScalingCorrection = max($mediaInRowScaling, $mediaScalingCorrection);

            // Set the corrected dimensions for each media element
            foreach ($this->fileObjects as $key => $fileObject) {
                $mediaWidth = floor($this->equalMediaWidth / $mediaScalingCorrection);
                $mediaHeight = floor(
                    $fileObject['properties']['cropDimensions']['height'] * ($mediaWidth / max($fileObject['properties']['cropDimensions']['width'], 1))
                );
                $this->mediaDimensions[$key] = [
                    'width' => $mediaWidth,
                    'height' => $mediaHeight
                ];
            }

            // Recalculate gallery width
            $this->galleryData['width'] = floor($totalRowWidth / $mediaScalingCorrection);

            // Automatic setting of width and height
        }
    }

    /**
     * Prepare the gallery data
     *
     * Make an array for rows, columns and configuration
     */
    protected function prepareGalleryData()
    {
        for ($row = 1; $row <= $this->galleryData['count']['rows']; $row++) {
            for ($column = 1; $column <= $this->galleryData['count']['columns']; $column++) {
                $fileKey = (($row - 1) * $this->galleryData['count']['columns']) + $column - 1;
                if ($this->fileObjects[$fileKey]['properties']['type'] === 'image') {
                    $this->fileObjects[$fileKey] = $this->getFileUtility()->processFile($this->getImageService()->getImage($this->fileObjects[$fileKey]['properties']['fileReferenceUid'], null, true), $this->mediaDimensions[$fileKey] ?? []);
                }
                if ($this->fileObjects[$fileKey]) {
                    $this->galleryData['rows'][$row]['columns'][$column] = $this->fileObjects[$fileKey];
                }
            }
        }

        $this->galleryData['columnSpacing'] = $this->columnSpacing;
        $this->galleryData['border']['enabled'] = $this->borderEnabled;
        $this->galleryData['border']['width'] = $this->borderWidth;
        $this->galleryData['border']['padding'] = $this->borderPadding;
    }

    /**
     * @return FileUtility
     */
    protected function getFileUtility(): FileUtility
    {
        return GeneralUtility::makeInstance(FileUtility::class);
    }

    /**
     * @return ImageService
     */
    protected function getImageService(): ImageService
    {
        return GeneralUtility::makeInstance(ImageService::class);
    }
}
