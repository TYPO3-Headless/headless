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
use FriendsOfTYPO3\Headless\Service\SiteService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Redirects\Service\RedirectService;

use function strpos;

final class RedirectHandler extends \TYPO3\CMS\Redirects\Http\Middleware\RedirectHandler
{
    /**
     * @var SiteService
     */
    private $siteService;
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
        EventDispatcher $eventDispatcher = null
    ) {
        parent::__construct($redirectService);
        $this->siteService = $siteService ?? GeneralUtility::makeInstance(SiteService::class);
        if ((new Typo3Version())->getMajorVersion() >= 10) {
            $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcher::class);
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

        $requestDomainUrl = $this->siteService->getFrontendUrl((string)$this->request->getUri(), $site->getRootPageId());

        if ($uri->getHost() === '') {
            $uri = $uri->withHost((new Uri($requestDomainUrl))->getHost());
        }

        if (substr($uri->getPath(), 0, 3) === '%7B') {
            $path = rawurldecode($uri->getPath());
            $path = json_decode($path, true);
            $uri = $uri->withPath($path['url']);
        }

        $redirectUrlEvent = new RedirectUrlEvent(
            $this->request,
            $uri,
            $this->prepareRelativeUrlIfPossible((string)$uri, $requestDomainUrl),
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

    public function prepareRelativeUrlIfPossible(string $targetUrl, $requestDomainUrl): string
    {
        $parsedTargetUrl = new Uri($this->sanitizeBaseUrl($targetUrl));
        $parsedProjectFrontendUrl = new Uri($this->sanitizeBaseUrl($requestDomainUrl));

        if ($parsedTargetUrl->getHost() === $parsedProjectFrontendUrl->getHost()) {
            return $parsedTargetUrl->getPath() . ($parsedTargetUrl->getQuery() ? '?' . $parsedTargetUrl->getQuery() : '');
        }

        return $targetUrl;
    }

    /**
     * If a site base contains "/" or "www.domain.com", it is ensured that
     * parse_url() can handle this kind of configuration properly.
     */
    private function sanitizeBaseUrl(string $base): string
    {
        // no protocol ("//") and the first part is no "/" (path), means that this is a domain like
        // "www.domain.com/blabla", and we want to ensure that this one then gets a "no-scheme agnostic" part
        if (!empty($base) && strpos($base, '//') === false && $base[0] !== '/') {
            // either a scheme is added, or no scheme but with domain, or a path which is not absolute
            // make the base prefixed with a slash, so it is recognized as path, not as domain
            // treat as path
            if (strpos($base, '.') === false) {
                $base = '/' . $base;
            } else {
                // treat as domain name
                $base = '//' . $base;
            }
        }
        return $base;
    }
}
