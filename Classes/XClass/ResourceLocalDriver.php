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
 * @codeCoverageIgnore
 */
class ResourceLocalDriver extends LocalDriver
{
    /**
     * Lazy-loaded dependencies. This XClass is registered via
     * $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'] in ext_localconf.php. TYPO3 instantiates
     * such classes through GeneralUtility::makeInstanceForDi() which bypasses Symfony's
     * service compilation, so neither constructor injection nor #[Required] setter injection
     * is honored for SYS][Objects] XClasses. We resolve via container manually on first use.
     */
    private ?HeadlessModeInterface $headlessMode = null;
    private ?UrlUtility $urlUtility = null;

    private function getHeadlessMode(): HeadlessModeInterface
    {
        return $this->headlessMode ??= GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    private function getUrlUtility(): UrlUtility
    {
        return $this->urlUtility ??= GeneralUtility::makeInstance(UrlUtility::class);
    }

    protected function determineBaseUrl(): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if (!$request instanceof ServerRequestInterface) {
            parent::determineBaseUrl();
            return;
        }

        $headlessMode = $this->getHeadlessMode()->withRequest($request);

        if (!$headlessMode->isEnabled() || ApplicationType::fromRequest($request)->isBackend()) {
            parent::determineBaseUrl();

            return;
        }

        if ($this->hasCapability(Capabilities::CAPABILITY_PUBLIC)) {
            $urlUtility = $this->getUrlUtility()->withRequest($request);

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
                $this->configuration['baseUri'] = $urlUtility->getStorageProxyUrl();
            }
        }

        parent::determineBaseUrl();
    }
}
