<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

trait DataProcessingTrait
{
    /**
     * @param array $processorConfiguration
     * @param array $processedData
     * @return array
     */
    protected function removeDataIfnotAppendInConfiguration(array $processorConfiguration, array $processedData): array
    {
        if (
            !isset($processorConfiguration['appendData']) ||
            (int)$processorConfiguration['appendData'] !== 1
        ) {
            // Items to keep
            $removeAll = !isset($processorConfiguration['appendData']) || $processorConfiguration['appendData'] == 0;
            $keepItems = $removeAll ? [] : array_flip(array_map('trim', explode(',', $processorConfiguration['appendData'])));
            if ($removeAll)
                unset($processedData['data']);
            else
                $processedData['data'] = array_intersect_key($processedData['data'], $keepItems);
            if (
                isset($processorConfiguration['as'], $processedData[$processorConfiguration['as']])
                && is_array($processedData[$processorConfiguration['as']])
            ) {
                foreach ($processedData[$processorConfiguration['as']] as &$item) {
                    if (is_array($item) && isset($item['data'])) {
                        if ($removeAll)
                            unset($item['data']);
                        else
                            $item['data'] = array_intersect_key($item['data'], $keepItems);
                    }

                    if ($this->isMenuProcessor() && isset($item['children']) && is_array($item['children'])) {
                        $this->removeDataInChildrenNodes($item['children'], $removeAll, $keepItems);
                    }
                }
            }
        }

        return $processedData;
    }

    protected function isMenuProcessor(): bool
    {
        return __CLASS__ === MenuProcessor::class;
    }

    /**
     * Removes recursively "data" in children nodes
     *
     * @param array $children
     * @param bool $removeAll
     * @param array $keepItems
     * @param string $nodeName
     */
    private function removeDataInChildrenNodes(array &$children, bool $removeAll, array $keepItems, string $nodeName = 'children'): void
    {
        foreach ($children as &$childrenItem) {
            if ($removeAll)
                unset($childrenItem['data']);
            else
                $childrenItem['data'] = array_intersect_key($childrenItem['data'], $keepItems);
            if (isset($childrenItem[$nodeName]) && is_array($childrenItem[$nodeName])) {
                $this->removeDataInChildrenNodes($childrenItem[$nodeName], $nodeName);
            }
        }
    }
}
