<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ViewHelpers;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\ViewHelpers\DomainViewHelper;
use TYPO3\CMS\Core\Http\ServerRequest;

class DomainViewHelperTest extends HeadlessUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    public function testReturnsConfiguredUrls(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::never())->method('withRequest');
        $urlUtility->method('getFrontendUrl')->willReturn('https://front.tld');
        $urlUtility->method('getProxyUrl')->willReturn('https://front.tld/api');
        $urlUtility->method('getStorageProxyUrl')->willReturn('https://front.tld/api/fileadmin');

        self::assertSame('https://front.tld', $this->render($urlUtility, 'frontendBase'));
        self::assertSame('https://front.tld/api', $this->render($urlUtility, 'proxyUrl'));
        self::assertSame('https://front.tld/api/fileadmin', $this->render($urlUtility, 'storageProxyUrl'));
    }

    public function testReturnsNullForUnknownReturnValue(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);

        self::assertNull($this->render($urlUtility, 'unknown'));
        self::assertNull($this->render($urlUtility, null));
    }

    public function testBindsCurrentRequestWhenAvailable(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest();

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->expects(self::once())->method('withRequest')
            ->with($GLOBALS['TYPO3_REQUEST'])
            ->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn('https://front.tld');

        self::assertSame('https://front.tld', $this->render($urlUtility, 'frontendBase'));
    }

    private function render(HeadlessFrontendUrlInterface $urlUtility, ?string $return): mixed
    {
        $viewHelper = new DomainViewHelper($urlUtility);
        $viewHelper->setArguments(['return' => $return]);

        return $viewHelper->render();
    }
}
