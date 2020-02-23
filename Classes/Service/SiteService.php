<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Service;

use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteService
{
    /**
     * @param $url
     * @param $pageUid
     * @return string
     */
    public function getFrontendUrl($url, $pageUid): string
    {
        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageUid);
            $base = $site->getBase()->getHost();
            $configuration = $site->getConfiguration();
            $frontendBaseUrl = $this->resolveFrontendBaseWithVariants(
                $configuration['frontendBase'] ?? '',
                $configuration['baseVariants'] ?? null
            );

            if ($frontendBaseUrl !== '') {
                $frontendBase = new Uri($this->sanitizeBaseUrl($frontendBaseUrl));
                $frontBase = $frontendBase->getHost();

                if (is_int(strpos($url, $base))) {
                    $url = str_replace($base, $frontBase, $url);
                }
            }
        } catch (SiteNotFoundException $exception) {
        }
        return $url;
    }

    /**
     * @param string $frontendUrl
     * @param array|null $baseVariants
     * @return string
     */
    protected function resolveFrontendBaseWithVariants(string $frontendUrl, ?array $baseVariants): string
    {
        if (!empty($baseVariants)) {
            $expressionLanguageResolver = GeneralUtility::makeInstance(
                Resolver::class,
                'site',
                []
            );
            foreach ($baseVariants as $baseVariant) {
                try {
                    if ($expressionLanguageResolver->evaluate($baseVariant['condition'])) {
                        $frontendUrl = $baseVariant['frontendBase'];
                        break;
                    }
                } catch (SyntaxError $e) {
                    // silently fail and do not evaluate
                    // no logger here, as Site is currently cached and serialized
                }
            }
        }
        return $frontendUrl;
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
