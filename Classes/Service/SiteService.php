<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Service;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function is_int;
use function str_replace;
use function strpos;

class SiteService
{
    /**
     * @param string $url
     * @param int $pageUid
     * @return string
     */
    public function getFrontendUrl(string $url, int $pageUid): string
    {
        $features = GeneralUtility::makeInstance(Features::class);

        if (!$features->isFeatureEnabled('FrontendBaseUrlInPagePreview') &&
            !$features->isFeatureEnabled('headless.frontendUrls')) {
            return $url;
        }

        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $frontendBaseUtility = GeneralUtility::makeInstance(FrontendBaseUtility::class);

            $site = $siteFinder->getSiteByPageId($pageUid);
            $base = $site->getBase()->getHost();
            $port = $site->getBase()->getPort();
            $configuration = $site->getConfiguration();

            if (!array_key_exists('frontendBase', $configuration)) {
                return $url;
            }

            $frontendBaseUrl = $frontendBaseUtility->resolveWithVariants(
                $configuration['frontendBase'] ?? '',
                $configuration['baseVariants'] ?? null
            );

            if ($frontendBaseUrl !== '') {
                $frontendBase = GeneralUtility::makeInstance(Uri::class, $this->sanitizeBaseUrl($frontendBaseUrl));
                $frontBase = $frontendBase->getHost();
                $frontPort = $frontendBase->getPort();

                if (is_int(strpos($url, $base))) {
                    $url = str_replace($base, $frontBase, $url);
                }

                if ($port === $frontPort) {
                    return $url;
                }

                $url = str_replace(
                    $frontBase . ($port ? ':' . $port : ''),
                    $frontBase . ($frontPort ? ':' . $frontPort : ''),
                    $url
                );
            }
        } catch (SiteNotFoundException $exception) {
        }

        return $url;
    }

    /**
     * If a site base contains "/" or "www.domain.com", it is ensured that
     * parse_url() can handle this kind of configuration properly.
     *
     * @param string $base
     * @return string
     */
    protected function sanitizeBaseUrl(string $base): string
    {
        // no protocol ("//") and the first part is no "/" (path), means that this is a domain like
        // "www.domain.com/blabla", and we want to ensure that this one then gets a "no-scheme agnostic" part
        if (!empty($base) && strpos($base, '//') === false && $base[0] !== '/') {
            // either a scheme is added, or no scheme but with domain, or a path which is not absolute
            // make the base prefixed with a slash, so it is recognized as path, not as domain
            // treat as path
            if (strpos($base, '.') === false) {
                $base = '/' . $base;
            } else {
                // treat as domain name
                $base = '//' . $base;
            }
        }
        return $base;
    }
}
