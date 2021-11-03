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

use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * @codeCoverageIgnore
 */
interface JsonViewDemandInterface
{
    public function getPageId(): int;
    public function getSite(): Site;
    public function getSiteLanguage(): SiteLanguage;
    public function getFeGroup(): int;
    public function isHiddenContentVisible(): bool;
    public function getPageTypeMode(): string;
    public function getLanguageId(): int;
    public function getPluginNamespace(): string;
    public function toArray(): array;
    public function isInitialized(): bool;
}
