<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Event\Listener;

use FriendsOfTYPO3\Headless\Event\Listener\LoginConfirmedEventListener;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\FrontendLogin\Controller\LoginController;
use TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent;

class LoginConfirmedEventListenerTest extends HeadlessUnitTestCase
{
    public function testAssignsSuccessStatusToView(): void
    {
        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::once())->method('assign')->with('status', 'success');

        $event = new LoginConfirmedEvent(
            $this->createMock(LoginController::class),
            $view,
            new ServerRequest()
        );

        (new LoginConfirmedEventListener())($event);
    }
}
