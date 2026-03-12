<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Seo\MetaHandler;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Http\Stream;

use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function json_decode;

class UserIntMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HeadlessUserInt $headlessUserInt,
        private readonly HeadlessModeInterface $headlessMode,
        private readonly MetaHandler $metaHandler,
        private readonly Features $features,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->headlessMode->withRequest($request)->isEnabled()) {
            return $response;
        }

        $jsonContent = $response->getBody()->__toString();

        if (!$this->headlessUserInt->hasNonCacheableContent($jsonContent)) {
            return $response;
        }

        $jsonContent = $this->headlessUserInt->unwrap($jsonContent);
        $responseBody = json_decode($jsonContent, true);

        if ($this->features->isFeatureEnabled('headless.pageTitleProviders')) {
            $this->modifyPageTitle($request, $responseBody);
        }

        if (($responseBody['seo']['title'] ?? null) !== null) {
            $responseBody = $this->metaHandler->process(
                $request,
                $request->getAttribute('frontend.controller'),
                $responseBody
            );
            $jsonContent = json_encode($responseBody);
        }

        $stream = new Stream('php://temp', 'r+');
        $stream->write($jsonContent);
        return $response->withBody($stream);
    }

    private function modifyPageTitle(ServerRequestInterface $request, mixed &$responseBody): void
    {
        if (($responseBody['meta']['title'] ?? null) === null) return;

        $titleProviderManager = GeneralUtility::makeInstance(PageTitleProviderManager::class);

        $title = $titleProviderManager->getTitle($request);

        $responseBody['meta']['title'] = $title;
    }
}
