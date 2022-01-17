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
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function count;
use function rtrim;
use function str_replace;
use function strpos;

class UrlUtility implements LoggerAwareInterface, HeadlessFrontendUrlInterface
{
    use LoggerAwareTrait;

    private Features $features;
    private Resolver $resolver;
    private SiteFinder $siteFinder;
    private SiteInterface $site;
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
            $this->site = $request->getAttribute('site');
            if ($this->site instanceof Site) {
                $this->conf = $this->site->getConfiguration();
                $this->variants = $this->conf['baseVariants'] ?? [];
            }
        }
    }

    public function withSite(Site $site): self
    {
        $object = clone $this;
        $object->site = $site;
        $object->conf = $site->getConfiguration();
        $object->variants = $object->conf['baseVariants'] ?? [];

        return $object;
    }

    public function getFrontendUrlForPage(string $url, int $pageUid, string $returnField = 'frontendBase'): string
    {
        if (!$this->features->isFeatureEnabled('headless.frontendUrls')) {
            return $url;
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            $base = $site->getBase()->getHost();
            $port = $site->getBase()->getPort();
            $configuration = $site->getConfiguration();

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

            if (strpos($url, $base) !== false) {
                $url = str_replace($base, $frontBase, $url);
            }

            if ($port === $frontPort) {
                return $url;
            }
            return str_replace(
                $frontBase . ($port ? ':' . $port : ''),
                $frontBase . ($frontPort ? ':' . $frontPort : ''),
                $url
            );
        } catch (SiteNotFoundException $e) {
            $this->logError($e->getMessage());
        }

        return $url;
    }

    public function getFrontendUrl(): string
    {
        return $this->resolveWithVariants('', $this->variants);
    }

    public function getProxyUrl(): string
    {
        return $this->resolveWithVariants('', $this->variants, 'frontendApiProxy');
    }

    public function getStorageProxyUrl(): string
    {
        return $this->resolveWithVariants('', $this->variants, 'frontendFileApi');
    }

    public function resolveKey(string $key): string
    {
        return $this->resolveWithVariants('', $this->variants, $key);
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

    private function resolveWithVariants(
        string $frontendUrl,
        array $variants = [],
        string $returnField = 'frontendBase'
    ): string {
        if (count($variants) === 0) {
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
}
