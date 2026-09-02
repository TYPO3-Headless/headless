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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

use function json_decode;

use const JSON_THROW_ON_ERROR;

#[AsEventListener(identifier: 'headless/AfterCacheableContentIsGenerated')]
class AfterCacheableContentIsGeneratedListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly JsonEncoderInterface $encoder,
        protected readonly MetaHandlerInterface $metaHandler,
        protected readonly HeadlessUserInt $headlessUserInt,
        protected readonly HeadlessModeInterface $headlessMode,
    ) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        try {
            if (!$this->headlessMode->isEnabledFor($event->getRequest())) {
                return;
            }

            if ($this->headlessUserInt->hasNonCacheableContent($event->getContent())) {
                // dynamic content on the page → MetaHandler runs later in the middleware
                return;
            }

            $content = json_decode($event->getContent(), true, 512, JSON_THROW_ON_ERROR);

            if (($content['seo']['title'] ?? null) === null) {
                return;
            }

            $content = $this->metaHandler->process($event->getRequest(), $content);

            $event->setContent($this->encoder->encode($content));
        } catch (Throwable $e) {
            $this->logger?->warning(
                'Failed to post-process cacheable content for headless SEO meta tags',
                ['exception' => $e]
            );
        }
    }
}
