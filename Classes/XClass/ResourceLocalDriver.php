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

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use const TYPO3_MODE;

class ResourceLocalDriver extends \TYPO3\CMS\Core\Resource\Driver\LocalDriver
{
    protected function determineBaseUrl(): void
    {
        if (TYPO3_MODE === 'BE') {
            parent::determineBaseUrl();

            return;
        }

        if (($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface &&
            $this->hasCapability(ResourceStorage::CAPABILITY_PUBLIC)) {
            $conf = $GLOBALS['TYPO3_REQUEST']->getAttribute('site')->getConfiguration();
            $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);
            $this->configuration['baseUri'] = $frontendBase->resolveWithVariants(
                $conf['frontendFileApi'] ?? '',
                $conf['baseVariants'] ?? null,
                'frontendFileApi'
            );
        }

        parent::determineBaseUrl();
    }
}
