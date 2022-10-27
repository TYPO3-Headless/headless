<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use TYPO3\CMS\Core\Site\Entity\Site;

interface HeadlessFrontendUrlInterface
{
    public function withSite(Site $site): self;

    public function getFrontendUrlForPage(string $url, int $pageUid, string $returnField = 'frontendBase'): string;

    public function getFrontendUrl(): string;

    public function getProxyUrl(): string;

    public function getStorageProxyUrl(): string;

    public function resolveKey(string $key): string;

    public function prepareRelativeUrlIfPossible(string $targetUrl): string;
}
