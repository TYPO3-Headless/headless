<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Event\RedirectUrlEvent;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Redirects\Event\RedirectWasHitEvent;

class HeadlessRedirectResponseListener
{
    public function __construct(
        private readonly HeadlessModeInterface $headlessMode,
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(RedirectWasHitEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || !$this->headlessMode->isEnabledFor($request)) {
            return;
        }

        $matchedRedirect = $event->getMatchedRedirect();
        $uri = $event->getTargetUrl();

        $urlUtility = $this->urlUtility->withRequest($request);
        $targetUrl = $urlUtility->prepareRelativeUrlIfPossible(
            $urlUtility->getFrontendUrlWithSite((string)$uri, $site)
        );

        $urlEvent = $this->eventDispatcher->dispatch(new RedirectUrlEvent(
            $request,
            $uri,
            $targetUrl,
            (int)($matchedRedirect['target_statuscode'] ?? 0),
            $matchedRedirect
        ));

        $event->setResponse(new JsonResponse([
            'redirectUrl' => $urlEvent->getTargetUrl(),
            'statusCode' => $urlEvent->getTargetStatusCode(),
        ]));
    }
}
