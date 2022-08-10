<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Preview;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create links to pages when in a workspace for previewing purposes
 *
 * @internal
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
            $uri = $this->prepareHeadlessUrl($site->getRouter()->generateUri($uid, ['ADMCMD_prev' => $previewKeyword, '_language' => $language], ''), $site);
            return (string)$uri;
        } catch (SiteNotFoundException | InvalidRouteArgumentsException $e) {
            throw new UnableToLinkToPageException('The page ' . $uid . ' had no proper connection to a site, no link could be built.', 1559794916);
        }
    }

    protected function prepareHeadlessUrl($url, $site): string
    {
        $siteConf = $site->getConfiguration();

        if ($siteConf['headless'] ?? false) {
            $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);
            $frontendBaseUrl = $frontendBase->resolveWithVariants('', $siteConf['baseVariants'] ?? []);

            if ($frontendBaseUrl !== '') {
                $parsedFrontendBase = parse_url($frontendBaseUrl);
                $frontendHost = $parsedFrontendBase['host'] ?? '';
                $frontendPort = $parsedFrontendBase['port'] ?? null;
            }

            $url = $url->withHost($frontendHost)->withPort($frontendPort);
        }

        return (string)$url;
    }
}
