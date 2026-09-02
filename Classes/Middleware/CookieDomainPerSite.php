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
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;

use function strtolower;

class CookieDomainPerSite implements MiddlewareInterface
{
    public function __construct(
        protected readonly HeadlessFrontendUrlInterface $urlUtility,
        protected readonly SiteFinder $siteFinder,
        protected readonly LoggerInterface $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            return $handler->handle($request);
        }
        $requestHost = strtolower($normalizedParams->getHttpHost());

        $cookieDomain = null;
        foreach ($this->siteFinder->getAllSites() as $site) {
            if (empty($site->getConfiguration()['baseVariants'])
                && strtolower($site->getBase()->getHost()) !== $requestHost) {
                continue;
            }

            $urlUtility = $this->urlUtility->withSite($site);
            $base = $urlUtility->resolveKey('base');

            if ($base === '' || strtolower((new Uri($base))->getHost()) !== $requestHost) {
                continue;
            }

            $resolved = $urlUtility->resolveKey('cookieDomain');
            if ($resolved) {
                $cookieDomain = $resolved;
                break;
            }
        }

        if ($cookieDomain === null) {
            if (!($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? '')
                && !($GLOBALS['TYPO3_CONF_VARS']['FE']['cookieDomain'] ?? '')
                && !($GLOBALS['TYPO3_CONF_VARS']['BE']['cookieDomain'] ?? '')
            ) {
                $this->logger->warning('missing cookieDomain configuration');
            }
            return $handler->handle($request);
        }

        $previous = [];
        foreach (['SYS', 'FE', 'BE'] as $scope) {
            $previous[$scope] = $GLOBALS['TYPO3_CONF_VARS'][$scope]['cookieDomain'] ?? null;
        }

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = $cookieDomain;
        foreach (['FE', 'BE'] as $scope) {
            if (($previous[$scope] ?? '') !== '') {
                $GLOBALS['TYPO3_CONF_VARS'][$scope]['cookieDomain'] = $cookieDomain;
            }
        }

        try {
            return $handler->handle($request);
        } finally {
            foreach (['SYS', 'FE', 'BE'] as $scope) {
                if ($previous[$scope] === null) {
                    unset($GLOBALS['TYPO3_CONF_VARS'][$scope]['cookieDomain']);
                } else {
                    $GLOBALS['TYPO3_CONF_VARS'][$scope]['cookieDomain'] = $previous[$scope];
                }
            }
        }
    }
}
