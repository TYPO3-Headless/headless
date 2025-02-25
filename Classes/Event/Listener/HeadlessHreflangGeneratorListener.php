<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Event\ModifyHrefLangTagsEvent;

/**
 * @codeCoverageIgnore
 */
class HeadlessHreflangGeneratorListener
{
    public function __invoke(ModifyHrefLangTagsEvent $event): void
    {
        $hrefLangs = [];
        $urlUtility = GeneralUtility::makeInstance(UrlUtility::class)->withRequest($event->getRequest());

        foreach ($event->getHrefLangs() as $lang => $hrefLang) {
            $hrefLangs[$lang] = $urlUtility->getFrontendUrlWithSite($hrefLang, $event->getRequest()->getAttribute('site'));
        }

        $event->setHrefLangs($hrefLangs);
    }
}
