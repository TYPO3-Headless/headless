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
use TYPO3\CMS\Core\Routing\PageArguments;

interface JsonViewConfigurationServiceInterface
{
    public function getSettings(): array;
    public function getDefaultModuleTranslationFile(): string;
    public function getDisallowedDoktypes(): array;
    public function createDemandWithPluginNamespace(string $pluginNamespace);
    public function getBootContentFlagFromSettings(JsonViewDemandInterface $demand = null): bool;
    public function getCurrentPageType(JsonViewDemandInterface $demand = null): string;
    public function getPageTypeArguments(JsonViewDemandInterface $demand = null): array;
    public function getPageArgumentsForDemand(int $pageId = 0, JsonViewDemandInterface $demand = null): PageArguments;
}
