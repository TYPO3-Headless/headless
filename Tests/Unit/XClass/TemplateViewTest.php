<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\XClass;

use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\XClass\TemplateView;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolver;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Cache\FluidCacheInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

use function json_encode;

#[IgnoreDeprecations]
class TemplateViewTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(HeadlessModeInterface::class, new HeadlessMode());
        GeneralUtility::setContainer($container);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(GeneralUtility::class, 'container'))->setValue(null, null);
        parent::tearDown();
    }

    public function testTemplateNotFoundRender(): void
    {
        $this->expectException(InvalidTemplateResourceException::class);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 1) // fe request
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $templatePaths = $this->createMock(TemplatePaths::class);
        $context = new RenderingContext($this->createMock(ViewHelperResolver::class), $this->createMock(FluidCacheInterface::class), [], [], $templatePaths);

        $variableProvider = new StandardVariableProvider();
        $variableProvider->add('settings', ['phpTemplate' => 1]);

        $context->setVariableProvider($variableProvider);
        $view = new TemplateView($context);
        $view->render();
    }

    public function testTemplateRender(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 1) // fe request
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $templatePaths = $this->createMock(TemplatePaths::class);
        $templatePaths->method('resolveTemplateFileForControllerAndActionAndFormat')->willReturn(__DIR__ . '/Fixtures/Templates/Default/Default.php');

        $context = new RenderingContext($this->createMock(ViewHelperResolver::class), $this->createMock(FluidCacheInterface::class), [], [], $templatePaths);

        $variableProvider = new StandardVariableProvider();
        $variableProvider->add('settings', ['phpTemplate' => 1]);
        $variableProvider->add('testValue', 'TestingJsonValue');

        $context->setVariableProvider($variableProvider);

        $view = new TemplateView($context);

        self::assertSame(json_encode(['testKey' => 'TestingJsonValue']), $view->render());
    }

    public function testTemplateFoundRender(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 1) // fe request
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $templatePaths = $this->createMock(TemplatePaths::class);

        $context = new RenderingContext($this->createMock(ViewHelperResolver::class), $this->createMock(FluidCacheInterface::class), [], [], $templatePaths);

        $variableProvider = new StandardVariableProvider();
        $variableProvider->add('settings', ['phpTemplate' => 1]);
        $variableProvider->add('testValue', 'TestingJsonValue');

        $context->setVariableProvider($variableProvider);

        $templatePaths = $this->createMock(TemplatePaths::class);
        $templatePaths->method('resolveTemplateFileForControllerAndActionAndFormat')->willReturn(null);

        $context->setTemplatePaths($templatePaths);

        $this->expectException(InvalidTemplateResourceException::class);

        $view = new TemplateView($context);
        $view->render();
    }

    public function testChangingAction(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 1) // fe request
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $templatePaths = $this->createMock(TemplatePaths::class);
        $context = new RenderingContext($this->createMock(ViewHelperResolver::class), $this->createMock(FluidCacheInterface::class), [], [], $templatePaths);

        $variableProvider = new StandardVariableProvider();
        $variableProvider->add('settings', ['phpTemplate' => 1]);
        $variableProvider->add('testValue', 'TestingJsonValue');

        $context->setVariableProvider($variableProvider);

        $templatePaths = $this->createMock(TemplatePaths::class);
        $templatePaths->method('resolveTemplateFileForControllerAndActionAndFormat')->willReturn(__DIR__ . '/Fixtures/Templates/Default/Default.php');

        $context->setTemplatePaths($templatePaths);

        self::assertSame('Default', $context->getControllerAction());

        $view = new TemplateView($context);
        $view->render('test');
        self::assertSame('Test', $context->getControllerAction());
    }

    public function testExceptionInTemplate(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', 1) // fe request
            ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL));

        $templatePaths = $this->createMock(TemplatePaths::class);
        $context = new RenderingContext($this->createMock(ViewHelperResolver::class), $this->createMock(FluidCacheInterface::class), [], [], $templatePaths);

        $variableProvider = new StandardVariableProvider();
        $variableProvider->add('settings', ['phpTemplate' => 1]);
        $variableProvider->add('testValue', 'TestingJsonValue');

        $context->setVariableProvider($variableProvider);

        $templatePaths = $this->createMock(TemplatePaths::class);
        $templatePaths->method('resolveTemplateFileForControllerAndActionAndFormat')->willReturn(__DIR__ . '/Fixtures/Templates/Default/DefaultException.php');

        $context->setTemplatePaths($templatePaths);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Example exception in template');

        $view = new TemplateView($context);
        $view->render();
    }
}
