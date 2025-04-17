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
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

interface HeadlessModeInterface
{
    public const NONE = 0;
    public const FULL = 1;
    public const MIXED = 2;

    public function withRequest(ServerRequestInterface $request): self;
    public function isEnabled(): bool;
    public function overrideBackendRequestBySite(SiteInterface $site, ?SiteLanguage $language = null): ServerRequestInterface;
}
