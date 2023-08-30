<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SiteBaseRedirectResolver extends \TYPO3\CMS\Frontend\Middleware\SiteBaseRedirectResolver
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = parent::process($request, $handler);

        $site = $request->getAttribute('site');
        $siteConf = $site->getConfiguration();

        if (!($siteConf['headless'] ?? false)) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class)->withRequest($request);
            return new JsonResponse([
                'redirectUrl' => $urlUtility->prepareRelativeUrlIfPossible(
                    $urlUtility->getFrontendUrlWithSite(
                        $response->getHeader('location')[0] ?? '',
                        $site
                    )
                ),
                'statusCode' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
