<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\XClass\Controller;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\XClass\Controller\LoginController;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\FrontendLogin\Configuration\RedirectConfiguration;
use TYPO3\CMS\FrontendLogin\Event\BeforeRedirectEvent;
use TYPO3\CMS\FrontendLogin\Event\LoginErrorOccurredEvent;
use TYPO3\CMS\FrontendLogin\Event\LogoutConfirmedEvent;
use TYPO3\CMS\FrontendLogin\Redirect\RedirectHandler;

/**
 * Locks in the behaviour of {@see LoginController::handleRedirect()} that
 * matters for the headless integration: empty target → null, event veto →
 * null, normal flow → JsonResponse with statusCode 303 and the supplied
 * status keyword.
 */
class LoginControllerTest extends HeadlessUnitTestCase
{
    public function testHandleRedirectReturnsNullWhenRedirectUrlIsEmpty(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);
        $controller = $this->buildController($this->createMock(EventDispatcherInterface::class));

        self::setProtected($controller, 'redirectUrl', '');

        self::assertNull(self::callHandleRedirect($controller));
    }

    public function testHandleRedirectReturnsNullWhenListenerClearsTheUrl(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (BeforeRedirectEvent $event): BeforeRedirectEvent {
                $event->setRedirectUrl('');
                return $event;
            });

        $controller = $this->buildController($eventDispatcher);
        self::setProtected($controller, 'redirectUrl', '/dashboard');

        self::assertNull(
            self::callHandleRedirect($controller),
            'event veto must short-circuit the redirect so loginAction can render the form view'
        );
    }

    public function testHandleRedirectReturnsJsonResponseWithStatusAndUrl(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $controller = $this->buildController($eventDispatcher);
        self::setProtected($controller, 'redirectUrl', '/dashboard');

        $response = self::callHandleRedirect($controller, 'failure');

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['redirectUrl' => '/dashboard', 'statusCode' => 303, 'status' => 'failure'],
            json_decode((string)$response->getBody(), true)
        );
    }

    public function testLoginActionAssignsViewVariablesAndReturnsJson(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->method('getPageIdsRecursive')->with([1, 2], 1)->willReturn([1, 2]);

        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::once())->method('assignMultiple')->with(self::callback(static function (array $variables): bool {
            self::assertSame('success', $variables['status']);
            self::assertSame('1,2', $variables['storagePid']);
            self::assertSame('message-key', $variables['messageKey']);
            self::assertInstanceOf(RequestToken::class, $variables['requestToken']);
            return true;
        }));
        $view->method('render')->willReturn('{"status":"success"}');

        $controller = $this->buildActionController($eventDispatcher, $view, pageRepository: $pageRepository);
        self::setProtected($controller, 'settings', ['pages' => '1,2', 'recursive' => 1]);

        $GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'] = true;

        try {
            $response = $controller->loginAction();
        } finally {
            unset($GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid']);
        }

        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"success"}', (string)$response->getBody());
    }

    public function testLoginActionDispatchesLogoutConfirmedEvent(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);

        $events = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$events): object {
            $events[] = $event;
            return $event;
        });

        $controller = $this->buildActionController($eventDispatcher, $this->createMock(ViewInterface::class));
        $controller->method('isLogoutSuccessful')->willReturn(true);

        $controller->loginAction();

        self::assertInstanceOf(LogoutConfirmedEvent::class, $events[0]);
    }

    public function testLoginActionSetsFailureStatusWhenLoginErrorOccurred(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);

        $events = [];
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$events): object {
            $events[] = $event;
            return $event;
        });

        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::once())->method('assignMultiple')->with(
            self::callback(static fn(array $variables): bool => $variables['status'] === 'failure')
        );

        $controller = $this->buildActionController($eventDispatcher, $view);
        $controller->method('hasLoginErrorOccurred')->willReturn(true);

        $controller->loginAction();

        self::assertInstanceOf(LoginErrorOccurredEvent::class, $events[0]);
    }

    public function testLoginActionReturnsForwardResponseFromLoginForwards(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $forwardResponse = $this->createMock(ResponseInterface::class);

        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::never())->method('assignMultiple');

        $controller = $this->buildActionController($eventDispatcher, $view);
        $controller->method('handleLoginForwards')->willReturn($forwardResponse);

        self::assertSame($forwardResponse, $controller->loginAction());
    }

    public function testLoginActionReturnsRedirectJsonWhenRedirectUrlIsSet(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::FULL);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::never())->method('assignMultiple');

        $controller = $this->buildActionController($eventDispatcher, $view);
        self::setProtected($controller, 'redirectUrl', '/dashboard');

        $response = $controller->loginAction();

        self::assertSame(
            ['redirectUrl' => '/dashboard', 'statusCode' => 303, 'status' => 'success'],
            json_decode((string)$response->getBody(), true)
        );
    }

    public function testLoginActionDelegatesToParentWhenHeadlessIsDisabled(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::NONE);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::once())->method('assignMultiple')->with(
            self::callback(static fn(array $variables): bool => !isset($variables['status']))
        );
        $view->method('render')->willReturn('<form></form>');

        $controller = $this->buildActionController($eventDispatcher, $view, HeadlessModeInterface::NONE);

        $response = $controller->loginAction();

        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<form></form>', (string)$response->getBody());
    }

    public function testHandleRedirectDelegatesToParentWhenHeadlessIsDisabled(): void
    {
        $this->bindHeadlessMode(HeadlessModeInterface::NONE);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $controller = $this->buildActionController($eventDispatcher, $this->createMock(ViewInterface::class), HeadlessModeInterface::NONE);
        self::setProtected($controller, 'redirectUrl', 'https://frontend.tld/dashboard');

        $response = self::callHandleRedirect($controller);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('https://frontend.tld/dashboard', $response->getHeaderLine('Location'));
    }

    private function buildActionController(
        EventDispatcherInterface $eventDispatcher,
        ViewInterface $view,
        int $mode = HeadlessModeInterface::FULL,
        ?PageRepository $pageRepository = null
    ): LoginController&\PHPUnit\Framework\MockObject\MockObject {
        $redirectHandler = $this->createMock(RedirectHandler::class);
        $redirectHandler->method('getLoginFormRedirectUrl')->willReturn('');
        $redirectHandler->method('getReferrerForLoginForm')->willReturn('');

        $controller = $this->getMockBuilder(LoginController::class)
            ->setConstructorArgs([
                $redirectHandler,
                $this->createMock(Context::class),
                $pageRepository ?? $this->createMock(PageRepository::class),
            ])
            ->onlyMethods([
                'isLogoutSuccessful',
                'hasLoginErrorOccurred',
                'handleLoginForwards',
                'getStatusMessageKey',
                'getPermaloginStatus',
                'isRedirectDisabled',
            ])
            ->getMock();

        $controller->method('getStatusMessageKey')->willReturn('message-key');
        $controller->method('getPermaloginStatus')->willReturn(-1);
        $controller->method('isRedirectDisabled')->willReturn(true);

        $httpFactory = new HttpFactory();
        $controller->injectResponseFactory($httpFactory);
        $controller->injectStreamFactory($httpFactory);
        $controller->injectEventDispatcher($eventDispatcher);

        $request = new Request(
            (new \TYPO3\CMS\Core\Http\ServerRequest('https://website.local/'))
                ->withAttribute('headless', new Headless($mode))
                ->withAttribute('extbase', new ExtbaseRequestParameters())
        );
        self::setProtected($controller, 'request', $request);
        self::setProtected($controller, 'loginType', 'login');
        self::setProtected($controller, 'view', $view);
        self::setProtected($controller, 'settings', ['pages' => '', 'recursive' => 0]);
        self::setProtected($controller, 'configuration', RedirectConfiguration::fromSettings([]));

        return $controller;
    }

    private function bindHeadlessMode(int $mode): void
    {
        $request = (new \TYPO3\CMS\Core\Http\ServerRequest('https://website.local/'))
            ->withAttribute('headless', new Headless($mode));

        $container = new Container();
        $container->set(HeadlessModeInterface::class, (new HeadlessMode())->withRequest($request));
        GeneralUtility::setContainer($container);
    }

    private function buildController(EventDispatcherInterface $eventDispatcher): LoginController
    {
        $controller = new LoginController(
            $this->createMock(RedirectHandler::class),
            $this->createMock(Context::class),
            $this->createMock(PageRepository::class),
        );

        $httpFactory = new HttpFactory();
        $controller->injectResponseFactory($httpFactory);
        $controller->injectStreamFactory($httpFactory);
        $controller->injectEventDispatcher($eventDispatcher);

        $request = new Request(
            (new \TYPO3\CMS\Core\Http\ServerRequest('https://website.local/'))
                ->withAttribute('headless', new Headless(HeadlessModeInterface::FULL))
                ->withAttribute('extbase', new ExtbaseRequestParameters())
        );
        self::setProtected($controller, 'request', $request);
        self::setProtected($controller, 'loginType', 'login');

        return $controller;
    }

    private static function callHandleRedirect(LoginController $controller, string $status = 'success'): ?ResponseInterface
    {
        $method = new ReflectionMethod($controller, 'handleRedirect');
        return $method->invoke($controller, $status);
    }

    private static function setProtected(object $object, string $property, mixed $value): void
    {
        (new ReflectionProperty($object, $property))->setValue($object, $value);
    }
}
