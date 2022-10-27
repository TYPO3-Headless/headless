<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Redirects\Service\RedirectService;

/**
 * @codeCoverageIgnore
 */
final class RedirectHandler extends \TYPO3\CMS\Redirects\Http\Middleware\RedirectHandler
{
    private EventDispatcherInterface $eventDispatcher;
    private ServerRequestInterface $request;
    private HeadlessFrontendUrlInterface $urlUtility;

    public function __construct(
        RedirectService $redirectService,
        HeadlessFrontendUrlInterface $urlUtility,
        EventDispatcher $eventDispatcher
    ) {
        parent::__construct($redirectService);
        $this->urlUtility = $urlUtility;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request = $request;
        return parent::process($request, $handler);
    }

    /**
     * @inheritDoc
     */
    protected function buildRedirectResponse(UriInterface $uri, array $redirectRecord): ResponseInterface
    {
        /**
         * @var Site
         */
        $site = $this->request->getAttribute('site');

        if (!($site instanceof Site)) {
            return parent::buildRedirectResponse($uri, $redirectRecord);
        }

        $siteConf = $this->request->getAttribute('site')->getConfiguration();

        if (!($siteConf['headless'] ?? false)) {
            return parent::buildRedirectResponse($uri, $redirectRecord);
        }

        $this->urlUtility = $this->urlUtility->withSite($site);

        $redirectUrlEvent = new RedirectUrlEvent(
            $this->request,
            $uri,
            $this->urlUtility->prepareRelativeUrlIfPossible((string)$uri),
            (int)$redirectRecord['target_statuscode'],
            $redirectRecord
        );

        $redirectUrlEvent = $this->eventDispatcher->dispatch($redirectUrlEvent);

        return new JsonResponse([
            'redirectUrl' => $redirectUrlEvent->getTargetUrl(),
            'statusCode' => $redirectUrlEvent->getTargetStatusCode()
        ]);
    }
}
