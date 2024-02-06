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
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;

use function is_numeric;
use function is_string;
use function str_starts_with;

final class AfterLinkIsGeneratedListener
{
    public function __construct(
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly LinkService $linkService
    ) {}

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

        $urlUtility = $this->urlUtility->withRequest($event->getContentObjectRenderer()->getRequest());

        if (is_numeric($pageId) && ((int)$pageId) > 0) {
            $href = $urlUtility->getFrontendUrlForPage(
                $event->getLinkResult()->getUrl(),
                (int)$pageId
            );
        } else {
            /**
             * @var Site $site
             */
            $site = $event->getContentObjectRenderer()->getRequest()->getAttribute('site');
            $key = 'frontendBase';

            $sitemapConfig = $site->getConfiguration()['settings']['headless']['sitemap'] ?? [];

            if (is_string($pageId) && str_starts_with($pageId, 't3://page?uid=current&type=' . ($sitemapConfig['type'] ?? '1533906435'))) {
                $key = $sitemapConfig['key'] ?? 'frontendApiProxy';
            }

            if (!$site instanceof NullSite) {
                $href = $urlUtility->getFrontendUrlWithSite($event->getLinkResult()->getUrl(), $site, $key);
            }
        }

        if (isset($href)) {
            $result = $result->withAttribute('href', $href);
            $event->setLinkResult($result);
        }
    }
}
