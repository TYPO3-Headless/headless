<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\Redirects;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * End-to-end coverage for the JSON redirect envelope:
 * core RedirectHandler → RedirectWasHitEvent → HeadlessRedirectResponseListener,
 * including the extbase-additionalParams correction done by
 * FriendsOfTYPO3\Headless\Redirects\TargetUrlResolver.
 */
class HeadlessRedirectsTest extends BaseHeadlessTesting
{
    protected array $coreExtensionsToLoad = [
        'install',
        'redirects',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/redirects.csv');

        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            "rootPageId: 1\nbase: https://website.local/\nheadless: 1\nfrontendBase: https://front.local\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );
    }

    public function testMatchedRedirectIsReturnedAsJsonWithFrontendBaseUrl(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/old-path')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['redirectUrl' => '/page1', 'statusCode' => 301],
            json_decode((string)$response->getBody(), true)
        );
    }

    public function testExtbaseAdditionalParamsAreAppliedToTargetUrl(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/old-action')
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string)$response->getBody(), true);

        self::assertSame(307, $payload['statusCode']);
        self::assertStringStartsWith('/page1/page1_1?', $payload['redirectUrl']);
        self::assertStringContainsString('tx_demo%5Bcontroller%5D=Demo', $payload['redirectUrl']);
        self::assertStringContainsString('tx_demo%5Baction%5D=show', $payload['redirectUrl']);
        self::assertStringContainsString('cHash=', $payload['redirectUrl']);
    }

    public function testKeepQueryParametersRedirectPreservesRequestQueryAndAdditionalParams(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('https://website.local/old-keep?utm=1')
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string)$response->getBody(), true);

        self::assertSame(302, $payload['statusCode']);
        self::assertStringStartsWith('/page1/page1_1?', $payload['redirectUrl']);
        // Core already applies the typolink additionalParams when
        // "keep query parameters" is enabled — the resolver must not
        // regenerate the URL and drop the preserved request query.
        self::assertStringContainsString('tx_demo', $payload['redirectUrl']);
        self::assertStringContainsString('utm=1', $payload['redirectUrl']);
    }
}
