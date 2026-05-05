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

/**
 * PreviewUrlHook
 *
 * @codeCoverageIgnore
 **/
class PreviewUrlHook
{
    public function __construct(private readonly UrlUtility $urlUtility) {}

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
    public function postProcess(string $previewUrl, int $pageUid, ?array $rootLine, string $anchorSection, string $viewScript, string $additionalGetVars, bool $switchFocus): string
    {
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER']->workspace !== 0) {
            return $previewUrl;
        }
        return $this->urlUtility->getFrontendUrlForPage($previewUrl, $pageUid);
    }
}
