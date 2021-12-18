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
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Redirects\Service\RedirectService;

use function strpos;

/**
 * @codeCoverageIgnore
 */
final class RedirectHandler extends \TYPO3\CMS\Redirects\Http\Middleware\RedirectHandler
{
    private LinkService $linkService;
    private EventDispatcherInterface $eventDispatcher;
    private ServerRequestInterface $request;
    private Features $features;
    private HeadlessFrontendUrlInterface $urlUtility;

    public function __construct(
        RedirectService $redirectService,
        HeadlessFrontendUrlInterface $urlUtility,
        LinkService $linkService,
        EventDispatcher $eventDispatcher,
        Features $features
    ) {
        parent::__construct($redirectService);
        $this->urlUtility = $urlUtility;
        $this->linkService = $linkService;
        $this->features = $features;
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

        $frontendDomainTrim = true;

        if ($redirectRecord['target'] === '/') {
            $resolvedTarget = ['type' => LinkService::TYPE_UNKNOWN, 'file' => '/'];
        } else {
            $resolvedTarget = $this->linkService->resolve($redirectRecord['target']);
        }

        if ($resolvedTarget['type'] === LinkService::TYPE_FILE || $resolvedTarget['type'] === LinkService::TYPE_FOLDER) {
            $targetUrl = $this->handleFileTypes($resolvedTarget);
        } elseif ($resolvedTarget['type'] === LinkService::TYPE_UNKNOWN && strpos($resolvedTarget['file'], '/') === 0) {
            $frontendDomainTrim = false;
            $targetUrl = $resolvedTarget['file'];
        } else {
            $targetUrl = $this->urlUtility->getFrontendUrlForPage((string)$uri, (int)$resolvedTarget['pageuid']);
        }

        if ($frontendDomainTrim) {
            $targetUrl = $this->urlUtility->prepareRelativeUrlIfPossible($targetUrl);
        }

        $redirectUrlEvent = new RedirectUrlEvent(
            $this->request,
            $uri,
            $targetUrl,
            (int)$redirectRecord['target_statuscode'],
            $redirectRecord
        );

        $redirectUrlEvent = $this->eventDispatcher->dispatch($redirectUrlEvent);

        return new JsonResponse([
            'redirectUrl' => $redirectUrlEvent->getTargetUrl(),
            'statusCode' => $redirectUrlEvent->getTargetStatusCode()
        ]);
    }

    /**
     * @param array<string,mixed> $resolvedTarget
     */
    private function handleFileTypes(array $resolvedTarget): string
    {
        $port = $this->request->getUri()->getPort();
        $baseFileUrl = $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getHost() . ($port ? ':' . $port : '');

        if ($this->features->isFeatureEnabled('headless.storageProxy')) {
            // we have to get frontendApiProxy, because getPublicUrl() returns storage folder already
            $baseFileUrl = $this->urlUtility->getProxyUrl();
        }

        return $baseFileUrl . '/' . $resolvedTarget[$resolvedTarget['type']]->getPublicUrl();
    }
}
