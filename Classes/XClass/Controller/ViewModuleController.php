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

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use FriendsOfTYPO3\Headless\Service\SiteService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller for viewing the frontend
 * @internal This is a specific Backend Controller implementation and is not considered part of the Public TYPO3 API.
 */
class ViewModuleController extends \TYPO3\CMS\Viewpage\Controller\ViewModuleController
{
    /**
     * Determine the url to view
     *
     * @param int $pageId
     * @param int $languageId
     * @return string
     */
    protected function getTargetUrl(int $pageId, int $languageId): string
    {
        $targetUrl = parent::getTargetUrl($pageId, $languageId);

        if (\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class)->isFeatureEnabled('FrontendBaseUrlInPagePreview')) {
            $siteService = GeneralUtility::makeInstance(SiteService::class);

            $targetUrl = $siteService->getFrontendUrl($targetUrl, $pageId);
        }

        return $targetUrl;
    }
}
