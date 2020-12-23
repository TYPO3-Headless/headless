<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

use function array_map;
use function explode;
use function implode;
use function rtrim;

use const TYPO3_MODE;

class ResourceLocalDriver extends \TYPO3\CMS\Core\Resource\Driver\LocalDriver
{
    protected function determineBaseUrl(): void
    {
        if (TYPO3_MODE === 'BE') {
            parent::determineBaseUrl();

            return;
        }

        if ($this->hasCapability(ResourceStorage::CAPABILITY_PUBLIC)) {
            $conf = $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getConfiguration();
            $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);

            $baseUri = $frontendBase->resolveWithVariants(
                $conf['frontendFileApi'] ?? '',
                $conf['baseVariants'] ?? null,
                'frontendFileApi'
            );

            if (!empty($baseUri)) {
                $this->baseUri = rtrim($baseUri, '/') . '/';
            } elseif (GeneralUtility::isFirstPartOfStr($this->absoluteBasePath, Environment::getPublicPath())) {
                // use site-relative URLs
                $temporaryBaseUri = rtrim(PathUtility::stripPathSitePrefix($this->absoluteBasePath), '/');
                if ($temporaryBaseUri !== '') {
                    $uriParts = explode('/', $temporaryBaseUri);
                    $uriParts = array_map('rawurlencode', $uriParts);
                    $temporaryBaseUri = implode('/', $uriParts) . '/';
                }
                $this->baseUri = $temporaryBaseUri;
            }
        }
    }
}
