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
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * @codeCoverageIgnore
 */
interface BackendTsfeServiceInterface
{
    public function bootFrontendControllerForPage(int $pageId, JsonViewDemandInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings, bool $bootContent = false): void;
    public function getPageFromTsfe(JsonViewDemandInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings): string;
    public function getFrontendRequest(JsonViewDemandInterface $demand, JsonViewConfigurationServiceInterface $configurationService): ServerRequest;
}
