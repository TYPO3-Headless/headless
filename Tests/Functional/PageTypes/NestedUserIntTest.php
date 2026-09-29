<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\PageTypes;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

use const JSON_THROW_ON_ERROR;

class NestedUserIntTest extends BasePageTypesHeadlessTesting
{
    protected array $typoScriptSetupFiles = [
        'EXT:headless/Configuration/TypoScript/setup.typoscript',
        'EXT:headless/Tests/Functional/Fixtures/nested_user_int.typoscript',
    ];

    public function setUp(): void
    {
        parent::setUp();
        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            "rootPageId: 1\nbase: /\nheadless: 1\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );
    }

    public function testUserIntInsideNestedJsonIsResolved(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/?type=834'));
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('HEADLESS_INT', $body);
        self::assertStringNotContainsString('INT_SCRIPT', $body);

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('direct-resolved', $data['direct']);
        self::assertSame('nested-resolved', $data['nested']['record']);
    }
}
