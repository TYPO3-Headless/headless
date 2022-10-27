<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * This menu processor utilizes HMENU to generate a json encoded menu
 * string that will be decoded again and assigned to JSON as
 * variable, then remove page data from content object. Additional DataProcessing is supported and will be applied
 * to each record.
 *
 * Options:
 * as - The variable to be used within the result
 * levels - Number of levels of the menu
 * expandAll = If false, submenus will only render if the parent page is active
 * includeSpacer = If true, pagetype spacer will be included in the menu
 * titleField = Field that should be used for the title
 *
 * See HMENU docs for more options.
 * https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Hmenu/Index.html
 *
 *
 * Example TypoScript configuration:
 *
 * 10 = FriendsOfTYPO3\Headless\DataProcessing\MenuProcessor
 * 10 {
 *   special = list
 *   special.value.field = pages
 *   levels = 7
 *   as = menu
 *   expandAll = 1
 *   includeSpacer = 1
 *   titleField = nav_title // title
 *   dataProcessing {
 *     10 = TYPO3\CMS\Frontend\DataProcessing\FilesProcessor
 *     10 {
 *        references.fieldName = media
 *     }
 *   }
 *
 *   # To customize JSON output you could use `overwriteMenuLevelConfig`
 *   overwriteMenuLevelConfig {
 *     stdWrap.cObject {
 *       100 = TEXT
 *       100.field = uid
 *       100.wrap = ,"uid":|
 *     }
 *   }
 * }
 *
 * @codeCoverageIgnore
 */
class MenuProcessor extends \TYPO3\CMS\Frontend\DataProcessing\MenuProcessor
{
    use DataProcessingTrait;

    /**
     * @inheritDoc
     */
    public $allowedConfigurationKeys = [
        'cache_period',
        'entryLevel',
        'entryLevel.',
        'special',
        'special.',
        'minItems',
        'minItems.',
        'maxItems',
        'maxItems.',
        'begin',
        'begin.',
        'alternativeSortingField',
        'alternativeSortingField.',
        'showAccessRestrictedPages',
        'showAccessRestrictedPages.',
        'excludeUidList',
        'excludeUidList.',
        'excludeDoktypes',
        'includeNotInMenu',
        'includeNotInMenu.',
        'alwaysActivePIDlist',
        'alwaysActivePIDlist.',
        'protectLvar',
        'addQueryString',
        'addQueryString.',
        'if',
        'if.',
        'levels',
        'levels.',
        'expandAll',
        'expandAll.',
        'includeSpacer',
        'includeSpacer.',
        'as',
        'titleField',
        'titleField.',
        'dataProcessing',
        'dataProcessing.',

        // New properties for EXT:headless
        'appendData',
        'overwriteMenuLevelConfig.',
        'overwriteMenuConfig.',
    ];

    /**
     * @inheritDoc
     */
    public $removeConfigurationKeysForHmenu = [
        'levels',
        'levels.',
        'expandAll',
        'expandAll.',
        'includeSpacer',
        'includeSpacer.',
        'as',
        'titleField',
        'titleField.',
        'dataProcessing',
        'dataProcessing.',

        // New properties for EXT:headless
        'appendData',
        'overwriteMenuLevelConfig.',
        'overwriteMenuConfig.',
    ];

    /**
     * Build the menu configuration so it can be treated by HMENU cObject and allow customization for $this->menuLevelConfig
     */
    public function buildConfiguration()
    {
        // Before rendering the actual menu via HMENU we want to update $this->menuLevelConfig
        $overwriteMenuLevelConfig = $this->getConfigurationValue('overwriteMenuLevelConfig.');
        if (\is_array($overwriteMenuLevelConfig)) {
            ArrayUtility::mergeRecursiveWithOverrule($this->menuLevelConfig, $overwriteMenuLevelConfig);
        }

        parent::buildConfiguration();

        // override built configuration
        $overwriteMenuConfig = $this->getConfigurationValue('overwriteMenuConfig.');
        if (\is_array($overwriteMenuConfig)) {
            ArrayUtility::mergeRecursiveWithOverrule($this->menuConfig, $overwriteMenuConfig);
        }
    }

    /**
     * @inheritDoc
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ) {
        $processedData = parent::process(
            $cObj,
            $contentObjectConfiguration,
            $processorConfiguration,
            $processedData
        );

        return $this->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData);
    }
}
