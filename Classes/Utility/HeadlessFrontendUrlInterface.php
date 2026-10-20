<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

interface HeadlessFrontendUrlInterface
{
    public function withSite(Site $site): HeadlessFrontendUrlInterface;

    public function withRequest(ServerRequestInterface $request): HeadlessFrontendUrlInterface;
    public function getFrontendUrlForPage(string $url, int $pageUid, string $returnField = 'frontendBase'): string;
    public function getFrontendUrlWithSite(string $url, SiteInterface $site, string $returnField = 'frontendBase'): string;

    public function getFrontendUrl(): string;

    public function getProxyUrl(): string;

    /**
     * Returns the configured frontendFileApi base URL. When $storageUid is
     * provided and frontendFileApi is configured as an array keyed by storage
     * UID, the URL for that storage is returned. When called with no argument,
     * the first entry is returned as a best-effort fallback.
     */
    public function getStorageProxyUrl(?int $storageUid = null): string;

    /**
     * Whether frontendFileApi is configured as a per-storage array with a URL
     * for this specific storage UID. When true, callers should use the URL
     * returned by getStorageProxyUrl($storageUid) directly as the storage's
     * base URI, bypassing URL composition from frontendBase + basePath.
     */
    public function hasStorageSpecificFrontendFileApi(int $storageUid): bool;

    public function resolveKey(string $key): string;

    public function prepareRelativeUrlIfPossible(string $targetUrl): string;
}
