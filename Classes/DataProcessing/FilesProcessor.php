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

namespace FriendsOfTYPO3\Headless\DataProcessing;

use FriendsOfTYPO3\Headless\Utility\FileUtility;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\Resource\FileCollector;

/**
 * Class FilesProcessor
 */
class FilesProcessor implements DataProcessorInterface
{
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
                'width' => isset($processorConfiguration['processingConfiguration.']['width']) ? $processorConfiguration['processingConfiguration.']['width'] : null,
                'height' => isset($processorConfiguration['processingConfiguration.']['height']) ? $processorConfiguration['processingConfiguration.']['height'] : null,
                'minWidth' => isset($processorConfiguration['processingConfiguration.']['minWidth']) ? $processorConfiguration['processingConfiguration.']['minWidth'] : null,
                'minHeight' => isset($processorConfiguration['processingConfiguration.']['minHeight']) ? $processorConfiguration['processingConfiguration.']['minHeight'] : null,
                'maxWidth' => isset($processorConfiguration['processingConfiguration.']['maxWidth']) ? $processorConfiguration['processingConfiguration.']['maxWidth'] : null,
                'maxHeight' => isset($processorConfiguration['processingConfiguration.']['maxHeight']) ? $processorConfiguration['processingConfiguration.']['maxHeight'] : null,
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

        return $processedData;
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
                $relationTable = $this->contentObjectRenderer->stdWrapValue('table', $referenceConfiguration, $this->contentObjectRenderer->getCurrentTable());
                if (!empty($relationTable)) {
                    $fileCollector->addFilesFromRelation($relationTable, $relationField, $this->contentObjectRenderer->data);
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
            $fileCollector->addFilesFromFolders($folders, !empty($this->processorConfiguration['folders.']['recursive']));
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
     * @return array
     */
    protected function processFiles(array $dimensions = []): array
    {
        $data = [];

        foreach ($this->fileObjects as $fileObject) {
            $data[] = $this->getFileUtility()->processFile($fileObject, $dimensions);
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
