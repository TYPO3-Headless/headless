<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent;

/**
 * @codeCoverageIgnore
 */
final class LoginConfirmedEventListener
{
    public function __invoke(LoginConfirmedEvent $event): void
    {
        $event->getView()->assign('status', 'success');
    }
}
