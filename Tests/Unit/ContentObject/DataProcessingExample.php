<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ContentObject;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

class DataProcessingExample implements DataProcessorInterface
{
    /**
     * @param ContentObjectRenderer $cObj
     * @param array<string,mixed> $contentObjectConfiguration
     * @param array<string,mixed> $processorConfiguration
     * @param array<string,mixed> $processedData
     * @return array<string,mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'sites');

        $processedData[$targetVariableName] = ['SomeCustomProcessing'];

        return $processedData;
    }
}
