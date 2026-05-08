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

/**
 * @codeCoverageIgnore
 */
class LanguageMenuProcessor extends \TYPO3\CMS\Frontend\DataProcessing\LanguageMenuProcessor
{
    use DataProcessingTrait;

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

        return $this->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData);
    }
}
