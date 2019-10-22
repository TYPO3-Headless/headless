<?php
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;

/**
 * Class ImagesProcessor
 */
class ImageProcessor implements DataProcessorInterface
{
    /**
     * @var array
     */
    public $defaults = [
        'as' => 'images',
        'filesAs' => 'files',
        'galleryAs' => 'gallery'
    ];

    /**
     * The content object renderer
     *
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    /**
     * The processor configuration
     *
     * @var array
     */
    protected $processorConfiguration;

    /**
     * The (filtered) media files to be used in the gallery
     *
     * @var FileInterface[]
     */
    protected $fileObjects = [];

    /**
     * @var array
     */
    protected $galleryObjects = [];

    /**
     * Process data for a gallery, for instance the CType "textmedia"
     *
     * @param ContentObjectRenderer $cObj The content object renderer, which contains data of the content element
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ) {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $this->contentObjectRenderer = $cObj;
        $this->processorConfiguration = $processorConfiguration;

        $filesProcessedDataKey = (string)$cObj->stdWrapValue(
            'filesProcessedDataKey',
            $processorConfiguration,
            $this->defaults['filesAs']
        );
        $galleryProcessedDataKey = (string)$cObj->stdWrapValue(
            'galleryProcessedDataKey',
            $processorConfiguration,
            $this->defaults['galleryAs']
        );

        $targetFieldName = (string)$cObj->stdWrapValue(
            'as',
            $processorConfiguration,
            $this->defaults['as']
        );

        if (isset($processedData[$galleryProcessedDataKey]) && is_array($processedData[$galleryProcessedDataKey])) {
            $this->galleryObjects = $processedData[$galleryProcessedDataKey];
            $processedData[$targetFieldName] = $this->processGalleryFiles();
        }

        if (isset($processedData[$filesProcessedDataKey]) && is_array($processedData[$filesProcessedDataKey]) && (!isset($processedData[$galleryProcessedDataKey]) && !is_array($processedData[$galleryProcessedDataKey]))) {
            $this->fileObjects = $processedData[$filesProcessedDataKey];
            $processedData[$targetFieldName] = $this->processFiles();
        }

        return $processedData;
    }

    /**
     * @return array
     */
    protected function processFiles(): array
    {
        $data = [];

        foreach ($this->fileObjects as $fileObject) {
            $metaData = $fileObject->toArray();
            $data['images'][] = [
                'publicUrl' => $this->getImageService()->getImageUri($fileObject, true),
                'dimensions' => [
                    'width' => $fileObject->getProperty('width'),
                    'height' => $fileObject->getProperty('height')
                ],
                'metaData' => [
                    'title' => $metaData['title'],
                    'alternative' => $metaData['alternative'],
                    'description' => $metaData['description'],
                    'link' => !empty($metaData['link']) ? $this->contentObjectRenderer->typoLink_URL([
                        'parameter' => $metaData['link']
                    ]) : null
                ]
            ];
        }

        return $data;
    }

    /**
     * @return array
     */
    protected function processGalleryFiles(): array
    {
        $data = [];

        foreach ($this->galleryObjects['rows'] as $rowKey => $row) {
            foreach ($row['columns'] as $columnKey => $image) {
                if (!empty($image['media'])) {
                    /**
                     * @var $processedFile ProcessedFile
                     */
                    $processedFile = $this->processImageFile($image['media'], [
                        'width' => $image['dimensions']['width'],
                        'height' => $image['dimensions']['height']
                    ]);
                    $metaData = $image['media']->toArray();
                    $data['images'][] = [
                        'publicUrl' => $this->getImageService()->getImageUri($processedFile, true),
                        'dimensions' => [
                            'width' => $processedFile->getProperty('width'),
                            'height' => $processedFile->getProperty('height')
                        ],
                        'metaData' => [
                            'title' => $metaData['title'],
                            'alternative' => $metaData['alternative'],
                            'description' => $metaData['description'],
                            'link' => !empty($metaData['link']) ? $this->contentObjectRenderer->typoLink_URL([
                                'parameter' => $metaData['link']
                            ]) : null
                        ]
                    ];
                }
            }
        }
        $data['gallery'] = $this->galleryObjects;

        return $data;
    }

    /**
     * @param FileReference $image
     * @param array $dimensions
     * @return ProcessedFile
     */
    protected function processImageFile(FileReference $image, array $dimensions): ProcessedFile
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
                'minWidth' => $properties['minWidth'],
                'minHeight' => $properties['minHeight'],
                'maxWidth' => $properties['maxWidth'],
                'maxHeight' => $properties['maxHeight'],
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
