<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchema;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface;
use InvalidArgumentException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

class RootSitesProcessor implements DataProcessorInterface
{
    /**
     * @var ContentDataProcessor
     */
    private $contentDataProcessor;

    /**
     * @param ContentObjectRenderer $cObj
     * @param array<string,mixed> $contentObjectConfiguration
     * @param array<string,mixed> $processorConfiguration
     * @param array<string,mixed> $processedData
     * @return array<string,mixed>
     * @throws SiteNotFoundException
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $siteUid = $cObj->data['uid'];

        if ($siteUid === null) {
            return $processedData;
        }

        $this->contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class);

        $siteProviderClass = $processorConfiguration['siteProvider'] ?? SiteProvider::class;
        $siteSchemaClass = $processorConfiguration['siteSchema'] ?? SiteSchema::class;

        if (!\is_a($siteProviderClass, SiteProviderInterface::class, true)) {
            // phpcs:ignore Generic.Files.LineLength
            throw new InvalidArgumentException('Invalid siteProvider implementation! Please provide class with FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface implemented!');
        }

        if (!\is_a($siteSchemaClass, SiteSchemaInterface::class, true)) {
            // phpcs:ignore Generic.Files.LineLength
            throw new InvalidArgumentException('Invalid SiteSchema implementation! Please provide class with FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface implemented!');
        }

        /**
         * @var SiteProviderInterface $siteProvider
         */
        $siteProvider = GeneralUtility::makeInstance($siteProviderClass);
        $siteProvider->prepare($processorConfiguration, $siteUid);

        /**
         * @var SiteSchemaInterface $siteSchema
         */
        $siteSchema = GeneralUtility::makeInstance($siteSchemaClass);

        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration, 'sites');
        $processedData[$targetVariableName] = $siteSchema->process($siteProvider, [
            'processorConfiguration' => $processorConfiguration,
            'siteUid' => $siteUid,
            'cObj' => $cObj
        ]);

        return $processedData;
    }
}
