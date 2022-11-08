<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
class CookieDomainPerSite implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var SiteFinder
     */
    private $siteFinder;
    /**
     * @var FrontendBaseUtility
     */
    private $frontendBaseUtility;

    public function __construct(
        FrontendBaseUtility $frontendBaseUtility = null,
        SiteFinder $siteFinder = null
    ) {
        $this->frontendBaseUtility = $frontendBaseUtility ?? GeneralUtility::makeInstance(FrontendBaseUtility::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $requestHost = $normalizedParams->getHttpHost();
        $allSites = $this->siteFinder->getAllSites();

        foreach ($allSites as $site) {
            $base = $this->frontendBaseUtility->resolveWithVariants($site->getConfiguration()['base'], $site->getConfiguration()['baseVariants'] ?? [], 'base');
            $cookieDomain = $this->frontendBaseUtility->resolveWithVariants($site->getConfiguration()['cookieDomain'] ?? '', $site->getConfiguration()['baseVariants'] ?? [], 'cookieDomain');

            if (str_contains($base, $requestHost) && $cookieDomain) {
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = $cookieDomain;
                break;
            }
        }

        if (!$GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain']) {
            $this->logger->warning('missing cookieDomain configuration');
        }

        return $handler->handle($request);
    }
}
