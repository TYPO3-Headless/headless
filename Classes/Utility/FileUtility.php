<?php

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

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Class FileUtility
 */
class FileUtility
{
    /**
     * @var string
     */
    protected $cropVariant = 'default';

    /**
     * @param FileReference $fileReference
     * @param $dimensions
     * @return array
     */
    public function processFile(FileReference $fileReference, array $dimensions = []): array
    {
        /** @var ContentObjectRenderer $cObj */
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $fileReferenceUid = $fileReference->getUid();
        $metaData = $fileReference->toArray();
        $fileRenderer = RendererRegistry::getInstance()->getRenderer($fileReference);

        if ($fileRenderer === null) {
            $fileReference = $this->processImageFile($fileReference, $dimensions);
        }
        return [
            'publicUrl' => $this->getImageService()->getImageUri($fileReference, true),
            'properties' => [
                'title' => $metaData['title'],
                'alternative' => $metaData['alternative'],
                'description' => $metaData['description'],
                'mimeType' => $fileReference->getMimeType(),
                'type' => explode('/', $fileReference->getMimeType())[0],
                'originalUrl' => $fileReference->getOriginalFile()->getPublicUrl(),
                'fileReferenceUid' => $fileReferenceUid,
                'size' => $this->calculateKilobytesToFileSize($fileReference->getSize()),
                'link' => !empty($metaData['link']) ? $cObj->typoLink_URL([
                    'parameter' => $metaData['link']
                ]) : null,
                'dimensions' => [
                    'width' => $fileReference->getProperty('width'),
                    'height' => $fileReference->getProperty('height'),
                ],
                'cropDimensions' => [
                    'width' => $this->getCroppedDimensionalProperty($fileReference, 'width'),
                    'height' => $this->getCroppedDimensionalProperty($fileReference, 'height')
                ],
                'autoplay' => $fileReference->getProperty('autoplay'),

                'extension' => $metaData['extension']
            ]
        ];
    }

    /**
     * @param FileReference|File $image
     * @param array $dimensions
     * @return ProcessedFile
     */
    public function processImageFile($image, array $dimensions = []): ProcessedFile
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
        } catch (\UnexpectedValueException $e) {
        } catch (\RuntimeException $e) {
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * When retrieving the height or width for a media file
     * a possible cropping needs to be taken into account.
     *
     * @param FileInterface $fileObject
     * @param string $dimensionalProperty 'width' or 'height'
     *
     * @return int
     */
    protected function getCroppedDimensionalProperty(FileInterface $fileObject, $dimensionalProperty)
    {
        if (!$fileObject->hasProperty('crop') || empty($fileObject->getProperty('crop'))) {
            return $fileObject->getProperty($dimensionalProperty);
        }

        $croppingConfiguration = $fileObject->getProperty('crop');
        $cropVariantCollection = CropVariantCollection::create((string)$croppingConfiguration);
        return (int)$cropVariantCollection->getCropArea($this->cropVariant)->makeAbsoluteBasedOnFile($fileObject)->asArray()[$dimensionalProperty];
    }

    /**
     * @param int $value
     * @return string
     */
    protected function calculateKilobytesToFileSize(?int $value): string
    {
        $units = LocalizationUtility::translate('viewhelper.format.bytes.units', 'fluid');
        $units = GeneralUtility::trimExplode(',', $units, true);

        if (is_numeric($value)) {
            $value = (float)$value;
        }
        if (!is_int($value) && !is_float($value)) {
            $value = 0;
        }
        $bytes = max($value, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(2, 10 * $pow);

        return number_format(round($bytes, 4 * 2)) . ' ' . $units[$pow];
    }

    /**
     * @return ImageService
     */
    protected function getImageService(): ImageService
    {
        return GeneralUtility::makeInstance(ImageService::class);
    }
}
