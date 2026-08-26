<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Redirects\Form\Element;

use FriendsOfTYPO3\Headless\Redirects\Form\Element\QrCodeElement;
use FriendsOfTYPO3\Headless\Redirects\SourceUrlResolver;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;

class QrCodeElementTest extends HeadlessUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('translate')->willReturnArgument(0);
        $GLOBALS['LANG'] = $languageService;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        parent::tearDown();
    }

    public function testQrCodeContentIsReplacedWithResolvedFrontendUrl(): void
    {
        $request = new ServerRequest();

        $urlResolver = $this->createMock(SourceUrlResolver::class);
        $urlResolver->method('resolve')->with('api.tld', '/go', $request)->willReturn('https://frontend.tld/go');

        $element = new QrCodeElement($urlResolver);
        $element->setData($this->buildData($request));

        $html = $element->render()['html'];

        self::assertStringContainsString('content="https://frontend.tld/go"', $html);
        self::assertStringNotContainsString('content="https://api.tld/go"', $html);
    }

    public function testQrCodeContentIsKeptWhenUrlCannotBeResolved(): void
    {
        $urlResolver = $this->createMock(SourceUrlResolver::class);
        $urlResolver->method('resolve')->willReturn(null);

        $element = new QrCodeElement($urlResolver);
        $element->setData($this->buildData(new ServerRequest()));

        self::assertStringContainsString('content="https://api.tld/go"', $element->render()['html']);
    }

    public function testQrCodeContentIsKeptWithoutRequestInData(): void
    {
        $urlResolver = $this->createMock(SourceUrlResolver::class);
        $urlResolver->expects(self::never())->method('resolve');

        $element = new QrCodeElement($urlResolver);
        $element->setData($this->buildData(null));

        self::assertStringContainsString('content="https://api.tld/go"', $element->render()['html']);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(?ServerRequest $request): array
    {
        $data = [
            'command' => 'edit',
            'databaseRow' => ['source_host' => 'api.tld', 'source_path' => '/go'],
            'parameterArray' => ['fieldConf' => ['config' => []]],
        ];

        if ($request !== null) {
            $data['request'] = $request;
        }

        return $data;
    }
}
