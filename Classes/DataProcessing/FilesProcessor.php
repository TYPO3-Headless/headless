<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use FriendsOfTYPO3\Headless\Utility\FileUtility;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\Resource\FileCollector;

/**
 * Class FilesProcessor
 *
 * @codeCoverageIgnore
 */
class FilesProcessor implements DataProcessorInterface
{
    use DataProcessingTrait;

    /**
     * @var array
     */
    public $defaults = [
        'as' => 'media',
        'filesAs' => 'files',
    ];

    /**
     * The content object renderer
     *
     * @var ContentObjectRenderer
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

        $dimensions = [];

        if (isset($processorConfiguration['processingConfiguration.'])) {
            $dimensions = [
                'width' => $cObj->stdWrapValue('width', $processorConfiguration['processingConfiguration.'] ?? [], null),
                'height' => $cObj->stdWrapValue('height', $processorConfiguration['processingConfiguration.'] ?? [], null),
                'minWidth' => $cObj->stdWrapValue('minWidth', $processorConfiguration['processingConfiguration.'] ?? [], null),
                'minHeight' => $cObj->stdWrapValue('minHeight', $processorConfiguration['processingConfiguration.'] ?? [], null),
                'maxWidth' => $cObj->stdWrapValue('maxWidth', $processorConfiguration['processingConfiguration.'] ?? [], null),
                'maxHeight' => $cObj->stdWrapValue('maxHeight', $processorConfiguration['processingConfiguration.'] ?? [], null),
            ];
        }

        $this->contentObjectRenderer = $cObj;
        $this->processorConfiguration = $processorConfiguration;

        $targetFieldName = (string)$cObj->stdWrapValue(
            'as',
            $this->processorConfiguration,
            $this->defaults['as']
        );

        $this->fileObjects = $this->fetchData();
        $processedData[$targetFieldName] = $this->processFiles($dimensions);

        return $this->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData);
    }

    /**
     * @return array
     */
    protected function fetchData(): array
    {
        /** @var FileCollector $fileCollector */
        $fileCollector = GeneralUtility::makeInstance(FileCollector::class);

        if (!empty($this->processorConfiguration['references.'])) {
            $referenceConfiguration = $this->processorConfiguration['references.'];
            $relationField = $this->contentObjectRenderer->stdWrapValue('fieldName', $referenceConfiguration);

            // If no reference fieldName is set, there's nothing to do
            if (!empty($relationField)) {
                // Fetch the references of the default element
                $relationTable = $this->contentObjectRenderer->stdWrapValue(
                    'table',
                    $referenceConfiguration,
                    $this->contentObjectRenderer->getCurrentTable()
                );
                if (!empty($relationTable)) {
                    $fileCollector->addFilesFromRelation(
                        $relationTable,
                        $relationField,
                        $this->contentObjectRenderer->data
                    );
                }
            }
        }

        $files = $this->contentObjectRenderer->stdWrapValue('files', $this->processorConfiguration);
        if ($files) {
            $files = GeneralUtility::intExplode(',', $files, true);
            $fileCollector->addFiles($files);
        }

        $collections = $this->contentObjectRenderer->stdWrapValue('collections', $this->processorConfiguration);
        if (!empty($collections)) {
            $collections = GeneralUtility::trimExplode(',', $collections, true);
            $fileCollector->addFilesFromFileCollections($collections);
        }

        $folders = $this->contentObjectRenderer->stdWrapValue('folders', $this->processorConfiguration);
        if (!empty($folders)) {
            $folders = GeneralUtility::trimExplode(',', $folders, true);
            $fileCollector->addFilesFromFolders(
                $folders,
                !empty($this->processorConfiguration['folders.']['recursive'])
            );
        }

        $sortingProperty = $this->contentObjectRenderer->stdWrapValue('sorting', $this->processorConfiguration);
        if ($sortingProperty) {
            $sortingDirection = $this->contentObjectRenderer->stdWrapValue(
                'direction',
                $this->processorConfiguration['sorting.'] ?? [],
                'ascending'
            );

            $fileCollector->sort($sortingProperty, $sortingDirection);
        }

        return $fileCollector->getFiles();
    }

    /**
     * @param array $dimensions
     * @return array|null
     */
    protected function processFiles(array $dimensions = []): ?array
    {
        $data = [];
        $cropVariant = $this->processorConfiguration['processingConfiguration.']['cropVariant'] ?? 'default';

        foreach ($this->fileObjects as $key => $fileObject) {
            if (isset($this->processorConfiguration['processingConfiguration.']['autogenerate.'])) {
                $file = $this->getFileUtility()->processFile($fileObject, $dimensions, $cropVariant);
                $targetWidth = (int)($dimensions['width'] ?: $file['properties']['dimensions']['width']);
                $targetHeight = (int)($dimensions['height'] ?: $file['properties']['dimensions']['height']);

                if (isset($this->processorConfiguration['processingConfiguration.']['autogenerate.']['retina2x']) &&
                    (int)$this->processorConfiguration['processingConfiguration.']['autogenerate.']['retina2x'] === 1 &&
                    ($targetWidth || $targetHeight)) {
                    $file['urlRetina'] = $this->getFileUtility()->processFile(
                        $fileObject,
                        array_merge(
                            $dimensions,
                            [
                                'width' => $targetWidth * FileUtility::RETINA_RATIO,
                                'height' => $targetHeight * FileUtility::RETINA_RATIO,
                            ]
                        ),
                        $cropVariant
                    )['publicUrl'];
                }

                if (isset($this->processorConfiguration['processingConfiguration.']['autogenerate.']['lqip']) &&
                    (int)$this->processorConfiguration['processingConfiguration.']['autogenerate.']['lqip'] === 1 &&
                    ($targetWidth || $targetHeight)) {
                    $file['urlLqip'] = $this->getFileUtility()->processFile(
                        $fileObject,
                        array_merge(
                            $dimensions,
                            [
                                'width' => $targetWidth * FileUtility::LQIP_RATIO,
                                'height' => $targetHeight * FileUtility::LQIP_RATIO,
                            ]
                        ),
                        $cropVariant
                    )['publicUrl'];
                }

                $data[] = $file;
            } else {
                $data[$key] = $this->getFileUtility()->processFile($fileObject, $dimensions, $cropVariant);

                $cropVariants = json_decode((string)$fileObject->getProperty('crop'), true);

                if (is_array($cropVariants) && count($cropVariants) > 1) {
                    foreach (array_keys($cropVariants) as $cropVariantName) {
                        $file = $this->getFileUtility()->processFile($fileObject, $dimensions, $cropVariantName);
                        $data[$key]['cropVariants'][$cropVariantName] = $file;
                    }
                }
            }
        }

        if (isset($this->processorConfiguration['processingConfiguration.']['returnFlattenObject']) &&
            (int)$this->processorConfiguration['processingConfiguration.']['returnFlattenObject'] === 1) {
            return $data[0] ?? null;
        }

        return $data;
    }

    /**
     * @return FileUtility
     */
    protected function getFileUtility(): FileUtility
    {
        return GeneralUtility::makeInstance(FileUtility::class);
    }
}
