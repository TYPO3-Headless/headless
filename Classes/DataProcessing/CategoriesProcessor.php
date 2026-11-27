<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use InvalidArgumentException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

use function array_filter;
use function array_flip;
use function array_key_exists;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;
use function strtoupper;
use function usort;

/**
 * Fetch categories as a list of objects, e.g. [{"id": 1, "title": "Category"}]
 *
 * Example TypoScript configuration (categories related to the current record):
 *
 * categories = JSON
 * categories {
 *   dataProcessing {
 *     10 = headless-categories
 *     10 {
 *       relation.fieldName = categories
 *       as = categories
 *     }
 *   }
 * }
 *
 * Example TypoScript configuration (categories by uid, in the given order):
 *
 * 10 = headless-categories
 * 10 {
 *   uidInList = 3,1,5
 *   as = categories
 * }
 *
 * Options:
 *   relation.fieldName  name of the category field of the current record
 *   relation.table      table of the current record (default: current table)
 *   uidInList           comma-separated list of category uids (used if no relation is configured)
 *   pidInList           storage pids of the categories (default: root, which means pid 0)
 *   recursive           recursion level for pidInList (default: 0)
 *   fields              sys_category fields to return (default: uid,title), "uid" is returned as "id"
 *   orderBy             sorting (default: relation sorting, or the order of uidInList)
 *   as                  target variable (default: categories)
 */
class CategoriesProcessor implements DataProcessorInterface
{
    public function __construct(protected ConnectionPool $connectionPool) {}

    /**
     * @param ContentObjectRenderer $cObj The data of the content element or page
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
    ): array {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $queryConfiguration = [
            'pidInList' => (string)$cObj->stdWrapValue('pidInList', $processorConfiguration, 'root'),
            'recursive' => (string)$cObj->stdWrapValue('recursive', $processorConfiguration, '0'),
            // select all fields, so language overlays and workspaces work as expected
            'selectFields' => '{#sys_category}.*',
        ];

        $relationConfiguration = $processorConfiguration['relation.'] ?? [];
        $relationField = (string)$cObj->stdWrapValue('fieldName', $relationConfiguration);
        $uidInList = implode(',', array_filter(GeneralUtility::intExplode(',', (string)$cObj->stdWrapValue('uidInList', $processorConfiguration), true)));

        if ($relationField !== '') {
            $relationTable = (string)$cObj->stdWrapValue('table', $relationConfiguration, $cObj->getCurrentTable());
            $connection = $this->connectionPool->getConnectionForTable('sys_category_record_mm');

            $queryConfiguration['join'] = '{#sys_category_record_mm} ON {#sys_category_record_mm}.{#uid_local} = {#sys_category}.{#uid}';
            $queryConfiguration['where'] = sprintf(
                '{#sys_category_record_mm}.{#tablenames} = %s AND {#sys_category_record_mm}.{#fieldname} = %s AND {#sys_category_record_mm}.{#uid_foreign} = %d',
                $connection->quote($relationTable),
                $connection->quote($relationField),
                // relations of translations and workspace versions are stored on their own uid
                (int)($cObj->data['_ORIG_uid'] ?? $cObj->data['_LOCALIZED_UID'] ?? $cObj->data['_PAGES_OVERLAY_UID'] ?? $cObj->data['uid'] ?? 0)
            );
            $queryConfiguration['orderBy'] = 'sys_category_record_mm.sorting_foreign';
        } elseif ($uidInList !== '') {
            $queryConfiguration['uidInList'] = $uidInList;
        } else {
            return $processedData;
        }

        $orderBy = $this->getOrderBy((string)$cObj->stdWrapValue('orderBy', $processorConfiguration));
        if ($orderBy !== '') {
            $queryConfiguration['orderBy'] = $orderBy;
        }

        $records = $cObj->getRecords('sys_category', $queryConfiguration);
        if ($relationField === '' && $orderBy === '') {
            $records = $this->sortByUidList($records, $uidInList);
        }

        $fields = $this->getFields((string)$cObj->stdWrapValue('fields', $processorConfiguration, 'uid,title'));

        $categories = [];
        foreach ($records as $record) {
            $category = [];
            foreach ($fields as $field) {
                if ($field === 'uid') {
                    $category['id'] = (int)$record['uid'];
                } elseif (array_key_exists($field, $record)) {
                    $category[$field] = $record[$field];
                }
            }
            $categories[] = $category;
        }

        $targetVariableName = (string)$cObj->stdWrapValue('as', $processorConfiguration, 'categories');
        $processedData[$targetVariableName] = $categories;

        return $processedData;
    }

    /**
     * @return list<string>
     */
    protected function getFields(string $fieldList): array
    {
        $fields = GeneralUtility::trimExplode(',', $fieldList, true);
        foreach ($fields as $field) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                throw new InvalidArgumentException(sprintf('Invalid field name "%s" in headless-categories fields', $field), 1761900000);
            }
        }

        return $fields;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    protected function sortByUidList(array $records, string $uidInList): array
    {
        $positions = array_flip(GeneralUtility::intExplode(',', $uidInList, true));
        usort(
            $records,
            static fn(array $a, array $b): int => ($positions[(int)$a['uid']] ?? PHP_INT_MAX) <=> ($positions[(int)$b['uid']] ?? PHP_INT_MAX)
        );

        return $records;
    }

    protected function getOrderBy(string $orderBy): string
    {
        foreach (QueryHelper::parseOrderBy($orderBy) as [$fieldName, $direction]) {
            if (!preg_match('/^[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)?$/', (string)$fieldName)) {
                throw new InvalidArgumentException(sprintf('Invalid field name "%s" in headless-categories orderBy', $fieldName), 1795692233);
            }
            if ($direction !== null && !in_array(strtoupper($direction), ['ASC', 'DESC'], true)) {
                throw new InvalidArgumentException(sprintf('Invalid sort direction "%s" in headless-categories orderBy', $direction), 1795687578);
            }
        }

        return $orderBy;
    }
}
