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
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly SiteFinder $siteFinder,
        private readonly LoggerInterface $logger,
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
            if (!($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? '')) {
                $this->logger->warning('missing cookieDomain configuration');
            }
            return $handler->handle($request);
        }

        $previous = $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = $cookieDomain;
        try {
            return $handler->handle($request);
        } finally {
            if ($previous === null) {
                unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain']);
            } else {
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = $previous;
            }
        }
    }
}
