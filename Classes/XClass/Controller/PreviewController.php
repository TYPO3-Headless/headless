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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;

/**
 * This XClass allows you to render frontend URLs for workspaces
 * Compatible with both TYPO3 v12 and v13
 *
 * @codeCoverageIgnore
 */

// Conditional class definition based on TYPO3 version
if ((new Typo3Version())->getMajorVersion() >= 13) {
    // TYPO3 v13+ version with readonly class and Context parameter
    readonly class PreviewController extends \TYPO3\CMS\Workspaces\Controller\PreviewController
    {
        protected function generateUrl(Site $site, int $pageUid, array $parameters, ?Context $context = null): string
        {
            $url = parent::generateUrl($site, $pageUid, $parameters, $context);

            if (!isset($GLOBALS['TYPO3_REQUEST'])) {
                return $url;
            }

            $headlessMode = GeneralUtility::makeInstance(HeadlessModeInterface::class);
            $headlessMode = $headlessMode->withRequest($GLOBALS['TYPO3_REQUEST']);
            $request = $headlessMode->overrideBackendRequestBySite($site, $parameters['_language'] ?? null);

            return GeneralUtility::makeInstance(UrlUtility::class)->withRequest($request)->getFrontendUrlForPage($url, $pageUid);
        }
    }
} else {
    // TYPO3 v12 version without readonly class and without Context parameter
    class PreviewController extends \TYPO3\CMS\Workspaces\Controller\PreviewController
    {
        protected function generateUrl(Site $site, int $pageUid, array $parameters): string
        {
            $url = parent::generateUrl($site, $pageUid, $parameters);

            if (!isset($GLOBALS['TYPO3_REQUEST'])) {
                return $url;
            }

            $headlessMode = GeneralUtility::makeInstance(HeadlessModeInterface::class);
            $headlessMode = $headlessMode->withRequest($GLOBALS['TYPO3_REQUEST']);
            $request = $headlessMode->overrideBackendRequestBySite($site, $parameters['_language'] ?? null);

            return GeneralUtility::makeInstance(UrlUtility::class)->withRequest($request)->getFrontendUrlForPage($url, $pageUid);
        }
    }
}
