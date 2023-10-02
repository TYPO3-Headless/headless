<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Core\Http\Uri;

final class AfterPagePreviewUriGeneratedListener
{
    public function __construct(private readonly HeadlessFrontendUrlInterface $urlUtility) {}

    public function __invoke(AfterPagePreviewUriGeneratedEvent $event): void
    {
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER']->workspace !== 0) {
            return;
        }

        $event->setPreviewUri(new Uri($this->urlUtility->getFrontendUrlForPage($event->getPreviewUri()->__toString(), $event->getPageId())));
    }
}
