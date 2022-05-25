<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Hooks;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectGetDataHookInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class ContentObjectRendererHook implements ContentObjectGetDataHookInterface
{
    private const SITE_FRONTEND_BASE      = 'site:frontendBase';
    private const SITE_FRONTEND_API_PROXY = 'site:frontendApiProxy';
    private const SITE_FRONTEND_FILE_API  = 'site:frontendFileApi';

    private const SITE_FRONTEND_KEYS = [
        self::SITE_FRONTEND_BASE,
        self::SITE_FRONTEND_API_PROXY,
        self::SITE_FRONTEND_FILE_API,
    ];

    /**
     * Extends the getData()-Method of \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer to process more/other commands
     *
     * @param string $getDataString Full content of getData-request e.g. "TSFE:id // field:title // field:uid
     * @param array $fields Current field-array
     * @param string $sectionValue Currently examined section value of the getData request e.g. "field:title
     * @param string $returnValue Current returnValue that was processed so far by getData
     * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $parentObject Parent content object
     * @return string Get data result
     */
    public function getDataExtension($getDataString, array $fields, $sectionValue, $returnValue, ContentObjectRenderer &$parentObject)
    {
        if (in_array($sectionValue, self::SITE_FRONTEND_KEYS)) {
            $url = (string)$parentObject->getRequest()->getAttribute('site')->getBase();
            $pageId = $parentObject->getRequest()->getAttribute('site')->getRootPageId();

            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class);

            if ($sectionValue === self::SITE_FRONTEND_BASE) {
                $returnValue = $urlUtility->getFrontendUrlForPage($url, $pageId, 'frontendBase');
            }

            if ($sectionValue === self::SITE_FRONTEND_API_PROXY) {
                $returnValue = $urlUtility->getFrontendUrlForPage($url, $pageId, 'frontendApiProxy');
            }

            if ($sectionValue === self::SITE_FRONTEND_FILE_API) {
                $returnValue = $urlUtility->getFrontendUrlForPage($url, $pageId, 'frontendFileApi');
            }
        }

        return $returnValue;
    }
}
