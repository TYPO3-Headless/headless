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
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * XClasses LocalDriver to resolve the storage's baseUri through the headless
 * frontendFileApi configuration, so file URLs in JSON output point at the
 * frontend delivery endpoint instead of the backend host.
 *
 * Supports per-storage `frontendFileApi` configuration (keyed by storage UID)
 * via the optional `$storageUid` argument of `UrlUtility::getStorageProxyUrl()`.
 * When a per-storage URL is configured, it takes precedence over URL composition
 * from `frontendBase + basePath`.
 *
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

        if ($this->hasCapability(Capabilities::CAPABILITY_PUBLIC)) {
            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class)->withRequest($request);
            $storageUid = (int)$this->storageUid;

            if ($urlUtility->hasStorageSpecificFrontendFileApi($storageUid)) {
                // Per-storage frontendFileApi configured (array form):
                // use the URL directly as the storage's baseUri.
                $this->configuration['baseUri'] = $urlUtility->getStorageProxyUrl($storageUid);
            } else {
                // Fall back to URL composition (frontendBase + storage basePath)
                // or single-string frontendFileApi.
                $basePath = match (true) {
                    (($this->configuration['baseUri'] ?? '') !== '') => $this->configuration['baseUri'],
                    (($this->configuration['basePath'] ?? '') !== '' && $this->configuration['pathType'] === 'relative') => $this->configuration['basePath'],
                    default => '',
                };

                if ($basePath !== '') {
                    $frontendUri = new Uri($urlUtility->getFrontendUrl());
                    $proxyUri = new Uri($urlUtility->getProxyUrl());
                    $baseUri = new Uri($basePath);

                    $path = trim($proxyUri->getPath(), '/') . '/' . trim($baseUri->getPath(), '/');
                    $this->configuration['baseUri'] = (string)$frontendUri->withPath('/' . trim($path, '/'));
                } else {
                    $this->configuration['baseUri'] = $urlUtility->getStorageProxyUrl($storageUid);
                }
            }
        }

        parent::determineBaseUrl();
    }
}
