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

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDto;
use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDtoInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class JsonViewConfigurationService implements JsonViewConfigurationServiceInterface
{
    /**
     * @param string $pluginNamespace
     * @return JsonViewDemandDto
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    public function getDemandWithPluginNamespace(string $pluginNamespace)
    {
        return new JsonViewDemandDto($GLOBALS['TYPO3_REQUEST'], $pluginNamespace);
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        $yamlFileLoader = GeneralUtility::makeInstance(YamlFileLoader::class);
        $configPath = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('headless', 'yamlPath');

        if (!file_exists(GeneralUtility::getFileAbsFileName($configPath))) {
            $configPath = GeneralUtility::getFileAbsFileName('typo3conf/ext/headless/Configuration/Yaml/HeadlessModule.yml');
        }

        return $yamlFileLoader->load($configPath);
    }

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param array $settings
     * @return bool
     */
    public function getBootContentFlagFromSettings(JsonViewDemandDtoInterface $demand, array $settings): bool
    {
        return (bool)$settings['pageTypeModes'][$demand->getPageTypeMode()]['bootContent'];
    }

    /**
     * @param array $settings
     * @param JsonViewDemandDtoInterface $demand
     * @return string
     */
    public function getCurrentPageType(array $settings, JsonViewDemandDtoInterface $demand): string
    {
        return $settings['pageTypeModes'][$demand->getPageTypeMode()]['pageType'] ?: '0';
    }

    /**
     * @param array $settings
     * @param JsonViewDemandDtoInterface $demand
     * @return array
     */
    public function getPageTypeArguments(array $settings, JsonViewDemandDtoInterface $demand): array
    {
        if ($GLOBALS['BE_USER']->isAdmin()) {
            return $settings['pageTypeModes'][$demand->getPageTypeMode()]['arguments'] ?: [];
        }

        return [];
    }

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param array $settings
     * @param int $pageId
     * @return PageArguments
     */
    public function getPageArgumentsForDemand(JsonViewDemandDtoInterface $demand, array $settings, int $pageId = 0)
    {
        if ($pageId > 0) {
            return new PageArguments($pageId, $this->getCurrentPageType($settings, $demand), $this->getPageTypeArguments($settings, $demand));
        }

        return new PageArguments($demand->getPageId(), $this->getCurrentPageType($settings, $demand), $this->getPageTypeArguments($settings, $demand));
    }

    /**
     * @return string
     */
    public function getDefaultModuleTranslationFile(): string
    {
        return 'LLL:EXT:headless/Resources/Private/Language/locallang_be.xlf:';
    }

    /**
     * @return string
     */
    public function getContentTabName(): string
    {
        return 'content';
    }

    /**
     * @return string
     */
    public function getRawTabName(): string
    {
        return 'raw';
    }

    /**
     * @return int[]
     */
    public function getDisallowedDoktypes(): array
    {
        return [3, 4, 7, 199, 254, 255];
    }

    /**
     * @param array $data
     * @return string
     */
    public function getElementTitle(array $data): string
    {
        return $data['header'] ?: $data['title'] ?: '';
    }

    /**
     * @param array $data
     * @return string
     */
    public function getSectionId(array $data): string
    {
        return 'section-' . $data['uid'];
    }
}
