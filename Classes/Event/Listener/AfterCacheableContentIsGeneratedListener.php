<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Seo\MetaHandlerInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Throwable;

use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

use function json_decode;

use const JSON_THROW_ON_ERROR;

class AfterCacheableContentIsGeneratedListener
{
    public function __construct(
        private readonly JsonEncoderInterface $encoder,
        private readonly MetaHandlerInterface $metaHandler,
        private readonly HeadlessUserInt $headlessUserInt,
        private readonly HeadlessModeInterface $headlessMode,
    ) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event)
    {
        try {
            if (!$this->headlessMode->withRequest($event->getRequest())->isEnabled()) {
                return;
            }

            if ($this->headlessUserInt->hasNonCacheableContent($event->getContent())) {
                // we have dynamic content on page, we fire MetaHandler later on middleware
                return;
            }

            $content = json_decode($event->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (($content['seo']['title'] ?? null) === null) {
                return;
            }

            $content = $this->metaHandler->process($event->getRequest(), $content);

            $event->setContent($this->encoder->encode($content));
        } catch (Throwable $e) {
            return;
        }
    }
}
