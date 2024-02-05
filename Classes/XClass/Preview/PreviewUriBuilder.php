<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Preview;

use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
class PreviewUriBuilder extends \TYPO3\CMS\Workspaces\Preview\PreviewUriBuilder
{
    /**
     * Generates a workspace preview link.
     *
     * @param int $uid The ID of the record to be linked
     * @param int $languageId the language to link to
     * @return string the full domain including the protocol http:// or https://, but without the trailing '/'
     */
    public function buildUriForPage(int $uid, int $languageId = 0): string
    {
        $previewKeyword = $this->compilePreviewKeyword(
            $this->previewLinkLifetime * 3600,
            $this->workspaceService->getCurrentWorkspace()
        );

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByPageId($uid);
            try {
                $language = $site->getLanguageById($languageId);
            } catch (\InvalidArgumentException $e) {
                $language = $site->getDefaultLanguage();
            }

            $headlessMode = GeneralUtility::makeInstance(HeadlessMode::class);
            $headlessMode = $headlessMode->withRequest($GLOBALS['TYPO3_REQUEST']);
            $request = $headlessMode->overrideBackendRequestBySite($site, $language);

            return GeneralUtility::makeInstance(UrlUtility::class)
                ->withRequest($request)
                ->getFrontendUrlForPage((string)$site->getRouter()->generateUri($uid, ['ADMCMD_prev' => $previewKeyword, '_language' => $language], ''), $uid);
        } catch (SiteNotFoundException | InvalidRouteArgumentsException $e) {
            throw new UnableToLinkToPageException('The page ' . $uid . ' had no proper connection to a site, no link could be built.', 1559794916);
        }
    }
}
