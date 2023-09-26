<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

use function json_decode;

use const JSON_THROW_ON_ERROR;

class AfterCacheableContentIsGeneratedListener
{
    public function __construct(private readonly JsonEncoder $encoder)
    {
    }

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event)
    {
        try {
            $content = json_decode($event->getController()->content, true, 512, JSON_THROW_ON_ERROR);

            if (($content['meta']['title'] ?? null) === null) {
                return;
            }

            $content['meta']['title'] = $event->getController()->generatePageTitle();

            $event->getController()->content = $this->encoder->encode($content);
        } catch (\Throwable) {
            return;
        }
    }
}
