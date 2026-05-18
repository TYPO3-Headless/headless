<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Extract a single (maybe nested) property from a given array
 *
 * Example:
 * lib.meta.fields.ogImage = TEXT
 * lib.meta.fields.ogImage {
 *     dataProcessing {
 *         10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
 *         ...
 *
 *         20 = FriendsOfTYPO3\Headless\DataProcessing\ExtractPropertyProcessor
 *         20.key = media.publicUrl
 *         20.as = media
 *     }
 * }
 */
class ExtractPropertyProcessor implements DataProcessorInterface
{
    /**
     * Extract a single (maybe nested) property from a given array
     *
     * @param ContentObjectRenderer $cObj The content object renderer, which contains data of the content element
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
        if (empty($processorConfiguration['as'])) {
            throw new Exception('Please specify property \'as\'');
        }

        if (empty($processorConfiguration['key'])) {
            throw new Exception('Please specify property \'key\'');
        }

        $targetFieldName = (string)$cObj->stdWrapValue(
            'as',
            $processorConfiguration
        );

        $key = GeneralUtility::trimExplode('.', $processorConfiguration['key'], true);

        $value = $processedData;
        foreach ($key as $segment) {
            if (!is_array($value)) {
                $value = null;
                break;
            }
            $value = $value[$segment] ?? null;
        }

        return [
            $targetFieldName => $value,
        ];
    }
}
