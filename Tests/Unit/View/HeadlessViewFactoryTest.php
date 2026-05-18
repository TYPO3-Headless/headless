<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\View;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\View\HeadlessPhpView;
use FriendsOfTYPO3\Headless\View\HeadlessViewFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

class HeadlessViewFactoryTest extends TestCase
{
    public function testFallsThroughWhenFormatIsNotPhp(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
        );

        $data = new ViewFactoryData(format: 'html', request: $this->frontendRequest());

        self::assertSame($expected, $factory->create($data));
    }

    public function testFallsThroughWhenFeatureFlagDisabled(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(false),
            $this->headlessModeEnabled(true),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->frontendRequest());

        self::assertSame($expected, $factory->create($data));
    }

    public function testFallsThroughWhenRequestMissing(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
        );

        $data = new ViewFactoryData(format: 'php', request: null);

        self::assertSame($expected, $factory->create($data));
    }

    public function testFallsThroughForBackendRequest(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->backendRequest());

        self::assertSame($expected, $factory->create($data));
    }

    public function testFallsThroughWhenHeadlessDisabled(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(false),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->frontendRequest());

        self::assertSame($expected, $factory->create($data));
    }

    public function testReturnsHeadlessPhpViewWhenOptedIn(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $inner->expects(self::never())->method('create');

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->frontendRequest());

        self::assertInstanceOf(HeadlessPhpView::class, $factory->create($data));
    }

    private function featuresWith(bool $enabled): Features
    {
        $features = $this->createMock(Features::class);
        $features->method('isFeatureEnabled')
            ->with('headless.overrideFluidTemplates')
            ->willReturn($enabled);
        return $features;
    }

    private function headlessModeEnabled(bool $enabled): HeadlessModeInterface
    {
        $mode = $this->createMock(HeadlessModeInterface::class);
        $mode->method('withRequest')->willReturnSelf();
        $mode->method('isEnabled')->willReturn($enabled);
        $mode->method('isEnabledFor')->willReturn($enabled);
        return $mode;
    }

    private function frontendRequest(): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }

    private function backendRequest(): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }
}
