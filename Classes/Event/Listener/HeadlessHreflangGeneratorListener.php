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
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;

class HeadlessHreflangGeneratorListener
{
    public function __construct(
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly HeadlessModeInterface $headlessMode,
    ) {}

    public function __invoke(ModifyHrefLangTagsEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->headlessMode->isEnabledFor($request)) {
            return;
        }

        $hrefLangs = $event->getHrefLangs();
        if ($hrefLangs === []) {
            return;
        }

        $urlUtility = $this->urlUtility->withRequest($request);
        $site = $request->getAttribute('site');
        $data = [];

        foreach ($hrefLangs as $lang => $href) {
            $data[$lang] = $urlUtility->getFrontendUrlWithSite($href, $site);
        }

        $event->setHrefLangs($data);
    }
}
