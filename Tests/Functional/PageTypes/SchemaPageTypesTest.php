<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\PageTypes;

use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

class SchemaPageTypesTest extends BasePageTypesTest
{
    /**
     * @test
     */
    public function getMenu()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/?type=834')
        );

        self::assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function getPage()
    {
        $response = $this->executeFrontendRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());
    }
}
