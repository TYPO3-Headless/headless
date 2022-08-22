<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterface;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchema;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface;
use InvalidArgumentException;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

use function is_a;
use function sprintf;

/*
 * Example usage:
    10 = FriendsOfTYPO3\Headless\DataProcessing\RootSitesProcessor
    10 {
       as = sites
       # allow to override provider of data for output processor, if empty defaults to FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider
       # your-class implementing FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProviderInterfac
       # example value: Vendor\Project\RootSiteProcessing\CustomSiteProvider
       siteProvider =
       # allow to override output of processor, if empty defaults to FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchema
       # your-class implementing FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSchemaInterface
       # example value: Vendor\Project\RootSiteProcessing\CustomSiteSchema
       siteSchema =
       # provider configuration, if empty defaults to 'sorting' field from pages table
       # example value = custom_sorting
       sortingField =
       # if empty defaults to sort by "sorting" field from `pages` table
       # your-class implementing FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSortingInterface
       # example value: Vendor\Project\RootSiteProcessing\CustomSorting
       sortingImplementation =
       # list of uid of root pages should be returned, i.e. you have 5 root pages(1,2,3,4,5), but two (4,5) of not ready to display, so you can hide it
       # example value = 1,2,3
       allowedSites =
       # automatically fetch root sites from another page/separator and filter sites yaml configs by returned list from database
       # very useful when you have multi site setup in one instance.
       # example value = 1
       sitesFromPid =
       # if empty defaults to uid,title,sorting - list of columns to fetch from database and provided for SiteSchema/DomainSchema to use
       # example value = uid,title,sorting
       dbColumns =
       # if empty defaults to "title" field from pages table, get site name from database
       # example value = your-custom-field-from-pages-table
       titleField =
    }
 */

class RootSitesProcessor implements DataProcessorInterface
{
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
        $siteUid = $cObj->data['uid'] ?? null;

        if ($siteUid === null) {
            return $processedData;
        }

        $features = GeneralUtility::makeInstance(Features::class);

        if (!$features->isFeatureEnabled('headless.frontendUrls')) {
            $msg = 'headless.frontendUrls option should be enabled!';
            throw new RuntimeException($msg);
        }

        $siteProviderClass = $processorConfiguration['siteProvider'] ?? SiteProvider::class;
        $siteSchemaClass = $processorConfiguration['siteSchema'] ?? SiteSchema::class;

        if (!is_a($siteProviderClass, SiteProviderInterface::class, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid siteProvider implementation! Please provide class with %s implemented!',
                    SiteProviderInterface::class
                )
            );
        }

        if (!is_a($siteSchemaClass, SiteSchemaInterface::class, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid SiteSchema implementation! Please provide class with %s implemented!',
                    SiteSchemaInterface::class
                )
            );
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
        $processedData[$targetVariableName] = $siteSchema->process(
            $siteProvider,
            [
                'processorConfiguration' => $processorConfiguration,
                'siteUid' => $siteUid,
                'cObj' => $cObj
            ]
        );

        return $processedData;
    }
}
