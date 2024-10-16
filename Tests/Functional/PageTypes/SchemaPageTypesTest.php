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

class SchemaPageTypesTest extends BasePageTypesHeadlessTesting
{
    public function testGetMenu()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/?type=834')
        );

        self::assertEquals(200, $response->getStatusCode());
    }

    public function testGetPage()
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/')
        );

        self::assertEquals(200, $response->getStatusCode());
    }
}
