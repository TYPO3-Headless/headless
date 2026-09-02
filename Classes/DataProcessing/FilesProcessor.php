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
     * @var array<string, string>
     */
    public array $defaults = [
        'as' => 'media',
    ];

    public function __construct(protected readonly FileUtilityInterface $fileUtility) {}

    protected ContentObjectRenderer $contentObjectRenderer;

    /**
     * @var array<string, mixed>
     */
    protected array $processorConfiguration = [];

    /**
     * The (filtered) media files to be used in the gallery
     *
     * @var FileInterface[]
     */
    protected array $fileObjects = [];

    /**
     * Process data for a gallery, for instance the CType "textmedia"
     *
     * @param ContentObjectRenderer $cObj The content object renderer, which contains data of the content element
     * @param array<string, mixed> $contentObjectConfiguration The configuration of Content Object
     * @param array<string, mixed> $processorConfiguration The configuration of this processor
     * @param array<string, mixed> $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array<string, mixed> the processed data as key/value store
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

        $properties = [];

        if (isset($processorConfiguration['processingConfiguration.'])) {
            foreach (array_keys((array)$processorConfiguration['processingConfiguration.']) as $key) {
                $properties[$key] = $cObj->stdWrapValue($key, $processorConfiguration['processingConfiguration.'], null);
            }
        }

        $this->contentObjectRenderer = $cObj;
        $this->fileUtility->setRequest($cObj->getRequest());
        $this->processorConfiguration = $processorConfiguration;

        $targetFieldName = (string)$cObj->stdWrapValue(
            'as',
            $this->processorConfiguration,
            $this->defaults['as']
        );

        if (!$this->hasFileSources($processorConfiguration)) {
            $processedData[$targetFieldName] = [];
            return $this->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData);
        }

        $this->fileObjects = $this->fetchData();
        $processedData[$targetFieldName] = $this->processFiles($properties);

        return $this->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData);
    }

    /**
     * @param array<string, mixed> $processorConfiguration
     */
    protected function hasFileSources(array $processorConfiguration): bool
    {
        foreach (['references', 'references.', 'files', 'files.', 'collections', 'collections.', 'folders', 'folders.'] as $key) {
            if (!empty($processorConfiguration[$key])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int, FileInterface>
     */
    protected function fetchData(): array
    {
        /** @var FileCollector $fileCollector */
        $fileCollector = GeneralUtility::makeInstance(FileCollector::class);

        if (!empty($this->processorConfiguration['references.'])) {
            $referencesUidList = (string)$this->contentObjectRenderer->stdWrapValue('references', $this->processorConfiguration ?? []);
            $referencesUids = GeneralUtility::intExplode(',', $referencesUidList, true);
            $fileCollector->addFileReferences($referencesUids);

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
     * @param array<string, mixed> $properties
     * @return array<int|string, mixed>|null
     */
    protected function processFiles(array $properties = []): ?array
    {
        $data = [];

        $processingConfiguration = ProcessingConfiguration::fromOptions($properties);

        foreach ($this->fileObjects as $key => $fileObject) {
            $data[$key] = $this->getFileUtility()->process(
                $fileObject,
                $processingConfiguration,
            );

            if (!$processingConfiguration->delayProcessing) {
                $data[$key] = $this->getFileUtility()->processCropVariants($fileObject, $processingConfiguration, $data[$key]);
            }
        }

        if ($processingConfiguration->flattenObject) {
            return $data[0] ?? null;
        }

        return $data;
    }

    protected function getFileUtility(): FileUtilityInterface
    {
        return $this->fileUtility;
    }
}
