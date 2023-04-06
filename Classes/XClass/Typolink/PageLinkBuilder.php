<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Typolink;

use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

use function count;
use function parse_url;

/**
 * @codeCoverageIgnore
 */
class PageLinkBuilder extends \TYPO3\CMS\Frontend\Typolink\PageLinkBuilder
{
    /**
     * @inheritDoc
     */
    protected function generateUrlForPageWithSiteConfiguration(
        array $page,
        Site $siteOfTargetPage,
        array $queryParameters,
        string $fragment,
        array $conf
    ): UriInterface {
        $tsfe = $this->getTypoScriptFrontendController();
        $currentSite = $this->getCurrentSite();
        $currentSiteLanguage = $this->getCurrentSiteLanguage();
        // Happens when currently on a pseudo-site configuration
        // We assume to use the default language then
        if ($currentSite && !($currentSiteLanguage instanceof SiteLanguage)) {
            $currentSiteLanguage = $currentSite->getDefaultLanguage();
        }

        $siteLanguageOfTargetPage = $this->getSiteLanguageOfTargetPage(
            $siteOfTargetPage,
            (string)($conf['language'] ?? 'current')
        );

        // By default, it is assumed to ab an internal link or current domain's linking scheme should be used
        // Use the config option to override this.
        $useAbsoluteUrl = $conf['forceAbsoluteUrl'] ?? false;
        // Check if the current page equal to the site of the target page, now only set the absolute URL
        // Always generate absolute URLs if no current site is set
        if (
            !$currentSite
            || $currentSite->getRootPageId() !== $siteOfTargetPage->getRootPageId()
            || $siteLanguageOfTargetPage->getBase()->getHost() !== $currentSiteLanguage->getBase()->getHost()) {
            $useAbsoluteUrl = true;
        }

        $targetPageId = (int)($page['l10n_parent'] > 0 ? $page['l10n_parent'] : $page['uid']);
        $queryParameters['_language'] = $siteLanguageOfTargetPage;

        if ($fragment
            && $useAbsoluteUrl === false
            && $currentSiteLanguage === $siteLanguageOfTargetPage
            && $targetPageId === (int)$tsfe->id
            && (empty($conf['addQueryString']) || !isset($conf['addQueryString.']))
            && !($tsfe->config['config']['baseURL'] ?? false)
            && count($queryParameters) === 1 // _language is always set
        ) {
            $uri = (new Uri())->withFragment($fragment);
        } else {
            try {
                $headlessMode = GeneralUtility::makeInstance(HeadlessMode::class)->withRequest($GLOBALS['TYPO3_REQUEST']);

                if ($headlessMode->isEnabled()) {
                    $urlUtility = GeneralUtility::makeInstance(UrlUtility::class)->withSite($siteOfTargetPage);
                    $frontendBaseUrl = $urlUtility->getFrontendUrl();

                    if ($frontendBaseUrl !== '') {
                        $parsedFrontendBase = parse_url($frontendBaseUrl);
                        $queryParameters['_frontendHost'] = $parsedFrontendBase['host'] ?? '';
                    }
                }

                $uri = $siteOfTargetPage->getRouter()->generateUri(
                    $targetPageId,
                    $queryParameters,
                    $fragment,
                    $useAbsoluteUrl ? RouterInterface::ABSOLUTE_URL : RouterInterface::ABSOLUTE_PATH
                );
            } catch (InvalidRouteArgumentsException $e) {
                throw new UnableToLinkException(
                    'The target page could not be linked. Error: ' . $e->getMessage(),
                    1535472406
                );
            }
            // Override scheme if absoluteUrl is set, but only if the site defines a domain/host. Fall back to site scheme and else https.
            if ($useAbsoluteUrl && $uri->getHost()) {
                $scheme = $conf['forceAbsoluteUrl.']['scheme'] ?? false;
                if (!$scheme) {
                    $scheme = $uri->getScheme() ?: 'https';
                }
                $uri = $uri->withScheme($scheme);
            }
        }

        return $uri;
    }
}
