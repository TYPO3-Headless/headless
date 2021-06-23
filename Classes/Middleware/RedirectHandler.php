<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Middleware;

use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use FriendsOfTYPO3\Headless\Service\SiteService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectService;

final class RedirectHandler extends \TYPO3\CMS\Redirects\Http\Middleware\RedirectHandler
{
    /**
     * @var SiteService
     */
    private $siteService;
    /**
     * @var LinkService
     */
    private $linkService;
    /**
     * @var EventDispatcherInterface|null
     */
    private $eventDispatcher;
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(
        RedirectService $redirectService,
        SiteService $siteService = null,
        LinkService $linkService = null,
        EventDispatcher $eventDispatcher = null
    ) {
        parent::__construct($redirectService);
        $this->siteService = $siteService ?? GeneralUtility::makeInstance(SiteService::class);
        $this->linkService = $linkService ?? GeneralUtility::makeInstance(LinkService::class);

        if ((new Typo3Version())->getMajorVersion() >= 10) {
            $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
        }
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
        $resolvedTarget = $this->linkService->resolve($redirectRecord['target']);
        $targetUrl = $this->siteService->getFrontendUrl((string)$uri, (int)$resolvedTarget['pageuid']);

        $redirectUrlEvent = new RedirectUrlEvent(
            $this->request,
            $uri,
            $targetUrl,
            (int)$redirectRecord['target_statuscode'],
            $redirectRecord
        );

        if ($this->eventDispatcher) {
            $redirectUrlEvent = $this->eventDispatcher->dispatch($redirectUrlEvent);
        } else {
            $redirectUrlEvent = $this->dispatchHooks($redirectUrlEvent);
        }

        return new JsonResponse([
            'redirectUrl' => $redirectUrlEvent->getTargetUrl(),
            'statusCode' => $redirectUrlEvent->getTargetStatusCode()
        ]);
    }

    private function dispatchHooks(RedirectUrlEvent $redirectUrlEvent): RedirectUrlEvent
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['headless']['hooks']['redirectUrl'] ?? [] as $hook) {
            $_params = [
                'pObj' => &$this,
                'redirectUrlEvent' => $redirectUrlEvent,
            ];

            $parsedEventByHooks = GeneralUtility::callUserFunction($hook, $_params, $this);

            if ($parsedEventByHooks instanceof RedirectUrlEvent) {
                $redirectUrlEvent = $parsedEventByHooks;
            }
        }

        return $redirectUrlEvent;
    }
}
