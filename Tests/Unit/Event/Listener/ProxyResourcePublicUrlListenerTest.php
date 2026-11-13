<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\ProxyResourcePublicUrlListener;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Event\GeneratePublicUrlForResourceEvent;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

use function str_contains;

class ProxyResourcePublicUrlListenerTest extends HeadlessUnitTestCase
{
    private const FRONTEND_URL = 'https://example.com';
    private const PROXY_URL = 'https://example.com/proxy';
    private const STORAGE_PROXY_URL = 'https://example.com/files';

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

    public function testSetsProxiedUrlForRelativeBasePath(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertSame(
            'https://example.com/proxy/fileadmin/foo/bar.jpg',
            $event->getPublicUrl()
        );
    }

    public function testSetsProxiedUrlForRelativeBaseUri(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['baseUri' => '/assets2/', 'basePath' => '/fileadmin2/', 'pathType' => 'relative']),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertSame(
            'https://example.com/proxy/assets2/foo/bar.jpg',
            $event->getPublicUrl()
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function pathOnlyWithoutFrontendHostProvider(): iterable
    {
        yield 'storage url' => ['/headless/fileadmin2', '', ''];
        yield 'storage proxy url' => ['', '/files/shared', ''];
        yield 'proxy url' => ['', '', '/headless'];
    }

    #[DataProvider('pathOnlyWithoutFrontendHostProvider')]
    public function testPathOnlyUrlWithoutFrontendHostKeepsPublicUrlUnset(
        string $storageUrl,
        string $storageProxyUrl,
        string $proxyUrl,
    ): void {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn('');
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturn($storageUrl);
        $urlUtility->method('getStorageProxyUrl')->willReturn($storageProxyUrl);
        $urlUtility->method('getProxyUrl')->willReturn($proxyUrl);

        $config = $storageProxyUrl !== '' ? [] : ['basePath' => 'fileadmin/', 'pathType' => 'relative'];
        $event = $this->event($this->localDriver(public: true), $this->storage($config), '/foo/bar.jpg');

        (new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility))($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testIgnoresMissingRequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo.jpg',
        );

        ($this->listener())($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testLeavesProtocolRelativeBaseUriUnchanged(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['baseUri' => '//cdn.example.com/assets/']),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testLeavesBaseUriWithOwnHostUnchanged(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['baseUri' => 'https://cdn.example.com/assets/', 'basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testStorageUrlFromSiteConfigWinsOverBaseUriWithOwnHost(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['baseUri' => 'https://cdn.example.com/assets/'], uid: 2),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertSame('https://cdn2.example.com/assets/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testUsesStorageUrlFromSiteConfig(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin2/', 'pathType' => 'relative'], uid: 2),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertSame('https://cdn2.example.com/assets/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testPathOnlyStorageUrlIsServedFromFrontendHost(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin2/', 'pathType' => 'relative'], uid: 4),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertSame('https://example.com/proxy/fileadmin2/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testPathOnlyStorageProxyUrlIsServedFromFrontendHost(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn(self::FRONTEND_URL);
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturn('');
        $urlUtility->method('getStorageProxyUrl')->willReturn('/files/shared');

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage([]),
            identifier: '/foo/bar.jpg',
        );

        (new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility))($event);

        self::assertSame('https://example.com/files/shared/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testStoragePathWithoutProxyPathStaysOnFrontendHost(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn(self::FRONTEND_URL);
        $urlUtility->method('getProxyUrl')->willReturn('');
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturn('');

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => 'fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo/bar.jpg',
        );

        (new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility))($event);

        self::assertSame('https://example.com/fileadmin/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testFrontendBasePathDoesNotPrefixProxyOrPathOnlyUrls(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn('https://example.com/shop');
        $urlUtility->method('getProxyUrl')->willReturn('https://example.com/headless');
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturnMap([
            [1, ''],
            [2, '/headless/fileadmin2'],
        ]);

        $listener = new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility);
        $proxied = $this->event(
            $this->localDriver(public: true),
            $this->storage(['basePath' => 'fileadmin/', 'pathType' => 'relative']),
            '/foo.jpg',
        );
        $mapped = $this->event(
            $this->localDriver(public: true),
            $this->storage(['basePath' => 'fileadmin2/', 'pathType' => 'relative'], uid: 2),
            '/foo.jpg',
        );
        $listener($proxied);
        $listener($mapped);

        self::assertSame('https://example.com/headless/fileadmin/foo.jpg', $proxied->getPublicUrl());
        self::assertSame('https://example.com/headless/fileadmin2/foo.jpg', $mapped->getPublicUrl());
    }

    public function testUsesHostOfProxyUrl(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn(self::FRONTEND_URL);
        $urlUtility->method('getProxyUrl')->willReturn('https://proxy.example.com:8443/headless');
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturn('');

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => 'fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo/bar.jpg',
        );

