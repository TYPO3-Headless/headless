<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\DataProcessing\RootSiteProcessing;

use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteSortingInterface;

use function array_reverse;

class TestSiteSorting implements SiteSortingInterface
{
    /**
     * @param array<\TYPO3\CMS\Core\Site\Entity\Site> $sites
     * @param array<int, array<string, mixed>> $pages
     */
    public function __construct(
        protected array $sites,
        protected array $pages,
        protected string $sortingField
    ) {}

    public function sort(): array
    {
        return array_reverse($this->sites);
    }
}
