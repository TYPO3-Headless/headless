<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
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
        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface
            && ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isBackend()) {
            parent::determineBaseUrl();

            return;
        }

        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface &&
            $this->hasCapability(ResourceStorage::CAPABILITY_PUBLIC)) {
            $urlUtility = GeneralUtility::makeInstance(UrlUtility::class);
            $this->configuration['baseUri'] = $urlUtility->getStorageProxyUrl();
        }

        parent::determineBaseUrl();
    }
}
