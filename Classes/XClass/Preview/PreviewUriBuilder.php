<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Preview;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use InvalidArgumentException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
readonly class PreviewUriBuilder extends \TYPO3\CMS\Workspaces\Preview\PreviewUriBuilder
{
    private function getHeadlessMode(): HeadlessModeInterface
    {
        return GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    private function getSiteFinder(): SiteFinder
    {
        return GeneralUtility::makeInstance(SiteFinder::class);
    }

    private function getUrlUtility(): HeadlessFrontendUrlInterface
    {
        return GeneralUtility::makeInstance(HeadlessFrontendUrlInterface::class);
    }

    public function buildUriForPage(int $uid, int $languageId = 0): string
    {
        $previewKeyword = $this->compilePreviewKeyword();
        try {
            $site = $this->getSiteFinder()->getSiteByPageId($uid);
            try {
                $language = $site->getLanguageById($languageId);
            } catch (InvalidArgumentException) {
                $language = $site->getDefaultLanguage();
            }

            $uri = $site->getRouter()->generateUri($uid, ['ADMCMD_prev' => $previewKeyword, '_language' => $language], '');

            $headlessMode = $this->getHeadlessMode()->withRequest($GLOBALS['TYPO3_REQUEST']);
            $request = $headlessMode->overrideBackendRequestBySite($site, $language);

            return $this->getUrlUtility()
                ->withRequest($request)
                ->getFrontendUrlForPage((string)$uri, $uid);

        } catch (SiteNotFoundException | InvalidRouteArgumentsException $e) {
            throw new UnableToLinkToPageException(sprintf('The link to the page with ID "%d" could not be generated: %s', $uid, $e->getMessage()), 1559794916, $e);
        }
    }
}
