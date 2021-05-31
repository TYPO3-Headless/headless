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

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDto;
use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDtoInterface;
use TYPO3\CMS\Core\Routing\PageArguments;

interface JsonViewConfigurationServiceInterface
{
    /**
     * @param string $pluginNamespace
     * @return JsonViewDemandDto
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    public function getDemandWithPluginNamespace(string $pluginNamespace);

    /**
     * @return array
     */
    public function getSettings(): array;

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param array $settings
     * @return bool
     */
    public function getBootContentFlagFromSettings(JsonViewDemandDtoInterface $demand, array $settings): bool;

    /**
     * @param array $settings
     * @param JsonViewDemandDtoInterface $demand
     * @return string
     */
    public function getCurrentPageType(array $settings, JsonViewDemandDtoInterface $demand): string;

    /**
     * @param array $settings
     * @param JsonViewDemandDtoInterface $demand
     * @return array
     */
    public function getPageTypeArguments(array $settings, JsonViewDemandDtoInterface $demand): array;

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param array $settings
     * @param int $pageId
     * @return PageArguments
     */
    public function getPageArgumentsForDemand(JsonViewDemandDtoInterface $demand, array $settings, int $pageId = 0);

    /**
     * @return string
     */
    public function getDefaultModuleTranslationFile(): string;

    /**
     * @return string
     */
    public function getContentTabName(): string;

    /**
     * @return string
     */
    public function getRawTabName(): string;

    /**
     * @return int[]
     */
    public function getDisallowedDoktypes(): array;

    /**
     * @param array $data
     * @return string
     */
    public function getElementTitle(array $data): string;

    /**
     * @param array $data
     * @return string
     */
    public function getSectionId(array $data): string;
}
