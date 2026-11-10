<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Event\GeneratePublicUrlForResourceEvent;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use WeakMap;

use function array_map;
use function explode;
use function implode;
use function ltrim;
use function rawurlencode;
use function rtrim;
use function trim;

class ProxyResourcePublicUrlListener
{
    /** @var WeakMap<ServerRequestInterface, array<int, string>> */
    protected WeakMap $baseUriCache;

    public function __construct(
        protected readonly HeadlessModeInterface $headlessMode,
        protected readonly HeadlessFrontendUrlInterface $urlUtility,
    ) {
        $this->baseUriCache = new WeakMap();
    }

    public function __invoke(GeneratePublicUrlForResourceEvent $event): void
    {
        if ($event->getPublicUrl() !== null) {
            return;
        }

        $driver = $event->getDriver();
        if (!$driver instanceof LocalDriver) {
            return;
        }

        if (!$driver->hasCapability(Capabilities::CAPABILITY_PUBLIC)) {
            return;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface
            || !$this->headlessMode->isEnabledFor($request)
            || !ApplicationType::fromRequest($request)->isFrontend()
        ) {
            return;
        }

        $baseUri = $this->buildBaseUri($event->getStorage(), $request);
        if ($baseUri === '') {
            return;
        }

        $event->setPublicUrl(
            rtrim($baseUri, '/') . '/' . $this->encodeIdentifier($event->getResource()->getIdentifier())
        );
    }

    protected function buildBaseUri(ResourceStorage $storage, ServerRequestInterface $request): string
    {
        $cache = $this->baseUriCache[$request] ?? [];
        $uid = $storage->getUid();
        if (!isset($cache[$uid])) {
            $cache[$uid] = $this->resolveBaseUri($storage, $request);
            $this->baseUriCache[$request] = $cache;
        }

        return $cache[$uid];
    }

    protected function resolveBaseUri(ResourceStorage $storage, ServerRequestInterface $request): string
    {
        $urlUtility = $this->urlUtility->withRequest($request);

        $storageUrl = $urlUtility->getStorageProxyUrlForStorage($storage->getUid());
        if ($storageUrl !== '') {
            return $this->onFrontendHost($storageUrl, $urlUtility);
        }

        $config = $storage->getConfiguration();
        $storagePath = match (true) {
            ($config['baseUri'] ?? '') !== '' => (string)$config['baseUri'],
            ($config['basePath'] ?? '') !== '' && ($config['pathType'] ?? '') === 'relative' => (string)$config['basePath'],
            default => '',
        };

        if ($storagePath === '') {
            return $this->onFrontendHost($urlUtility->getStorageProxyUrl(), $urlUtility);
        }

        $storageUri = new Uri($storagePath);
        if ($storageUri->getHost() !== '') {
            return '';
        }

        $proxy = new Uri($urlUtility->getProxyUrl());
        $path = '/' . trim(trim($proxy->getPath(), '/') . '/' . trim($storageUri->getPath(), '/'), '/');

        if ($proxy->getHost() !== '') {
            return (string)$proxy->withPath($path);
        }

        return $this->onFrontendHost($path, $urlUtility);
    }

    protected function onFrontendHost(string $url, HeadlessFrontendUrlInterface $urlUtility): string
    {
        if ($url === '') {
            return '';
        }

        $uri = new Uri($url);
        if ($uri->getHost() !== '') {
            return $url;
        }

        $frontend = new Uri($urlUtility->getFrontendUrl());
        if ($frontend->getHost() === '') {
            return '';
        }

        return (string)$frontend->withPath('/' . trim($uri->getPath(), '/'));
    }

    protected function encodeIdentifier(string $identifier): string
    {
        $parts = explode('/', ltrim($identifier, '/'));

        return implode('/', array_map(rawurlencode(...), $parts));
    }
}
