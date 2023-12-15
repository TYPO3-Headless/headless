<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
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
 * 10 = FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor
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
 *          parseFunc =< lib.parseFunc_RTE
 *      }
 *      link = TEXT
 *      link {
 *          field = link
 *          typolink {
 *              parameter {
 *                  field = link
 *              }
 *              returnLast = result
 *          }
 *      }
 *   }
 * }
 *
 * where "as" means the variable to be containing the result-set from the DB query.
 *
 * @codeCoverageIgnore
 */
class DatabaseQueryProcessor implements DataProcessorInterface
{
    private ContentDataProcessor $contentDataProcessor;
    private TypoScriptService $typoScriptService;

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
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
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
        $processedRecordVariables = $this->processRecordVariables($cObj, $records, $tableName, $processorConfiguration);

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
    private function processRecordVariables(ContentObjectRenderer $cObj, array $records, string $tableName, array $processorConfiguration): array
    {
        $processedRecordVariables = [];
        foreach ($records as $key => $record) {
            $recordContentObjectRenderer = $this->createContentObjectRenderer();
            $recordContentObjectRenderer->start($record, $tableName);
            $recordContentObjectRenderer->setRequest($cObj->getRequest());

            $objConf = [];
            $objName = '< ' . $tableName;

            if (isset($processorConfiguration['fields.'])) {
                $objName = 'JSON';
                $fields = $this->typoScriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['fields.']);
                $objConf = $this->typoScriptService->convertPlainArrayToTypoScriptArray(['fields' => $fields, '_typoScriptNodeValue' => 'JSON']);
            }

            $processedRecordVariables[$key] = \json_decode($recordContentObjectRenderer->cObjGetSingle($objName, $objConf), true);
            $processedRecordVariables[$key] = $this->contentDataProcessor->process($recordContentObjectRenderer, $processorConfiguration, $processedRecordVariables[$key]);

            if (isset($processorConfiguration['overrideFields.'])) {
                $overrideFields = $this->typoScriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['overrideFields.']);
                $jsonCE = $this->typoScriptService->convertPlainArrayToTypoScriptArray(['fields' => $overrideFields, '_typoScriptNodeValue' => 'JSON']);
                $record = \json_decode($recordContentObjectRenderer->cObjGetSingle('JSON', $jsonCE), true);

                foreach ($record as $fieldName => $overrideData) {
                    $processedRecordVariables[$key][$fieldName] = $overrideData;
                }
            }

            if ($processorConfiguration['returnFlattenObject'] ?? false) {
                return array_shift($processedRecordVariables);
            }
        }

        return $processedRecordVariables;
    }

    protected function createContentObjectRenderer(): ContentObjectRenderer
    {
        return GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }
}