        (new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility))($event);

        self::assertSame('https://proxy.example.com:8443/headless/fileadmin/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testPathOnlyProxyUrlIsServedFromFrontendHost(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn(self::FRONTEND_URL);
        $urlUtility->method('getProxyUrl')->willReturn('/headless');
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturn('');

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => 'fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo/bar.jpg',
        );

        (new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility))($event);

        self::assertSame('https://example.com/headless/fileadmin/foo/bar.jpg', $event->getPublicUrl());
    }

    public function testStorageUrlIsResolvedOncePerRequestAndStorage(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->expects(self::exactly(2))->method('getStorageProxyUrlForStorage')
            ->willReturnMap([
                [2, 'https://cdn2.example.com/assets'],
                [3, 'https://cdn3.example.com/assets'],
            ]);

        $listener = new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility);
        $storage2 = $this->storage([], uid: 2);
        $storage3 = $this->storage([], uid: 3);

        $first = $this->event($this->localDriver(public: true), $storage2, '/a.jpg');
        $second = $this->event($this->localDriver(public: true), $storage2, '/b.jpg');
        $third = $this->event($this->localDriver(public: true), $storage3, '/c.jpg');
        $listener($first);
        $listener($second);
        $listener($third);

        self::assertSame('https://cdn2.example.com/assets/a.jpg', $first->getPublicUrl());
        self::assertSame('https://cdn2.example.com/assets/b.jpg', $second->getPublicUrl());
        self::assertSame('https://cdn3.example.com/assets/c.jpg', $third->getPublicUrl());
    }

    public function testStorageUrlIsResolvedAgainForNewRequest(): void
    {
        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->expects(self::exactly(2))->method('getStorageProxyUrlForStorage')
            ->with(2)
            ->willReturnOnConsecutiveCalls('https://cdn-a.example.com', 'https://cdn-b.example.com');

        $listener = new ProxyResourcePublicUrlListener($this->headlessMode(), $urlUtility);
        $storage = $this->storage([], uid: 2);

        $first = $this->event($this->localDriver(public: true), $storage, '/a.jpg');
        $listener($first);

        $GLOBALS['TYPO3_REQUEST'] = $this->createFrontendRequest();
        $second = $this->event($this->localDriver(public: true), $storage, '/a.jpg');
        $listener($second);

        self::assertSame('https://cdn-a.example.com/a.jpg', $first->getPublicUrl());
        self::assertSame('https://cdn-b.example.com/a.jpg', $second->getPublicUrl());
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, string>}>
     */
    public static function siteConfigExampleProvider(): iterable
    {
        yield 'production' => ['Production', [
            1 => 'https://example.com/headless/fileadmin/user_upload/photo.jpg',
            2 => 'https://cdn2.example.com/fileadmin2/user_upload/photo.jpg',
            3 => 'https://files.example.com/shared/user_upload/photo.jpg',
        ]];
        yield 'development variant' => ['Development', [
            1 => 'https://dev.example.com/headless/fileadmin/user_upload/photo.jpg',
            2 => 'https://cdn2-dev.example.com/fileadmin2/user_upload/photo.jpg',
            3 => 'https://files.dev.example.com/shared/user_upload/photo.jpg',
        ]];
        yield 'variant without map' => ['Testing', [
            1 => 'https://test.example.com/headless/fileadmin/user_upload/photo.jpg',
            2 => 'https://test.example.com/headless/fileadmin2/user_upload/photo.jpg',
            3 => 'https://files.test.example.com/shared/user_upload/photo.jpg',
        ]];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('siteConfigExampleProvider')]
    public function testResolvesUrlsFromSiteConfig(string $context, array $expected): void
    {
        $site = $this->createMock(Site::class);
        $site->method('getBase')->willReturn(new Uri('https://api.example.com/'));
        $site->method('getConfiguration')->willReturn([
            'base' => 'https://api.example.com',
            'frontendBase' => 'https://example.com',
            'frontendApiProxy' => 'https://example.com/headless',
            'frontendFileApi' => 'https://files.example.com/shared',
            'frontendFileApiByStorage' => [2 => 'https://cdn2.example.com/fileadmin2'],
            'baseVariants' => [
                [
                    'base' => 'https://api.dev.example.com',
                    'condition' => 'applicationContext == "Development"',
                    'frontendBase' => 'https://dev.example.com',
                    'frontendApiProxy' => 'https://dev.example.com/headless',
                    'frontendFileApi' => 'https://files.dev.example.com/shared',
                    'frontendFileApiByStorage' => [2 => 'https://cdn2-dev.example.com/fileadmin2'],
                ],
                [
                    'base' => 'https://api.test.example.com',
                    'condition' => 'applicationContext == "Testing"',
                    'frontendBase' => 'https://test.example.com',
                    'frontendApiProxy' => 'https://test.example.com/headless',
                    'frontendFileApi' => 'https://files.test.example.com/shared',
                ],
            ],
        ]);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://api.example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site);

        $resolver = $this->createMock(Resolver::class);
        $resolver->method('evaluate')->willReturnCallback(
            static fn(string $condition): bool => str_contains($condition, $context)
        );
        $headlessMode = $this->headlessMode();
        $headlessMode->method('withRequest')->willReturnSelf();

        $listener = new ProxyResourcePublicUrlListener(
            $headlessMode,
            new UrlUtility($resolver, $this->createMock(SiteFinder::class), $headlessMode),
        );

        $storages = [
            1 => ['basePath' => 'fileadmin/', 'pathType' => 'relative'],
            2 => ['basePath' => 'fileadmin2/', 'pathType' => 'relative'],
            3 => ['basePath' => '/mnt/shared/', 'pathType' => 'absolute'],
        ];
        foreach ($storages as $uid => $config) {
            $event = $this->event($this->localDriver(public: true), $this->storage($config, $uid), '/user_upload/photo.jpg');
            $listener($event);

            self::assertSame($expected[$uid], $event->getPublicUrl(), 'storage ' . $uid);
        }
    }

    public function testFallsBackToStorageProxyUrlWhenNoBasePath(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage([]),
            identifier: '/foo/bar.jpg',
        );

        ($this->listener())($event);

        self::assertSame(
            self::STORAGE_PROXY_URL . '/foo/bar.jpg',
            $event->getPublicUrl()
        );
    }

    public function testEncodesSpecialCharsInIdentifier(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/some folder/file with space.jpg',
        );

        ($this->listener())($event);

        self::assertSame(
            'https://example.com/proxy/fileadmin/some%20folder/file%20with%20space.jpg',
            $event->getPublicUrl()
        );
    }

    public function testIgnoresAlreadySetPublicUrl(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo.jpg',
        );
        $event->setPublicUrl('https://cdn.example.com/already-set.jpg');

        ($this->listener())($event);

        self::assertSame('https://cdn.example.com/already-set.jpg', $event->getPublicUrl());
    }

    public function testIgnoresNonLocalDriver(): void
    {
        $event = $this->event(
            driver: $this->createMock(DriverInterface::class),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo.jpg',
        );

        ($this->listener())($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testIgnoresNonPublicDriver(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: false),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo.jpg',
        );

        ($this->listener())($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testIgnoresBackendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->createMock(ServerRequestInterface::class);
        $GLOBALS['TYPO3_REQUEST']->method('getAttribute')->willReturnCallback(
            static fn(string $key) => $key === 'applicationType' ? SystemEnvironmentBuilder::REQUESTTYPE_BE : null
        );

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo.jpg',
        );

        ($this->listener())($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testIgnoresWhenHeadlessModeDisabled(): void
    {
        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage(['basePath' => '/fileadmin/', 'pathType' => 'relative']),
            identifier: '/foo.jpg',
        );

        ($this->listener(headlessEnabled: false))($event);

        self::assertNull($event->getPublicUrl());
    }

    public function testEmptyStorageProxyUrlKeepsPublicUrlUnsetAndIsCachedPerRequest(): void
    {
        $headlessMode = $this->createMock(HeadlessModeInterface::class);
        $headlessMode->method('isEnabledFor')->willReturn(true);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->expects(self::once())->method('getStorageProxyUrl')->willReturn('');

        $listener = new ProxyResourcePublicUrlListener($headlessMode, $urlUtility);

        $event = $this->event(
            driver: $this->localDriver(public: true),
            storage: $this->storage([]),
            identifier: '/foo/bar.jpg',
        );

        $listener($event);
        $listener($event);

        self::assertNull($event->getPublicUrl());
    }

    private function headlessMode(bool $enabled = true): HeadlessModeInterface&MockObject
    {
        $headlessMode = $this->createMock(HeadlessModeInterface::class);
        $headlessMode->method('isEnabledFor')->willReturn($enabled);

        return $headlessMode;
    }

    private function listener(bool $headlessEnabled = true): ProxyResourcePublicUrlListener
    {
        $headlessMode = $this->headlessMode($headlessEnabled);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn(self::FRONTEND_URL);
        $urlUtility->method('getProxyUrl')->willReturn(self::PROXY_URL);
        $urlUtility->method('getStorageProxyUrl')->willReturn(self::STORAGE_PROXY_URL);
        $urlUtility->method('getStorageProxyUrlForStorage')->willReturnMap([
            [1, ''],
            [2, 'https://cdn2.example.com/assets/'],
            [4, '/proxy/fileadmin2'],
        ]);

        return new ProxyResourcePublicUrlListener($headlessMode, $urlUtility);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function storage(array $config, int $uid = 1): ResourceStorage
    {
        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getConfiguration')->willReturn($config);
        $storage->method('getUid')->willReturn($uid);

        return $storage;
    }

    private function localDriver(bool $public): LocalDriver
    {
        $driver = $this->createMock(LocalDriver::class);
        $driver->method('hasCapability')->willReturnCallback(
            static fn(int $cap) => $cap === Capabilities::CAPABILITY_PUBLIC && $public
        );

        return $driver;
    }

    private function event(
        DriverInterface $driver,
        ResourceStorage $storage,
        string $identifier,
    ): GeneratePublicUrlForResourceEvent {
        $resource = $this->createMock(ResourceInterface::class);
        $resource->method('getIdentifier')->willReturn($identifier);

        return new GeneratePublicUrlForResourceEvent($resource, $storage, $driver);
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
