<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Site\SiteFinder;

class CookieDomainPerSite implements MiddlewareInterface
{
    private HeadlessFrontendUrlInterface $urlUtility;
    private SiteFinder $siteFinder;
    private LoggerInterface $logger;

    public function __construct(
        HeadlessFrontendUrlInterface $urlUtility,
        SiteFinder $siteFinder,
        LoggerInterface $logger
    ) {
        $this->urlUtility = $urlUtility;
        $this->siteFinder = $siteFinder;
        $this->logger = $logger;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $normalizedParams = $request->getAttribute('normalizedParams');

        if (!$normalizedParams instanceof NormalizedParams) {
            return $handler->handle($request);
        }

        $requestHost = strtolower($normalizedParams->getHttpHost());
        $allSites = $this->siteFinder->getAllSites();

        foreach ($allSites as $site) {
            $urlUtility = $this->urlUtility->withSite($site);
            $base = $urlUtility->resolveKey('base');
            $cookieDomain = $urlUtility->resolveKey('cookieDomain');

            if ($cookieDomain && $this->baseMatchesHost($base, $requestHost)) {
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = $cookieDomain;
                break;
            }
        }

        if (!($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? '')) {
            $this->logger->warning('missing cookieDomain configuration');
        }

        return $handler->handle($request);
    }

    private function baseMatchesHost(string $base, string $requestHost): bool
    {
        if ($requestHost === '' || $base === '') {
            return false;
        }

        if (!str_contains($base, '//')) {
            $base = '//' . $base;
        }

        $parts = parse_url($base);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if ($host === '') {
            return false;
        }

        $authority = $host . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return strtolower($authority) === $requestHost;
    }
}
