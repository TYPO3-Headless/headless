<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use FriendsOfTYPO3\Headless\Utility\File\ProcessingConfiguration;
use FriendsOfTYPO3\Headless\Utility\FileUtilityInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class GalleryProcessor extends \TYPO3\CMS\Frontend\DataProcessing\GalleryProcessor
{
    use DataProcessingTrait;

    /**
     * @var array<string, FileReference>
     */
    protected $fileReferenceCache = [];

    /**
     * @var array<string, array{width:int, height:int}>
     */
    protected array $croppedDimensionCache = [];

    /**
     * @var array<int, array<string, string|array<mixed>>>
     */
    protected $fileObjects = [];

    protected ProcessingConfiguration $processorConfigurationObject;

    public function __construct(
        protected readonly FileUtilityInterface $fileUtility,
        protected readonly ImageService $imageService,
    ) {}

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ) {
        $this->processorConfigurationObject = ProcessingConfiguration::fromOptions($processorConfiguration);
        $this->fileUtility->setRequest($cObj->getRequest());

        $processedData = parent::process(
            $cObj,
            $contentObjectConfiguration,
            $processorConfiguration,
            $processedData
        );

        return $this->removeDataIfnotAppendInConfiguration(
            $processorConfiguration,
            $processedData,
            (string)$cObj->stdWrapValue('as', $processorConfiguration, 'gallery')
        );
    }

    /**
     * @inheritDoc
     *
     * replaced only calls to $this->getCroppedDimensionalPropertyFromProcessedFile()
     * because of already processed files by FilesProcessor
     */
    protected function calculateMediaWidthsAndHeights(): void
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
                    $currentMediaScaling = $this->equalMediaHeight / max($this->getCroppedDimensionalPropertyFromProcessedFile(
                        $this->fileObjects[$fileKey],
                        'height'
                    ), 1);
                    $totalRowWidth += $this->getCroppedDimensionalPropertyFromProcessedFile(
                        $this->fileObjects[$fileKey],
                        'width'
                    ) * $currentMediaScaling;
                }
                $maximumRowWidth = max($totalRowWidth, $maximumRowWidth);
                $mediaInRowScaling = $totalRowWidth / $galleryWidthMinusBorderAndSpacing;
                $mediaScalingCorrection = max($mediaInRowScaling, $mediaScalingCorrection);
            }

            // Set the corrected dimensions for each media element
            foreach ($this->fileObjects as $key => $fileObject) {
                $mediaHeight = floor($this->equalMediaHeight / $mediaScalingCorrection);
                $mediaWidth = floor(
                    $this->getCroppedDimensionalPropertyFromProcessedFile(
                        $fileObject,
                        'width'
                    ) * ($mediaHeight / max($this->getCroppedDimensionalPropertyFromProcessedFile(
                        $fileObject,
                        'height'
                    ), 1))
                );
                $this->mediaDimensions[$key] = [
                    'width' => $mediaWidth,
                    'height' => $mediaHeight,
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
                    $this->getCroppedDimensionalPropertyFromProcessedFile(
                        $fileObject,
                        'height'
                    ) * ($mediaWidth / max($this->getCroppedDimensionalPropertyFromProcessedFile(
                        $fileObject,
                        'width'
                    ), 1))
                );
                $this->mediaDimensions[$key] = [
                    'width' => $mediaWidth,
                    'height' => $mediaHeight,
                ];
            }

            // Recalculate gallery width
            $this->galleryData['width'] = floor($totalRowWidth / $mediaScalingCorrection);
            // Automatic setting of width and height
        } else {
            $maxMediaWidth = (int)($galleryWidthMinusBorderAndSpacing / $this->galleryData['count']['columns']);
            foreach ($this->fileObjects as $key => $fileObject) {
                $croppedWidth = $this->getCroppedDimensionalPropertyFromProcessedFile($fileObject, 'width');
                $mediaWidth = $croppedWidth > 0 ? min($maxMediaWidth, $croppedWidth) : $maxMediaWidth;
                $mediaHeight = floor(
                    $this->getCroppedDimensionalPropertyFromProcessedFile(
                        $fileObject,
                        'height'
                    ) * ($mediaWidth / max($this->getCroppedDimensionalPropertyFromProcessedFile(
                        $fileObject,
                        'width'
                    ), 1))
                );
                $this->mediaDimensions[$key] = [
                    'width' => $mediaWidth,
                    'height' => $mediaHeight,
                ];
            }
        }
    }

    /**
     * Replaces original method (because of already processed files)
     *
     * @param array<string, mixed> $processedFile
     */
    protected function getCroppedDimensionalPropertyFromProcessedFile(array $processedFile, string $property): int
    {
        if ($this->processorConfigurationObject->legacyReturn) {
            if (empty($processedFile['properties']['crop'])) {
                return (int)($this->processorConfigurationObject->flattenProperties ? ($processedFile['properties'][$property] ?? 0) : ($processedFile['properties']['dimensions'][$property] ?? 0));
            }

            $croppingConfiguration = $processedFile['properties']['crop'];
        } else {
            if (empty($processedFile['crop'])) {
                return (int)($this->processorConfigurationObject->flattenProperties ? ($processedFile[$property] ?? 0) : ($processedFile['dimensions'][$property] ?? 0));
            }

            $croppingConfiguration = $processedFile['crop'];
        }

        $cacheKey = $this->createFileCacheKey($processedFile);

        if (!isset($this->croppedDimensionCache[$cacheKey])) {
            $cropArea = CropVariantCollection::create((string)$croppingConfiguration)
                ->getCropArea($this->cropVariant)
                ->makeAbsoluteBasedOnFile($this->createFileObject($processedFile))
                ->asArray();

            $this->croppedDimensionCache[$cacheKey] = [
                'width' => (int)($cropArea['width'] ?? 0),
                'height' => (int)($cropArea['height'] ?? 0),
            ];
        }

        return $this->croppedDimensionCache[$cacheKey][$property] ?? 0;
    }

    /**
     * Prepare the gallery data
     *
     * Make an array for rows, columns and configuration
     */
    protected function prepareGalleryData(): void
    {
        for ($row = 1; $row <= $this->galleryData['count']['rows']; $row++) {
            for ($column = 1; $column <= $this->galleryData['count']['columns']; $column++) {
                $fileKey = (($row - 1) * $this->galleryData['count']['columns']) + $column - 1;
                $fileObj = $this->fileObjects[$fileKey] ?? null;

                if ($fileObj !== null && (($fileObj['properties']['type'] ?? '') === 'image' || ($fileObj['type'] ?? '') === 'image')) {
                    $properties = $this->processorConfigurationObject->legacyReturn ? $fileObj['properties'] : $fileObj;
                    $fileReferenceUid = $properties['fileReferenceUid'] ?? null;
                    $image = $this->getImageService()->getImage(
                        (string)($fileReferenceUid ?? $properties['uidLocal']),
                        null,
                        $fileReferenceUid !== null
                    );
                    $fileObj = $this->getFileUtility()->process(
                        $image,
                        $this->processorConfigurationObject->withOptions($this->mediaDimensions[$fileKey] ?? [])
                    );

                    $fileObj = $this->getFileUtility()->processCropVariants(
                        $image,
                        $this->processorConfigurationObject,
                        $fileObj
                    );
                }

                $this->galleryData['rows'][$row]['columns'][$column] = $fileObj;
            }
        }

        $this->galleryData['columnSpacing'] = $this->columnSpacing;
        $this->galleryData['border']['enabled'] = $this->borderEnabled;
        $this->galleryData['border']['width'] = $this->borderWidth;
        $this->galleryData['border']['padding'] = $this->borderPadding;
    }

    /**
     * @return FileUtilityInterface
     */
    protected function getFileUtility(): FileUtilityInterface
    {
        return $this->fileUtility;
    }

    /**
     * @return ImageService
     */
    protected function getImageService(): ImageService
    {
        return $this->imageService;
    }

    /**
     * small helper for handling cropping based on already processed file
     *
     * @param array<string, mixed> $processedFile
     */
    protected function createFileObject(array $processedFile): FileInterface
    {
        $uid = $this->processorConfigurationObject->legacyReturn ? (int)$processedFile['properties']['uidLocal'] : $processedFile['uidLocal'];
        $cacheKey = $this->createFileCacheKey($processedFile);
        if (!isset($this->fileReferenceCache[$cacheKey])) {
            $this->fileReferenceCache[$cacheKey] = GeneralUtility::makeInstance(
                FileReference::class,
                array_merge(
                    $this->processorConfigurationObject->legacyReturn ? $processedFile['properties'] : $processedFile,
                    $this->processorConfigurationObject->legacyReturn ? $processedFile['properties']['dimensions'] : $processedFile['dimensions'],
                    ['uid_local' => $uid]
                )
            );
        }

        return $this->fileReferenceCache[$cacheKey];
    }

    /**
     * @param array<string, mixed> $processedFile
     */
    protected function createFileCacheKey(array $processedFile): string
    {
        $properties = $this->processorConfigurationObject->legacyReturn ? $processedFile['properties'] : $processedFile;

        if (isset($properties['fileReferenceUid'])) {
            return 'ref-' . $properties['fileReferenceUid'];
        }

        return 'file-' . (int)$properties['uidLocal'];
    }
}
