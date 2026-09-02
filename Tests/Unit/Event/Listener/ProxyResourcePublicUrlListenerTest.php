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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Event\GeneratePublicUrlForResourceEvent;
use TYPO3\CMS\Core\Resource\ResourceInterface;
use TYPO3\CMS\Core\Resource\ResourceStorage;

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

    private function listener(bool $headlessEnabled = true): ProxyResourcePublicUrlListener
    {
        $headlessMode = $this->createMock(HeadlessModeInterface::class);
        $headlessMode->method('isEnabledFor')->willReturn($headlessEnabled);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('getFrontendUrl')->willReturn(self::FRONTEND_URL);
        $urlUtility->method('getProxyUrl')->willReturn(self::PROXY_URL);
        $urlUtility->method('getStorageProxyUrl')->willReturn(self::STORAGE_PROXY_URL);

        return new ProxyResourcePublicUrlListener($headlessMode, $urlUtility);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function storage(array $config): ResourceStorage
    {
        $storage = $this->createMock(ResourceStorage::class);
        $storage->method('getConfiguration')->willReturn($config);

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
