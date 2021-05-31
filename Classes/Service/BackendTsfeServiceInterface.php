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

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDtoInterface;

interface BackendTsfeServiceInterface
{
    /**
     * @param int $pageId
     * @param JsonViewDemandDtoInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @param bool $bootContent
     */
    public function bootFrontendControllerForPage(int $pageId, JsonViewDemandDtoInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings, bool $bootContent = false): void;

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @return string
     */
    public function getPageFromTsfe(JsonViewDemandDtoInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings): string;
}
