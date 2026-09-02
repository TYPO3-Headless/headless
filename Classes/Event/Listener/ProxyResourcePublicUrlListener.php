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

use function array_map;
use function explode;
use function implode;
use function ltrim;
use function rawurlencode;
use function rtrim;
use function trim;

class ProxyResourcePublicUrlListener
{
    /** @var array<string, string> */
    protected array $baseUriCache = [];

    public function __construct(
        protected readonly HeadlessModeInterface $headlessMode,
        protected readonly HeadlessFrontendUrlInterface $urlUtility,
    ) {}

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

        $baseUri = $this->buildBaseUri($event->getStorage()->getConfiguration(), $request);
        if ($baseUri === '') {
            return;
        }

        $event->setPublicUrl(
            rtrim($baseUri, '/') . '/' . $this->encodeIdentifier($event->getResource()->getIdentifier())
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function buildBaseUri(array $config, ServerRequestInterface $request): string
    {
        $storagePath = match (true) {
            ($config['baseUri'] ?? '') !== '' => (string)$config['baseUri'],
            ($config['basePath'] ?? '') !== '' && ($config['pathType'] ?? '') === 'relative' => (string)$config['basePath'],
            default => '',
        };

        $cacheKey = spl_object_hash($request) . '|' . $storagePath;
        if (isset($this->baseUriCache[$cacheKey])) {
            return $this->baseUriCache[$cacheKey];
        }

        $urlUtility = $this->urlUtility->withRequest($request);

        if ($storagePath === '') {
            return $this->baseUriCache[$cacheKey] = $urlUtility->getStorageProxyUrl();
        }

        $frontend = new Uri($urlUtility->getFrontendUrl());
        $proxy = new Uri($urlUtility->getProxyUrl());
        $storage = new Uri($storagePath);

        $path = trim($proxy->getPath(), '/') . '/' . trim($storage->getPath(), '/');

        return $this->baseUriCache[$cacheKey] = (string)$frontend->withPath('/' . trim($path, '/'));
    }

    protected function encodeIdentifier(string $identifier): string
    {
        $parts = explode('/', ltrim($identifier, '/'));

        return implode('/', array_map(rawurlencode(...), $parts));
    }
}
