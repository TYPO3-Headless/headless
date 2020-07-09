<?php

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
        if (!isset($processorConfiguration['appendData']) ||
            (int)$processorConfiguration['appendData'] === 0) {
            unset($processedData['data']);
            if (isset($processedData[$processorConfiguration['as']])
                && is_array($processedData[$processorConfiguration['as']])) {
                foreach ($processedData[$processorConfiguration['as']] as &$item) {
                    unset($item['data']);
                }
            }
        }

        return $processedData;
    }
}
