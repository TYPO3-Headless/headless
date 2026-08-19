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
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function array_merge;
use function array_unique;
use function in_array;
use function ltrim;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

class UrlUtility implements LoggerAwareInterface, HeadlessFrontendUrlInterface
{
    use LoggerAwareTrait;

    /** @var array<string, mixed> */
    private array $conf = [];
    /** @var array<int, array<string, mixed>> */
    private array $variants = [];
    /** @var array<int, string> */
    private array $frontendDomains = [];
    /** @var array<int, string> */
    private array $backendDomains = [];

    /** @var array<string, bool> */
    private array $variantConditionCache = [];

    public function __construct(
        private readonly Resolver $resolver,
        private readonly SiteFinder $siteFinder,
        private HeadlessModeInterface $headlessMode,
    ) {}

    public function withSite(Site $site): HeadlessFrontendUrlInterface
    {
        $clone = clone $this;
        $clone->applySite($site);
        return $clone;
    }

    public function withRequest(ServerRequestInterface $request): HeadlessFrontendUrlInterface
    {
        $clone = clone $this;
        $clone->applyRequest($request);
        return $clone;
    }

    public function withLanguage(SiteLanguage $language): HeadlessFrontendUrlInterface
    {
        $clone = clone $this;
        $clone->applyLanguage($language);
        return $clone;
    }

