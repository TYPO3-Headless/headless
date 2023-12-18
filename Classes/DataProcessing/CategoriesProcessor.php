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

/**
 * Example usage (get categories by relation field):
 *  categories = JSON
 *  categories {
 *      dataProcessing {
 *          10 = headless-categories
 *          10 {
 *              relation.fieldName = categories
 *              as = categories
 *          }
 *      }
 *  }
 * Example usage (get categories by comma-separated-list of category ids):
 *
 *   categories = JSON
 *   categories {
 *       dataProcessing {
 *           10 = headless-categories
 *           10 {
 *               uidInList = 1,3,5
 *
 *               as = categories
 *           }
 *       }
 *   }
 *  Example usage (get categories by comma-separated-list of category ids from custom pid):
 *
 *    categories = JSON
 *    categories {
 *        dataProcessing {
 *            10 = headless-categories
 *            10 {
 *                uidInList = 1,3,5
 *                pidInList = leveluid:0
 *                recursive = 250
 *
 *                as = categories
 *            }
 *        }
 *    }
 *
 * @codeCoverageIgnore
 **/
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

        $defaultQueryConfig = [
            'pidInList' => (string)$cObj->stdWrapValue('pidInList', $processorConfiguration, 'root'),
            'recursive' => (string)$cObj->stdWrapValue('recursive', $processorConfiguration, '0'),
            'selectFields' => '{#sys_category}.{#uid} AS id, {#sys_category}.{#title}',
        ];
        $queryConfig = [];

        $uidInList = (string)$cObj->stdWrapValue('uidInList', $processorConfiguration, '');
        if (!empty($uidInList)) {
            $queryConfig = [
                'uidInList' => $uidInList,
                'languageField' => 0,
            ];
        }

        if (!empty($processorConfiguration['relation.'])) {
            $referenceConfiguration = $processorConfiguration['relation.'];
            $relationField = $cObj->stdWrapValue('fieldName', $referenceConfiguration ?? []);
            if (!empty($relationField)) {
                $relationTable = $cObj->stdWrapValue('table', $referenceConfiguration, $cObj->getCurrentTable());

                if (!empty($relationTable)) {
                    $queryConfig = [
                        'join' => '{#sys_category_record_mm} on {#sys_category_record_mm}.{#uid_local} = {#sys_category}.{#uid}',
                        'where' => '({#sys_category_record_mm}.{#tablenames} = \'' . $relationTable . '\' AND {#sys_category_record_mm}.{#fieldname} = \'' . $relationField . '\' AND {#sys_category_record_mm}.{#uid_foreign}=' . $cObj->data['uid'] . ')',
                    ];
                }
            }
        }

        if (empty($queryConfig) === true) {
            return $processedData;
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
