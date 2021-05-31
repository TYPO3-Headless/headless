<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

namespace FriendsOfTYPO3\Headless\Dto;

interface JsonViewDemandDtoInterface
{
    /**
     * @return int
     */
    public function getPageId(): int;

    /**
     * @return \TYPO3\CMS\Core\Site\Entity\Site
     */
    public function getSite(): \TYPO3\CMS\Core\Site\Entity\Site;

    /**
     * @return \TYPO3\CMS\Core\Site\Entity\SiteLanguage
     */
    public function getSiteLanguage(): \TYPO3\CMS\Core\Site\Entity\SiteLanguage;

    /**
     * @return int
     */
    public function getFeGroup(): int;

    /**
     * @return bool
     */
    public function isHiddenContentVisible(): bool;

    /**
     * @return string
     */
    public function getPageTypeMode(): string;

    /**
     * @return int
     */
    public function getLanguageId(): int;

    /**
     * @return string
     */
    public function getPluginNamespace(): string;

    /**
     * @return array
     */
    public function getCurrentDemandArgumentsAsArray(): array;

    /**
     * @return bool
     */
    public function isInitialized(): bool;
}
