<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Preview;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use InvalidArgumentException;
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
     * Lazy-loaded dependencies. This XClass is registered via
     * $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'] in ext_localconf.php. TYPO3 instantiates
     * such classes through GeneralUtility::makeInstanceForDi() which bypasses Symfony's
     * service compilation: neither constructor injection nor #[Required] setter injection
     * are honored for SYS][Objects] XClasses. We resolve via container manually on first use.
     */
    private ?HeadlessModeInterface $headlessMode = null;
    private ?SiteFinder $siteFinder = null;
    private ?UrlUtility $urlUtility = null;

    private function getHeadlessMode(): HeadlessModeInterface
    {
        return $this->headlessMode ??= GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    private function getSiteFinder(): SiteFinder
    {
        return $this->siteFinder ??= GeneralUtility::makeInstance(SiteFinder::class);
    }

    private function getUrlUtility(): UrlUtility
    {
        return $this->urlUtility ??= GeneralUtility::makeInstance(UrlUtility::class);
    }

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

        try {
            $site = $this->getSiteFinder()->getSiteByPageId($uid);
            try {
                $language = $site->getLanguageById($languageId);
            } catch (InvalidArgumentException $e) {
                $language = $site->getDefaultLanguage();
            }

            $headlessMode = $this->getHeadlessMode()->withRequest($GLOBALS['TYPO3_REQUEST']);
            $request = $headlessMode->overrideBackendRequestBySite($site, $language);

            return $this->getUrlUtility()
                ->withRequest($request)
                ->getFrontendUrlForPage((string)$site->getRouter()->generateUri($uid, ['ADMCMD_prev' => $previewKeyword, '_language' => $language], ''), $uid);
        } catch (SiteNotFoundException | InvalidRouteArgumentsException $e) {
            throw new UnableToLinkToPageException('The page ' . $uid . ' had no proper connection to a site, no link could be built.', 1559794916);
        }
    }
}
