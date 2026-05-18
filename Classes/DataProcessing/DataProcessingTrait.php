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
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    protected function removeDataIfnotAppendInConfiguration(array $processorConfiguration, array $processedData): array
    {
        if (!isset($processorConfiguration['appendData']) ||
            (int)$processorConfiguration['appendData'] === 0) {
            unset($processedData['data']);
            if (isset($processorConfiguration['as'], $processedData[$processorConfiguration['as']])
                && is_array($processedData[$processorConfiguration['as']])) {
                foreach ($processedData[$processorConfiguration['as']] as &$item) {
                    if (is_array($item) && isset($item['data'])) {
                        unset($item['data']);
                    }

                    if ($this->isMenuProcessor() && isset($item['children']) && is_array($item['children'])) {
                        $this->removeDataInChildrenNodes($item['children']);
                    }
                }
            }
        }

        return $processedData;
    }

    protected function isMenuProcessor(): bool
    {
        return $this instanceof MenuProcessor;
    }

    /**
     * Removes recursively "data" in children nodes
     *
     * @param array<int|string, array<string, mixed>> $children
     */
    private function removeDataInChildrenNodes(array &$children, string $nodeName = 'children'): void
    {
        foreach ($children as &$childrenItem) {
            unset($childrenItem['data']);
            if (isset($childrenItem[$nodeName]) && is_array($childrenItem[$nodeName])) {
                $this->removeDataInChildrenNodes($childrenItem[$nodeName], $nodeName);
            }
        }
    }
}
