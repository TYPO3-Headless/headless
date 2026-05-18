<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\View;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\View\HeadlessPhpView;
use FriendsOfTYPO3\Headless\View\HeadlessViewFactory;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;

/**
 * Asserts the DI wiring for the `headless.overrideFluidTemplates` feature
 * flag and that the factory routes `format='php'` to {@see HeadlessPhpView}
 * while leaving every other format on the default Fluid view.
 */
class HeadlessViewFactoryIntegrationTest extends BaseHeadlessTesting
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'features' => [
                'headless.overrideFluidTemplates' => true,
            ],
        ],
    ];

    public function testContainerAliasResolvesToHeadlessFactoryWhenFeatureFlagIsOn(): void
    {
        $factory = $this->get(ViewFactoryInterface::class);

        self::assertInstanceOf(
            HeadlessViewFactory::class,
            $factory,
            'feature flag must replace the default Fluid factory in the container alias'
        );
    }

    public function testFactoryStillReturnsFluidViewForDefaultFormat(): void
    {
        $factory = $this->get(ViewFactoryInterface::class);

        $view = $factory->create(new ViewFactoryData(
            templateRootPaths: [Environment::getFrameworkBasePath() . '/headless/Resources/Private/'],
            format: 'html',
        ));

        self::assertInstanceOf(
            FluidViewAdapter::class,
            $view,
            'non-php format must still be served by Fluid'
        );
    }

    public function testFactoryReturnsHeadlessPhpViewForPhpFormatWithHeadlessRequest(): void
    {
        $factory = $this->get(ViewFactoryInterface::class);
        $request = (new ServerRequest('https://website.local/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $view = $factory->create(new ViewFactoryData(
            templateRootPaths: [Environment::getPublicPath() . '/typo3temp/var/tests/'],
            request: $request,
            format: 'php',
        ));

        self::assertInstanceOf(HeadlessPhpView::class, $view);
    }

    public function testFactoryFallsBackToFluidWhenHeadlessDisabled(): void
    {
        $factory = $this->get(ViewFactoryInterface::class);
        $request = (new ServerRequest('https://website.local/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        // No 'headless' attribute → NONE → fall-through.

        $view = $factory->create(new ViewFactoryData(
            templateRootPaths: [Environment::getPublicPath() . '/typo3temp/var/tests/'],
            request: $request,
            format: 'php',
        ));

        self::assertNotInstanceOf(HeadlessPhpView::class, $view);
    }
}