    /**
     * @param string|null $url
     */
    public function getFrontendUrlWithSite($url, SiteInterface $site, string $returnField = 'frontendBase'): string
    {
        $clone = clone $this;
        $clone->applySite($site);
        $siteLanguage = $clone->collectLanguageDomainsAndMatch($site, $url);
        if ($siteLanguage !== null) {
            $clone->applyLanguage($siteLanguage);
        }

        $targetUri = new Uri($clone->sanitizeBaseUrl($url));

        if (!$clone->headlessMode->isEnabled() ||
            $targetUri->getHost() === '' ||
            $clone->isExternalUrl($targetUri->getHost()) ||
            $clone->alreadyFrontendLink($targetUri->getHost())) {
            return $url;
        }

        try {
            $frontendBaseUrl = $clone->resolveWithVariants(
                $clone->conf[$returnField] ?? '',
                $clone->variants,
                $returnField
            );

            if ($frontendBaseUrl === '') {
                return $url;
            }

            $frontendBase = GeneralUtility::makeInstance(Uri::class, $clone->sanitizeBaseUrl($frontendBaseUrl));

            $scheme = strtolower($frontendBase->getScheme());
            if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
                return $url;
            }

            $targetUri = $targetUri->withHost($frontendBase->getHost());

            if ($targetUri->getScheme() === '') {
                $targetUri = $targetUri->withScheme($frontendBase->getScheme());
            }

            $frontExtraPath = $frontendBase->getPath();
            if ($frontExtraPath) {
                $targetUri = $targetUri->withPath(
                    $clone->handleFrontendAndBackendPaths($frontExtraPath, $targetUri, $site->getBase()->getPath())
                );
            }

            $targetUri = $targetUri->withPort($frontendBase->getPort());

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
            return '/' . ltrim($parsedTargetUrl->getPath() . ($parsedTargetUrl->getQuery() ? '?' . $parsedTargetUrl->getQuery() : ''), '/');
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
        if (str_starts_with($base, '#')) {
            return $base;
        }

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

    /**
     * @param array<int, array<string, mixed>> $variants
     */
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
            $condition = (string)($baseVariant['condition'] ?? '');
            if ($condition === '') {
                continue;
            }
            try {
                if (!array_key_exists($condition, $this->variantConditionCache)) {
                    $this->variantConditionCache[$condition] = (bool)$this->resolver->evaluate($condition);
                }
                if ($this->variantConditionCache[$condition]) {
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

    private function applySite(Site $site): void
    {
        $this->conf = $site->getConfiguration();
        $this->variants = $this->conf['baseVariants'] ?? [];
        $this->variantConditionCache = [];
        $this->frontendDomains = [];
        $this->backendDomains = [$site->getBase()->getHost()];

        foreach ($this->variants as $variant) {
            $variantBase = trim($variant['base'] ?? '');
            if ($variantBase !== '') {
                $this->backendDomains[] = $this->hostFromBase($variantBase);
            }
        }

        $base = trim($this->conf['frontendBase'] ?? '');
        if ($base !== '') {
            $this->frontendDomains[] = $this->hostFromBase($base);
        }
    }

    private function applyLanguage(SiteLanguage $language): void
    {
        $langConf = $language->toArray();
        $variants = $langConf['baseVariants'] ?? [];
        $frontendBase = trim($langConf['frontendBase'] ?? '');
        $frontendApiProxy = trim($langConf['frontendApiProxy'] ?? '');
        $frontendFileApi = trim($langConf['frontendFileApi'] ?? '');
        $overrides = [];

        if ($language->getBase()->getHost() !== '') {
            $this->backendDomains[] = $language->getBase()->getHost();
        }

        if ($frontendBase !== '') {
            $overrides['frontendBase'] = $frontendBase;
            $this->frontendDomains[] = $this->hostFromBase($frontendBase);
        }
        if ($frontendApiProxy !== '') {
            $overrides['frontendApiProxy'] = $frontendApiProxy;
        }
        if ($frontendFileApi !== '') {
            $overrides['frontendFileApi'] = $frontendFileApi;
        }

        $this->conf = array_merge($this->conf, $overrides);

        if ($variants !== []) {
            $this->variants = $variants;
            $this->variantConditionCache = [];
        }
    }

    private function applyRequest(ServerRequestInterface $request): void
    {
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $this->applySite($site);
        }

        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $this->applyLanguage($language);
        }

        $this->headlessMode = $this->headlessMode->withRequest($request);
    }

    private function handleFrontendAndBackendPaths(string $frontendPath, UriInterface $targetUri, string $baseBackendPath = ''): string
    {
        $frontendPath = rtrim($frontendPath, '/');
        $targetPath = $targetUri->getPath();
        if ($targetPath === '') {
            return $frontendPath;
        }
        return $frontendPath . '/' . ltrim(substr($targetPath, strlen($baseBackendPath)), '/');
    }

    private function collectLanguageDomainsAndMatch(SiteInterface $site, string $backendUrl): ?SiteLanguage
    {
        $backendUri = GeneralUtility::makeInstance(Uri::class, $this->sanitizeBaseUrl($backendUrl));
        $matchedLanguage = null;
        foreach ($site->getLanguages() as $language) {
            $base = trim($language->toArray()['frontendBase'] ?? '');
            if ($base === '') {
                continue;
            }

            if ($language->getBase()->getHost() !== '') {
                $this->backendDomains[] = $language->getBase()->getHost();
            }
            $this->frontendDomains[] = $this->hostFromBase($base);

            if ($language->getBase()->getHost() === $backendUri->getHost()) {
                $matchedLanguage = $language;
            } elseif ($backendUri->getPath() !== '/' && str_starts_with($backendUri->getPath(), $language->getBase()->getPath())) {
                $matchedLanguage = $language;
            }
        }

        $this->backendDomains = array_unique($this->backendDomains);
        $this->frontendDomains = array_unique($this->frontendDomains);

        return $matchedLanguage;
    }

    private function hostFromBase(string $base): string
    {
        return (new Uri($this->sanitizeBaseUrl($base)))->getHost();
    }

    protected function alreadyFrontendLink(string $url): bool
    {
        return in_array($url, $this->frontendDomains, true);
    }

    protected function isExternalUrl(string $url): bool
    {
        return !in_array($url, $this->backendDomains, true)
            && !in_array($url, $this->frontendDomains, true);
    }
}
