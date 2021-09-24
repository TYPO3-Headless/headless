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

namespace FriendsOfTYPO3\Headless\Service;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemand;
use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class JsonViewConfigurationService implements JsonViewConfigurationServiceInterface
{
    protected array $settings = [];
    protected JsonViewDemandInterface $demand;

    public function __construct()
    {
        $this->settings = $this->getSettings();
    }

    public function getSettings(): array
    {
        if ($this->settings !== []) {
            return $this->settings;
        }

        $yamlFileLoader = GeneralUtility::makeInstance(YamlFileLoader::class);
        $configPath = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('headless', 'yamlPath');

        if (!file_exists(GeneralUtility::getFileAbsFileName($configPath))) {
            $configPath = GeneralUtility::getFileAbsFileName(
                'typo3conf/ext/headless/Configuration/Yaml/HeadlessModule.yml'
            );
        }

        return $yamlFileLoader->load($configPath);
    }

    public function createDemandWithPluginNamespace(string $pluginNamespace): JsonViewDemand
    {
        $demand = new JsonViewDemand($GLOBALS['TYPO3_REQUEST'], $pluginNamespace);
        $this->setDemand($demand);
        return $demand;
    }

    public function getBootContentFlagFromSettings(JsonViewDemandInterface $demand = null): bool
    {
        return (bool)$this->getValueFromConfiguration('bootContent', false, $demand);
    }

    public function getValueFromConfiguration(
        string $settingsKey,
        $defaultValue = null,
        JsonViewDemandInterface $demand = null
    ) {
        $demand = $demand ?? $this->demand;

        if ($demand !== null && isset($this->settings['pageTypeModes'][$demand->getPageTypeMode()][$settingsKey])) {
            return $this->settings['pageTypeModes'][$demand->getPageTypeMode()][$settingsKey];
        }

        return $defaultValue;
    }

    public function getPageArgumentsForDemand(int $pageId = 0, JsonViewDemandInterface $demand = null): PageArguments
    {
        $demand = $demand ?? $this->demand;

        if ($pageId > 0) {
            return new PageArguments(
                $pageId,
                $this->getCurrentPageType($demand),
                $this->getPageTypeArguments($demand)
            );
        }

        return new PageArguments(
            $demand->getPageId(),
            $this->getCurrentPageType($demand),
            $this->getPageTypeArguments($demand)
        );
    }

    public function getCurrentPageType(JsonViewDemandInterface $demand = null): string
    {
        return (string)$this->getValueFromConfiguration('pageType', '0', $demand);
    }

    public function getPageTypeArguments(JsonViewDemandInterface $demand = null): array
    {
        if ($GLOBALS['BE_USER']->isAdmin()) {
            return $this->getValueFromConfiguration('arguments', [], $demand);
        }

        return [];
    }

    public function getDefaultModuleTranslationFile(): string
    {
        return 'LLL:EXT:headless/Resources/Private/Language/locallang_be.xlf:';
    }

    public function getContentTabName(): string
    {
        return 'content';
    }

    public function getRawTabName(): string
    {
        return 'raw';
    }

    public function getDisallowedDoktypes(): array
    {
        return [
            PageRepository::DOKTYPE_LINK,
            PageRepository::DOKTYPE_SHORTCUT,
            PageRepository::DOKTYPE_MOUNTPOINT,
            PageRepository::DOKTYPE_SPACER,
            PageRepository::DOKTYPE_SYSFOLDER,
            PageRepository::DOKTYPE_RECYCLER,
        ];
    }

    public function getElementTitle(array $data): string
    {
        return $data['header'] ?: $data['title'] ?: '';
    }

    public function getSectionId(array $data): string
    {
        return 'section-' . $data['uid'];
    }

    public function getPageDataFromApi(array $jsonArray = []): array
    {
        return $jsonArray['page'] ?? [];
    }

    public function getDemand(): JsonViewDemandInterface
    {
        return $this->demand;
    }

    public function setDemand(JsonViewDemandInterface $demand): void
    {
        $this->demand = $demand;
    }
}
