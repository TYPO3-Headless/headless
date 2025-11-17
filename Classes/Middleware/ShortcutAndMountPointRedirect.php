<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
class ShortcutAndMountPointRedirect extends \TYPO3\CMS\Frontend\Middleware\ShortcutAndMountPointRedirect
{
    public function __construct(private readonly HeadlessModeInterface $headlessMode) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $pageType = (int)($queryParams['type'] ?? 0);

        if ($pageType === 834) {
            return $handler->handle($request);
        }

        $coreResponse = parent::process($request, $handler);

        if ($coreResponse instanceof RedirectResponse && $this->isHeadlessEnabled($request)) {
            $site = $request->getAttribute('site', new NullSite());

            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class)->withRequest($request);
            return new JsonResponse([
                'redirectUrl' => $urlUtility->prepareRelativeUrlIfPossible(
                    ($site instanceof NullSite ?
                        $coreResponse->getHeader('location')[0] ?? '' :
                        $urlUtility->getFrontendUrlWithSite(
                            $coreResponse->getHeader('location')[0] ?? '',
                            $site
                        )
                    ),
                ),
                'statusCode' => $coreResponse->getStatusCode(),
            ]);
        }

        return $coreResponse;
    }

    private function isHeadlessEnabled(ServerRequestInterface $request): bool
    {
        return $this->headlessMode->withRequest($request)->isEnabled();
    }
}
