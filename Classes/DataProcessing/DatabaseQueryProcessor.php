<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Fetch records from the database, using the default .select syntax from TypoScript.
 *
 * This way, e.g. a FLUIDTEMPLATE cObject can iterate over the array of records.
 *
 * Example TypoScript configuration:
 *
 * 10 = TYPO3\CMS\Frontend\DataProcessing\DatabaseQueryProcessor
 * 10 {
 *   table = tt_content
 *   pidInList = 123
 *   as = contents
 *   fields {
 *      header = TEXT
 *      header {
 *           field = header
 *      }
 *      bodytext = TEXT
 *      bodytext {
 *          field = bodytext
 *          parseFunc =< lib.parseFunc_links
 *      }
 *      link = TEXT
 *      link {
 *          field = link
 *          typolink {
 *              parameter {
 *                  field = link
 *              }
 *              returnLast = url
 *          }
 *      }
 *   }
 * }
 *
 * where "as" means the variable to be containing the result-set from the DB query.
 */
class DatabaseQueryProcessor implements DataProcessorInterface
{
    /**
     * @var ContentDataProcessor
     */
    protected $contentDataProcessor;

    /**
     * @var TypoScriptService
     */
    private $typoScriptService;

    public function __construct(ContentDataProcessor $contentDataProcessor = null, TypoScriptService $typoScriptService = null)
    {
        $this->contentDataProcessor = $contentDataProcessor ?? GeneralUtility::makeInstance(ContentDataProcessor::class);
        $this->typoScriptService = $typoScriptService ?? GeneralUtility::makeInstance(TypoScriptService::class);
    }

    /**
     * @inheritDoc
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData): array
    {
        if (isset($processorConfiguration['if.']) && ! $cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        // the table to query, if none given, exit
        $tableName = $cObj->stdWrapValue('table', $processorConfiguration);
        if (empty($tableName)) {
            return $processedData;
        }

        if (isset($processorConfiguration['table.'])) {
            unset($processorConfiguration['table.']);
        }

        if (isset($processorConfiguration['table'])) {
            unset($processorConfiguration['table']);
        }

        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'records');

        $records = $cObj->getRecords($tableName, $processorConfiguration);
        $processedRecordVariables = $this->processRecordVariables($records, $tableName, $processorConfiguration);

        $processedData[$targetVariableName] = $processedRecordVariables;

        return $processedData;
    }

    /**
     * @param array $records
     * @param string $tableName
     * @param array $processorConfiguration
     *
     * @return array
     */
    private function processRecordVariables(array $records, string $tableName, array $processorConfiguration): array
    {
        $processedRecordVariables = [];
        foreach ($records as $key => $record) {
            $recordContentObjectRenderer = $this->createContentObjectRenderer();
            $recordContentObjectRenderer->start($record, $tableName);

            if (isset($processorConfiguration['fields.'])) {
                $fields = $this->typoScriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['fields.']);
                array_walk($fields, static function (&$item, $field) use ($processorConfiguration, $recordContentObjectRenderer) {
                    $item = $recordContentObjectRenderer->cObjGetSingle($processorConfiguration['fields.'][$field], $processorConfiguration['fields.'][$field . '.']);
                });
                $record = $fields;
            }

            $processedRecordVariables[$key] = $record;
            $processedRecordVariables[$key] = $this->contentDataProcessor->process($recordContentObjectRenderer, $processorConfiguration, $processedRecordVariables[$key]);
        }

        return $processedRecordVariables;
    }

    /**
     * @return ContentObjectRenderer
     */
    protected function createContentObjectRenderer(): ContentObjectRenderer
    {
        return GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }
}
