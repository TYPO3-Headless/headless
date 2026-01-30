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

use function is_array;

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
 *   # To add additional fields from page data to menu items
 *   additionalFields = abstract
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
    public array $allowedConfigurationKeys = [
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
        'additionalFields',
        'additionalFields.',
    ];

    /**
     * @inheritDoc
     */
    public array $removeConfigurationKeysForHmenu = [
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
        'additionalFields',
        'additionalFields.',
    ];

    /**
     * Build the menu configuration so it can be treated by HMENU cObject and allow customization
     */
    public function buildConfiguration(): void
    {
        parent::buildConfiguration();

        // After parent builds configuration, apply level-specific overrides to each level
        $overwriteMenuLevelConfig = $this->processorConfiguration['overwriteMenuLevelConfig.'] ?? null;
        if (is_array($overwriteMenuLevelConfig)) {
            // Apply to each menu level that was configured
            for ($i = 1; $i <= $this->menuLevels; $i++) {
                if (isset($this->menuConfig[$i . '.']) && is_array($this->menuConfig[$i . '.'])) {
                    ArrayUtility::mergeRecursiveWithOverrule($this->menuConfig[$i . '.'], $overwriteMenuLevelConfig);
                }
            }
        }

        // Override built configuration
        $overwriteMenuConfig = $this->processorConfiguration['overwriteMenuConfig.'] ?? null;
        if (is_array($overwriteMenuConfig)) {
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

        // Add additional fields from page data to menu items
        $additionalFields = $this->getAdditionalFields($processorConfiguration);
        if ($additionalFields !== [] && isset($processedData[$this->menuTargetVariableName])) {
            $processedData[$this->menuTargetVariableName] = $this->addAdditionalFieldsToMenuItems(
                $processedData[$this->menuTargetVariableName],
                $additionalFields
            );
        }

        return $this->removeDataIfnotAppendInConfiguration($processorConfiguration, $processedData);
    }

    /**
     * Get additional fields to include from configuration
     */
    protected function getAdditionalFields(array $processorConfiguration): array
    {
        $additionalFields = $processorConfiguration['additionalFields'] ?? '';
        if ($additionalFields === '') {
            return [];
        }
        return array_map('trim', explode(',', $additionalFields));
    }

    /**
     * Add additional fields from page data to menu items recursively
     */
    protected function addAdditionalFieldsToMenuItems(array $menuItems, array $additionalFields): array
    {
        foreach ($menuItems as $key => $item) {
            if (isset($item['data']) && is_array($item['data'])) {
                foreach ($additionalFields as $field) {
                    if (array_key_exists($field, $item['data'])) {
                        $menuItems[$key][$field] = $item['data'][$field];
                    }
                }
            }
            if (isset($item['children']) && is_array($item['children'])) {
                $menuItems[$key]['children'] = $this->addAdditionalFieldsToMenuItems($item['children'], $additionalFields);
            }
        }
        return $menuItems;
    }
}
