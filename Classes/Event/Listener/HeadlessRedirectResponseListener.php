<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Redirects\TargetUrlResolver;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Redirects\Event\RedirectWasHitEvent;

readonly class HeadlessRedirectResponseListener
{
    public function __construct(
        protected HeadlessModeInterface        $headlessMode,
        protected HeadlessFrontendUrlInterface $urlUtility,
        protected TargetUrlResolver            $targetUrlResolver,
    ) {}

    public function __invoke(RedirectWasHitEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || !$this->headlessMode->isEnabledFor($request)) {
            return;
        }

        $matchedRedirect = $event->getMatchedRedirect();
        $uri = $this->targetUrlResolver->resolve($matchedRedirect, $event->getTargetUrl(), $request)
            ?? $event->getTargetUrl();

        $urlUtility = $this->urlUtility->withRequest($request);
        $targetUrl = $urlUtility->prepareRelativeUrlIfPossible(
            $urlUtility->getFrontendUrlWithSite((string)$uri, $site)
        );

        $event->setResponse(new JsonResponse([
            'redirectUrl' => $targetUrl,
            'statusCode' => (int)($matchedRedirect['target_statuscode'] ?? 0),
        ]));
    }
}
