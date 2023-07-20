<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_merge;
use function rtrim;
use function str_contains;

class UrlUtility implements LoggerAwareInterface, HeadlessFrontendUrlInterface
{
    use LoggerAwareTrait;

    private Features $features;
    private Resolver $resolver;
    private SiteFinder $siteFinder;
    private array $conf = [];
    private array $variants = [];

    public function __construct(
        ?Features $features = null,
        ?Resolver $resolver = null,
        ?SiteFinder $siteFinder = null,
        ?ServerRequestInterface $serverRequest = null
    ) {
        $this->features = $features ?? GeneralUtility::makeInstance(Features::class);
        $this->resolver = $resolver ?? GeneralUtility::makeInstance(Resolver::class, 'site', []);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $request = $serverRequest ?? ($GLOBALS['TYPO3_REQUEST'] ?? null);

        if ($request instanceof ServerRequestInterface) {
            $this->extractConfigurationFromRequest($request, $this);
        }
    }

    public function withSite(Site $site): HeadlessFrontendUrlInterface
    {
        return $this->handleSiteConfiguration($site, clone $this);
    }

    public function withRequest(ServerRequestInterface $request): HeadlessFrontendUrlInterface
    {
        return $this->extractConfigurationFromRequest($request, clone $this);
    }

    public function withLanguage(SiteLanguage $language): HeadlessFrontendUrlInterface
    {
        return $this->handleLanguageConfiguration($language, clone $this);
    }

    public function getFrontendUrlWithSite($url, SiteInterface $site, string $returnField = 'frontendBase'): string
    {
        $configuration = $site->getConfiguration();

        if (!($configuration['headless'] ?? false)) {
            return $url;
        }

        try {
            $base = $site->getBase()->getHost();
            $port = $site->getBase()->getPort();
            $frontendBaseUrl = $this->resolveWithVariants(
                $configuration[$returnField] ?? '',
                $configuration['baseVariants'] ?? [],
                $returnField
            );

            if ($frontendBaseUrl === '') {
                return $url;
            }

            $frontendBase = GeneralUtility::makeInstance(Uri::class, $this->sanitizeBaseUrl($frontendBaseUrl));
            $frontBase = $frontendBase->getHost();
            $frontPort = $frontendBase->getPort();
            $targetUri = new Uri($this->sanitizeBaseUrl($url));

            if (str_contains($url, $base)) {
                $targetUri = $targetUri->withHost($frontBase);
            }

            if ($port === $frontPort) {
                return (string)$targetUri;
            }

            if ($frontPort) {
                $targetUri = $targetUri->withPort($frontPort);
            }

            return (string)$targetUri;
        } catch (SiteNotFoundException $e) {
            $this->logError($e->getMessage());
        }

        return $url;
    }

    public function getFrontendUrlForPage(string $url, int $pageUid, string $returnField = 'frontendBase'): string
    {
        try {
            return $this->getFrontendUrlWithSite(
                $url,
                $this->siteFinder->getSiteByPageId($pageUid),
                $returnField
            );
        } catch (SiteNotFoundException $e) {
            $this->logError($e->getMessage());
        }

        return $url;
    }

    public function getFrontendUrl(): string
    {
        return $this->resolveWithVariants($this->conf['frontendBase'] ?? '', $this->variants);
    }

    public function getProxyUrl(): string
    {
        return $this->resolveWithVariants($this->conf['frontendApiProxy'] ?? '', $this->variants, 'frontendApiProxy');
    }

    public function getStorageProxyUrl(): string
    {
        return $this->resolveWithVariants($this->conf['frontendFileApi'] ?? '', $this->variants, 'frontendFileApi');
    }

    public function resolveKey(string $key): string
    {
        return $this->resolveWithVariants($this->conf[$key] ?? '', $this->variants, $key);
    }

    public function prepareRelativeUrlIfPossible(string $targetUrl): string
    {
        $parsedTargetUrl = new Uri($this->sanitizeBaseUrl($targetUrl));
        $parsedProjectFrontendUrl = new Uri($this->sanitizeBaseUrl($this->getFrontendUrl()));

        if ($parsedTargetUrl->getHost() === $parsedProjectFrontendUrl->getHost()) {
            return $parsedTargetUrl->getPath() . ($parsedTargetUrl->getQuery() ? '?' . $parsedTargetUrl->getQuery() : '');
        }

        return $targetUrl;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function logError(string $message): void
    {
        if ($this->logger) {
            $this->logger->error($message);
        }
    }

    /**
     * If a site base contains "/" or "www.domain.com", it is ensured that
     * parse_url() can handle this kind of configuration properly.
     */
    private function sanitizeBaseUrl(string $base): string
    {
        // no protocol ("//") and the first part is no "/" (path), means that this is a domain like
        // "www.domain.com/blabla", and we want to ensure that this one then gets a "no-scheme agnostic" part
        if (!empty($base) && !str_contains($base, '//')   && $base[0] !== '/') {
            // either a scheme is added, or no scheme but with domain, or a path which is not absolute
            // make the base prefixed with a slash, so it is recognized as path, not as domain
            // treat as path
            if (!str_contains($base, '.')) {
                $base = '/' . $base;
            } else {
                // treat as domain name
                $base = '//' . $base;
            }
        }
        return $base;
    }

    private function resolveWithVariants(
        string $frontendUrl,
        array $variants = [],
        string $returnField = 'frontendBase'
    ): string {
        $frontendUrl = rtrim($frontendUrl, '/');
        if ($variants === []) {
            return $frontendUrl;
        }

        foreach ($variants as $baseVariant) {
            try {
                if ($this->resolver->evaluate($baseVariant['condition'])) {
                    return rtrim($baseVariant[$returnField] ?? '', '/');
                }
            } catch (SyntaxError $e) {
                $this->logError($e->getMessage());
                // silently fail and do not evaluate
                // no logger here, as Site is currently cached and serialized
            }
        }
        return $frontendUrl;
    }

    private function handleLanguageConfiguration(SiteLanguage $language, HeadlessFrontendUrlInterface $object): HeadlessFrontendUrlInterface
    {
        $langConf = $language->toArray();
        $variants = $langConf['baseVariants'] ?? [];
        $frontendBase = trim($langConf['frontendBase'] ?? '');
        $frontendApiProxy = trim($langConf['frontendApiProxy'] ?? '');
        $frontendFileApi = trim($langConf['frontendFileApi'] ?? '');
        $overrides = [];

        if ($frontendBase !== '') {
            $overrides['frontendBase'] =  $frontendBase;
        }

        if ($frontendApiProxy !== '') {
            $overrides['frontendApiProxy'] =  $frontendApiProxy;
        }

        if ($frontendFileApi !== '') {
            $overrides['frontendFileApi'] =  $frontendFileApi;
        }

        $object->conf = array_merge($object->conf, $overrides);

        if ($variants !== []) {
            $object->variants = $variants;
        }

        return $object;
    }

    private function handleSiteConfiguration(Site $site, UrlUtility $object): self
    {
        $object->conf = $site->getConfiguration();
        $object->variants = $object->conf['baseVariants'] ?? [];

        return $object;
    }

    private function extractConfigurationFromRequest(ServerRequestInterface $request, HeadlessFrontendUrlInterface $object): HeadlessFrontendUrlInterface
    {
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $object->handleSiteConfiguration($site, $object);
        }

        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $object->handleLanguageConfiguration($language, $object);
        }

        return $object;
    }
}
