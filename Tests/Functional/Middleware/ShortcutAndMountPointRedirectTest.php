<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\Middleware;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A shortcut page in FULL headless mode must surface as a JSON envelope
 * with the target mapped to the frontendBase (relative when possible)
 * instead of a raw 30x Location header.
 */
class ShortcutAndMountPointRedirectTest extends BaseHeadlessTesting
{
    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shortcut_pages.csv');

        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            "rootPageId: 1\nbase: https://website.local/\nheadless: 1\nfrontendBase: https://front.local\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );
    }

    public function testShortcutPageIsReturnedAsJsonRedirectWithFrontendBaseUrl(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/go')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string)$response->getBody(), true);

        self::assertSame('/page2', $payload['redirectUrl']);
        self::assertSame(307, $payload['statusCode']);
    }

    public function testShortcutPageStaysHttpRedirectWithoutHeadlessMode(): void
    {
        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            "rootPageId: 1\nbase: https://website.local/\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/go')
        );

        self::assertSame(307, $response->getStatusCode());
        self::assertStringStartsWith('https://website.local/page2', $response->getHeaderLine('location'));
    }
}
