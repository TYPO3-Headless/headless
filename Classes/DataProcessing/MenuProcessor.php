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
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
 *   additionalFields = abstract
 * }
 *
 * @codeCoverageIgnore
 */
class MenuProcessor extends \TYPO3\CMS\Frontend\DataProcessing\MenuProcessor
{
    use DataProcessingTrait;

    /**
     * @var array<int, string>
     */
    public array $allowedConfigurationKeys = [
        'cache',
        'cache.',
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
     * @var array<int, string>
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

    public function buildConfiguration(): void
    {
        parent::buildConfiguration();

        $overwriteMenuLevelConfig = $this->processorConfiguration['overwriteMenuLevelConfig.'] ?? null;
        if (is_array($overwriteMenuLevelConfig)) {
            for ($i = 1; $i <= $this->menuLevels; $i++) {
                if (isset($this->menuConfig[$i . '.']) && is_array($this->menuConfig[$i . '.'])) {
                    ArrayUtility::mergeRecursiveWithOverrule($this->menuConfig[$i . '.'], $overwriteMenuLevelConfig);
                }
            }
        }

        $overwriteMenuConfig = $this->processorConfiguration['overwriteMenuConfig.'] ?? null;
        if (is_array($overwriteMenuConfig)) {
            ArrayUtility::mergeRecursiveWithOverrule($this->menuConfig, $overwriteMenuConfig);
        }
    }

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
    ) {
        $processedData = parent::process(
            $cObj,
            $contentObjectConfiguration,
            $processorConfiguration,
            $processedData
        );

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
     * @param array<string, mixed> $processorConfiguration
     * @return array<int, string>
     */
    protected function getAdditionalFields(array $processorConfiguration): array
    {
        $additionalFields = (string)($processorConfiguration['additionalFields'] ?? '');
        if ($additionalFields === '') {
            return [];
        }
        return GeneralUtility::trimExplode(',', $additionalFields, true);
    }

    /**
     * @param array<int|string, array<string, mixed>> $menuItems
     * @param array<int, string> $additionalFields
     * @return array<int|string, array<string, mixed>>
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
