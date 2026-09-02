<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class LanguageMenuProcessor extends \TYPO3\CMS\Frontend\DataProcessing\LanguageMenuProcessor
{
    use DataProcessingTrait;

    /**
     * @var array<int, string>
     */
    protected array $allowedConfigurationKeys = [
        'if',
        'if.',
        'languages',
        'languages.',
        'as',
        'addQueryString',
        'addQueryString.',

        // New properties for EXT:headless
        'appendData',
    ];

    /**
     * @var array<int, string>
     */
    protected array $removeConfigurationKeysForHmenu = [
        'languages',
        'languages.',
        'as',

        // New properties for EXT:headless
        'appendData',
    ];

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $processedData = parent::process(
            $cObj,
            $contentObjectConfiguration,
            $processorConfiguration,
            $processedData
        );

        return $this->removeDataIfnotAppendInConfiguration(
            $processorConfiguration,
            $processedData,
            (string)$cObj->stdWrapValue('as', $processorConfiguration, $this->menuDefaults['as'] ?? '')
        );
    }
}
