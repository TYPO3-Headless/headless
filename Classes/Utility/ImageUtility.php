<?php
declare(strict_types = 1);

namespace FriendsOfTYPO3\Headless\Utility;

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

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class ImageUtility
 */
class ImageUtility
{
    /**
     * @param FileReference $fileReference
     * @param $dimensions
     * @return array
     */
    public function processFile(FileReference $fileReference, array $dimensions = []): array
    {
        /** @var ContentObjectRenderer $cObj */
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $processedFile = $this->processImageFile($fileReference, $dimensions);

        $metaData = $processedFile->toArray();
        $data = [
            'publicUrl' => $this->getImageService()->getImageUri($processedFile, true),
            'dimensions' => [
                'width' => $processedFile->getProperty('width'),
                'height' => $processedFile->getProperty('height')
            ],
            'metaData' => [
                'title' => $metaData['title'],
                'alternative' => $metaData['alternative'],
                'description' => $metaData['description'],
                'link' => !empty($metaData['link']) ? $cObj->typoLink_URL([
                    'parameter' => $metaData['link']
                ]) : null
            ]
        ];

        return $data;
    }

    /**
     * @param FileReference $image
     * @param array $dimensions
     * @return ProcessedFile
     */
    protected function processImageFile(FileReference $image, array $dimensions = []): ProcessedFile
    {
        try {
            $properties = $image->getProperties();

            $imageService = GeneralUtility::makeInstance(ImageService::class);
            $cropString = $properties['crop'];
            if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
                $cropString = $image->getProperty('crop');
            }
            $cropVariantCollection = CropVariantCollection::create((string)$cropString);
            $cropVariant = $properties['cropVariant'] ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = [
                'width' => $dimensions['width'] ?? $properties['width'],
                'height' => $dimensions['height'] ?? $properties['height'],
                'minWidth' => $dimensions['minWidth'] ?? $properties['minWidth'],
                'minHeight' => $dimensions['minHeight'] ?? $properties['minHeight'],
                'maxWidth' => $dimensions['maxWidth'] ?? $properties['maxWidth'],
                'maxHeight' => $dimensions['maxHeight'] ?? $properties['maxHeight'],
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];
            return $imageService->applyProcessingInstructions($image, $processingInstructions);
        } catch (ResourceDoesNotExistException $e) {
        } catch (\UnexpectedValueException $e) {
        } catch (\RuntimeException $e) {
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * @return ImageService
     */
    protected function getImageService(): ImageService
    {
        return GeneralUtility::makeInstance(ImageService::class);
    }
}
