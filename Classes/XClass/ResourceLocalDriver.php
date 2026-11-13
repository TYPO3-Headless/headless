<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
class ResourceLocalDriver extends LocalDriver
{
    protected function determineBaseUrl(): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if (!$request instanceof ServerRequestInterface) {
            parent::determineBaseUrl();
            return;
        }

        $headlessMode = GeneralUtility::makeInstance(HeadlessModeInterface::class)->withRequest($request);

        if (!$headlessMode->isEnabled() || ApplicationType::fromRequest($request)->isBackend()) {
            parent::determineBaseUrl();

            return;
        }

        if ($this->hasCapability(((new Typo3Version())->getMajorVersion() < 13) ? ResourceStorage::CAPABILITY_PUBLIC : \TYPO3\CMS\Core\Resource\Capabilities::CAPABILITY_PUBLIC)) {
            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class, null, null, null, $request);
            $baseUri = $this->resolveProxyBaseUri($urlUtility);
            if ($baseUri !== null) {
                $this->configuration['baseUri'] = $baseUri;
            }
        }

        parent::determineBaseUrl();
    }

    protected function resolveProxyBaseUri(UrlUtility $urlUtility): ?string
    {
        $storageUrl = $urlUtility->getStorageProxyUrlForStorage((int)$this->storageUid);
        if ($storageUrl !== '') {
            return $this->onFrontendHost($storageUrl, $urlUtility);
        }

        $basePath = match (true) {
            (($this->configuration['baseUri'] ?? '') !== '') => (string)$this->configuration['baseUri'],
            (($this->configuration['basePath'] ?? '') !== '' && ($this->configuration['pathType'] ?? '') === 'relative') => (string)$this->configuration['basePath'],
            default => '',
        };

        if ($basePath === '') {
            $fileApi = $urlUtility->getStorageProxyUrl();
            return $this->onFrontendHost($fileApi, $urlUtility) ?? $fileApi;
        }

        $baseUri = new Uri($basePath);
        if ($baseUri->getHost() !== '') {
            return null;
        }

        $frontendUri = new Uri($urlUtility->getFrontendUrl());
        $proxyUri = new Uri($urlUtility->getProxyUrl());
        $path = trim($proxyUri->getPath(), '/') . '/' . trim($baseUri->getPath(), '/');

        return (string)$frontendUri->withPath('/' . trim($path, '/'));
    }

    protected function onFrontendHost(string $url, UrlUtility $urlUtility): ?string
    {
        if ($url === '') {
            return '';
        }

        $uri = new Uri($url);
        if ($uri->getHost() !== '') {
            return $url;
        }

        $frontendUri = new Uri($urlUtility->getFrontendUrl());
        if ($frontendUri->getHost() === '') {
            return null;
        }

        return (string)$frontendUri->withPath('/' . trim($uri->getPath(), '/'));
    }
}
