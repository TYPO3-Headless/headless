<?php

declare(strict_types = 1);

namespace FriendsOfTYPO3\Headless\Service;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * Settings service
 */
class SettingsService
{
    /**
     * @return string
     */
    public function provideSettings(): string
    {
        $settings = [];
        try {
            $settings['manifest'] = $this->getExtensionConfiguration()->get('headless', 'manifest');
        } catch (ExtensionConfigurationExtensionNotConfiguredException $e) {
        } catch (ExtensionConfigurationPathDoesNotExistException $e) {
        }

        return json_encode($settings);
    }

    /**
     * @return ExtensionConfiguration
     */
    protected function getExtensionConfiguration(): ExtensionConfiguration
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }
}
