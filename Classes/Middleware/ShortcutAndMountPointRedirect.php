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
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\LinkHandling\PageTypeLinkResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * @codeCoverageIgnore
 */
class ShortcutAndMountPointRedirect extends \TYPO3\CMS\Frontend\Middleware\ShortcutAndMountPointRedirect
{
    public function __construct(
        private readonly HeadlessModeInterface $headlessMode,
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        PageTypeLinkResolver $pageTypeLinkResolver,
    ) {
        parent::__construct($pageTypeLinkResolver);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $pageType = (int)($queryParams['type'] ?? 0);

        if ($pageType === 834) {
            return $handler->handle($request);
        }

        $coreResponse = parent::process($request, $handler);

        if ($coreResponse instanceof RedirectResponse && $this->isHeadlessEnabled($request)) {
            $urlUtility = $this->urlUtility->withRequest($request);
            $location = $coreResponse->getHeader('location')[0] ?? '';
            $site = $request->getAttribute('site');

            if ($site instanceof Site) {
                $location = $urlUtility->getFrontendUrlWithSite($location, $site);
            }

            return new JsonResponse([
                'redirectUrl' => $urlUtility->prepareRelativeUrlIfPossible($location),
                'statusCode' => $coreResponse->getStatusCode(),
            ]);
        }

        return $coreResponse;
    }

    private function isHeadlessEnabled(ServerRequestInterface $request): bool
    {
        return $this->headlessMode->isEnabledFor($request);
    }
}
