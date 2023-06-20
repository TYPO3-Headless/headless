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
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;

final class AfterLinkIsGeneratedListener
{
    public function __construct(
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly LinkService $linkService
    ) {
    }

    public function __invoke(AfterLinkIsGeneratedEvent $event): void
    {
        $result = $event->getLinkResult();

        if ($result->getType() !== 'page') {
            return;
        }

        $pageId = $result->getLinkConfiguration()['parameter'] ?? 0;

        if (isset($result->getLinkConfiguration()['parameter.'])) {
            $pageId = (int)($this->linkService->resolve($event->getContentObjectRenderer()->parameters['href'] ?? '')['pageuid'] ?? 0);
        }

        if ($pageId) {
            $href = $this->urlUtility->getFrontendUrlForPage(
                $event->getLinkResult()->getUrl(),
                (int)$pageId
            );
        } else {
            $site = $event->getContentObjectRenderer()->getRequest()->getAttribute('site');

            if (!$site instanceof NullSite) {
                $href = $this->urlUtility->getFrontendUrlWithSite($event->getLinkResult()->getUrl(), $site);
            }
        }

        if (isset($href)) {
            $result = $result->withAttribute('href', $href);
            $event->setLinkResult($result);
        }
    }
}
