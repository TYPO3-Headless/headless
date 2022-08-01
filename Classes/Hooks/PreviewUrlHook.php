<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
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
     * @param array|null $rootLine
     * @param string $anchorSection
     * @param string $viewScript
     * @param string $additionalGetVars
     * @param bool $switchFocus
     * @return string The processed preview URL
     */
    public function postProcess(string $previewUrl, int $pageUid, array $rootLine = null, string $anchorSection, string $viewScript, string $additionalGetVars, bool $switchFocus): string
    {
        // Only rewrite $previewUrl if we're not in a workspace, because
        // for workspaces that preview URL is a BE URL and looks like this:
        // https://example.org/typo3/index.php?route=%2Fworkspace%2Fpreview-control%2F&token=...&id=...
        if ($GLOBALS['BE_USER']->workspace === 0) {
            $siteService = GeneralUtility::makeInstance(SiteService::class);
            $previewUrl = $siteService->getFrontendUrl($previewUrl, $pageUid);
        }

        return $previewUrl;
    }
}
