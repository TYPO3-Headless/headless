<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/*
 * Example usage (get categories by relation record):
    categories = JSON
    categories {
        dataProcessing {
            10 = headless-categories
            10 {
                relationTable = pages
                relationUid.field = uid

                as = categories
            }
        }
    }
 * Example usage (get categories by comma-separated-list of category ids):

    categories = JSON
    categories {
        dataProcessing {
            10 = headless-categories
            10 {
                categoryIdList = 1,3,5

                as = categories
            }
        }
    }
 */

class CategoriesProcessor implements DataProcessorInterface
{
    /**
     * Fetches categories from the database
     *
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     *
     * @return array the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $relationTable = $cObj->stdWrapValue('relationTable', $processorConfiguration);
        $relationUid = (int)$cObj->stdWrapValue('relationUid', $processorConfiguration);
        $categoryIdList = (string)$cObj->stdWrapValue('categoryIdList', $processorConfiguration, '');

        $defaultQueryConfig = [
            'pidInList' => 'root',
            'selectFields' => 'uid AS id,title',
        ];

        if (empty($categoryIdList) === false) {
            $queryConfig = [
                'where' => '{#sys_category.uid} IN (' . $categoryIdList . ')',
                'languageField' => 0,
            ];
        }

        if (empty($relationTable) === false && empty($relationUid) === false) {
            $queryConfig = [
                'join' => 'sys_category_record_mm on sys_category_record_mm.uid_local = sys_category.uid',
                'where' => '({#sys_category_record_mm.tablenames} = \'' . $relationTable . '\' AND {#sys_category_record_mm.uid_foreign}=' . $relationUid . ')',
            ];
        }

        ArrayUtility::mergeRecursiveWithOverrule(
            $queryConfig,
            $defaultQueryConfig
        );

        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'categories');
        $categories = $cObj->getRecords('sys_category', $queryConfig);

        $processedRecordVariables = [];
        foreach ($categories as $key => $category) {
            $processedRecordVariables[$key] = ArrayUtility::filterRecursive($category, function ($key) {
                return $key === 'id' || $key === 'title';
            }, ARRAY_FILTER_USE_KEY);
        }

        $processedData[$targetVariableName] = $processedRecordVariables;

        return $processedData;
    }
}
