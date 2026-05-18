<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Resource\Service;

use FriendsOfTYPO3\Headless\Resource\Service\HeadlessImageService;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class HeadlessImageServiceTest extends HeadlessUnitTestCase
{
    private const PROXY_URL = 'https://example.com/proxy';

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_REQUEST'] = $this->createFrontendRequest();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    public function testStripsProxyPrefixThenResolvesByNumericId(): void
    {
        $file = $this->createMock(File::class);
        $resourceFactory = $this->createMock(ResourceFactory::class);
        $resourceFactory->expects(self::once())
            ->method('getFileObject')
            ->with('123')
            ->willReturn($file);

        $service = $this->service($resourceFactory);

        self::assertSame(
            $file,
            $service->getImage(self::PROXY_URL . '/123', null, false)
        );
    }

    public function testDoesNotStripWhenHeadlessModeDisabled(): void
    {
        $resourceFactory = $this->createMock(ResourceFactory::class);
        $resourceFactory->expects(self::once())
            ->method('retrieveFileOrFolderObject')
            ->with(self::PROXY_URL . '/123')
            ->willReturn($this->createMock(File::class));

        $service = $this->service($resourceFactory, headlessEnabled: false);

        $service->getImage(self::PROXY_URL . '/123', null, false);
    }

    public function testDoesNotStripWhenProxyUrlEmpty(): void
    {
        $resourceFactory = $this->createMock(ResourceFactory::class);
        $resourceFactory->expects(self::once())
            ->method('retrieveFileOrFolderObject')
            ->with('fileadmin/test.jpg')
            ->willReturn($this->createMock(File::class));

        $service = $this->service($resourceFactory, proxyUrl: '');

        $service->getImage('fileadmin/test.jpg', null, false);
    }

    public function testDoesNotStripWhenBackendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->createMock(ServerRequestInterface::class);
        $GLOBALS['TYPO3_REQUEST']->method('getAttribute')->willReturnCallback(
            static fn(string $key) => $key === 'applicationType' ? SystemEnvironmentBuilder::REQUESTTYPE_BE : null
        );

        $resourceFactory = $this->createMock(ResourceFactory::class);
        $resourceFactory->expects(self::once())
            ->method('retrieveFileOrFolderObject')
            ->with(self::PROXY_URL . '/123')
            ->willReturn($this->createMock(File::class));

        $service = $this->service($resourceFactory);

        $service->getImage(self::PROXY_URL . '/123', null, false);
    }

    public function testReturnsAlreadyResolvedFileWithoutTouchingResourceFactory(): void
    {
        $file = $this->createMock(File::class);
        $resourceFactory = $this->createMock(ResourceFactory::class);
        $resourceFactory->expects(self::never())->method(self::anything());

        $service = $this->service($resourceFactory);

        self::assertSame(
            $file,
            $service->getImage(self::PROXY_URL . '/123', $file, false)
        );
    }

    private function service(
        ResourceFactory $resourceFactory,
        bool $headlessEnabled = true,
        string $proxyUrl = self::PROXY_URL,
    ): HeadlessImageService {
        $headlessMode = $this->createMock(HeadlessModeInterface::class);
        $headlessMode->method('isEnabledFor')->willReturn($headlessEnabled);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getProxyUrl')->willReturn($proxyUrl);

        return new HeadlessImageService($resourceFactory, $headlessMode, $urlUtility);
    }

    private function createFrontendRequest(): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn(string $key) => $key === 'applicationType' ? SystemEnvironmentBuilder::REQUESTTYPE_FE : null
        );

        return $request;
    }
}
