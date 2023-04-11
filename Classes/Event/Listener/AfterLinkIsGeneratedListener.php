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

use function is_numeric;

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

        $params = $this->linkService->resolve($result->getLinkText());

        if (!isset($params['pageuid'])) {
            return;
        }

        if (is_numeric($params['pageuid'])) {
            $href = $this->urlUtility->getFrontendUrlForPage(
                $event->getLinkResult()->getUrl(),
                (int)$params['pageuid']
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
