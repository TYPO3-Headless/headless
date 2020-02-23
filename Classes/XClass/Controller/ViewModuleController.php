<?php
declare(strict_types = 1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

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
        $siteService = GeneralUtility::makeInstance(SiteService::class);

        $targetUrl = $siteService->getFrontendUrl($targetUrl, $pageId);

        return $targetUrl;
    }
}
