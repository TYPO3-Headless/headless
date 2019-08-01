<?php
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
 * }
 */
class MenuProcessor extends \TYPO3\CMS\Frontend\DataProcessing\MenuProcessor
{
    /**
     * @var array
     */
    protected $menuLevelConfig = [
        'doNotLinkIt' => '1',
        'wrapItemAndSub' => '{|}, |*| {|}, |*| {|}',
        'stdWrap.' => [
            'cObject' => 'COA',
            'cObject.' => [
                '20' => 'TEXT',
                '20.' => [
                    'field' => 'nav_title // title',
                    'trim' => '1',
                    'wrap' => '"title":|',
                    'preUserFunc' => 'TYPO3\CMS\Frontend\DataProcessing\MenuProcessor->jsonEncodeUserFunc'
                ],
                '21' => 'TEXT',
                '21.' => [
                    'value' => self::LINK_PLACEHOLDER,
                    'wrap' => ',"link":|',
                ],
                '22' => 'TEXT',
                '22.' => [
                    'value' => self::TARGET_PLACEHOLDER,
                    'wrap' => ',"target":|',
                ],
                '30' => 'TEXT',
                '30.' => [
                    'value' => '0',
                    'wrap' => ',"active":|'
                ],
                '40' => 'TEXT',
                '40.' => [
                    'value' => '0',
                    'wrap' => ',"current":|'
                ],
                '50' => 'TEXT',
                '50.' => [
                    'value' => '0',
                    'wrap' => ',"spacer":|'
                ]
            ]
        ]
    ];
}
