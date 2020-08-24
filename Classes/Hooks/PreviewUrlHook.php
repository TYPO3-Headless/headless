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

namespace FriendsOfTYPO3\Headless\Hooks;

use FriendsOfTYPO3\Headless\Service\SiteService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PreviewUrlHook
 **/
class PreviewUrlHook
{
    /**
     * @param string $previewUrl
     * @param int $pageUid
     * @param array $rootLine
     * @param string $anchorSection
     * @param string $viewScript
     * @param string $additionalGetVars
     * @param bool $switchFocus
     * @return string The processed preview URL
     */
    public function postProcess(string $previewUrl, int $pageUid, array $rootLine, string $anchorSection, string $viewScript, string $additionalGetVars, bool $switchFocus): string
    {
        $siteService = GeneralUtility::makeInstance(SiteService::class);
        $previewUrl = $siteService->getFrontendUrl($previewUrl, $pageUid);

        return $previewUrl;
    }
}
