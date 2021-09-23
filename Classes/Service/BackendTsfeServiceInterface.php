<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

namespace FriendsOfTYPO3\Headless\Service;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;

interface BackendTsfeServiceInterface
{
    /**
     * @param int $pageId
     * @param JsonViewDemandInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @param bool $bootContent
     */
    public function bootFrontendControllerForPage(int $pageId, JsonViewDemandInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings, bool $bootContent = false): void;

    /**
     * @param JsonViewDemandInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @return string
     */
    public function getPageFromTsfe(JsonViewDemandInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings): string;
}
