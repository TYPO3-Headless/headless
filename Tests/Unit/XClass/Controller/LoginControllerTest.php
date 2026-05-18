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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\FrontendLogin\Event\BeforeRedirectEvent;
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
