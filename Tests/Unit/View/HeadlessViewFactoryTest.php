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
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;

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

    public function testReturnsHeadlessPhpViewForExtbaseRequestWhenFormatConfiguredInTypoScript(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $inner->expects(self::never())->method('create');

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
            $this->configurationManagerWithFormat('php'),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->extbaseFrontendRequest());

        self::assertInstanceOf(HeadlessPhpView::class, $factory->create($data));
    }

    public function testRefusesRequestForcedPhpFormatAndStripsFormatForFluid(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')
            ->with(self::callback(static fn(ViewFactoryData $data): bool => $data->format === null))
            ->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
            $this->configurationManagerWithFormat('html'),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->extbaseFrontendRequest());

        self::assertSame($expected, $factory->create($data));
    }

    public function testRefusesExtbaseRequestWhenConfigurationManagerUnavailable(): void
    {
        $inner = $this->createMock(ViewFactoryInterface::class);
        $expected = $this->createMock(ViewInterface::class);
        $inner->expects(self::once())->method('create')
            ->with(self::callback(static fn(ViewFactoryData $data): bool => $data->format === null))
            ->willReturn($expected);

        $factory = new HeadlessViewFactory(
            $inner,
            $this->featuresWith(true),
            $this->headlessModeEnabled(true),
        );

        $data = new ViewFactoryData(format: 'php', request: $this->extbaseFrontendRequest());

        self::assertSame($expected, $factory->create($data));
    }

    private function configurationManagerWithFormat(string $format): ConfigurationManagerInterface
    {
        $configurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $configurationManager->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK)
            ->willReturn(['format' => $format]);
        return $configurationManager;
    }

    private function extbaseFrontendRequest(): Request
    {
        return new Request(
            (new ServerRequest())
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('extbase', new ExtbaseRequestParameters())
        );
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
