<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;


/**
 * This XClass allows you to render frontend URLs for workspaces
 *
 * @codeCoverageIgnore
 */
class PreviewController extends \TYPO3\CMS\Workspaces\Controller\PreviewController
{
    protected function generateUrl(Site $site, int $pageUid, array $parameters): string
    {
        $url = (string)$site->getRouter()->generateUri($pageUid, $parameters);

        if (!isset($GLOBALS['TYPO3_REQUEST'])) {
            return $url;
        }

        $headlessMode = GeneralUtility::makeInstance(HeadlessModeInterface::class);
        $headlessMode = $headlessMode->withRequest($GLOBALS['TYPO3_REQUEST']);
        $request = $headlessMode->overrideBackendRequestBySite($site, $parameters['_language'] ?? null);

        return GeneralUtility::makeInstance(UrlUtility::class)->withRequest($request)->getFrontendUrlForPage($url, $pageUid);
    }
}
