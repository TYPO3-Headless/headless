<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\AfterCacheableContentIsGeneratedListener;
use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function json_encode;

class AfterCacheableContentIsGeneratedListenerTest extends UnitTestCase
{
    use ProphecyTrait;

    public function testNotModifiedWithInvalidJsonContent(): void
    {
        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder());

        $request = $this->prophesize(ServerRequestInterface::class);
        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = '';

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame('', $event->getController()->content);
    }

    public function testNotModifiedWhileValidJson(): void
    {
        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder());

        $content = json_encode(['someCustomPageWithoutMeta' => ['title' => 'test before event']]);

        $request = $this->prophesize(ServerRequestInterface::class);
        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = $content;
        $controller->generatePageTitle()->willReturn('Modified title via PageTitleManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame($content, $event->getController()->content);
    }

    public function testModifiedPageTitle(): void
    {
        $listener = new AfterCacheableContentIsGeneratedListener(new JsonEncoder());

        $request = $this->prophesize(ServerRequestInterface::class);
        $controller = $this->prophesize(TypoScriptFrontendController::class);
        $controller->content = json_encode(['meta' => ['title' => 'test before event']]);
        $controller->generatePageTitle()->willReturn('Modified title via PageTitleProviderManager');

        $event = new AfterCacheableContentIsGeneratedEvent($request->reveal(), $controller->reveal(), 'abc', false);

        $listener($event);

        self::assertSame(json_encode(['meta' => ['title' => 'Modified title via PageTitleProviderManager']]), $event->getController()->content);
    }
}
