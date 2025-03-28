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
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AfterPagePreviewUriGeneratedListener
{
    public function __construct(private HeadlessFrontendUrlInterface $urlUtility, private readonly SiteFinder $siteFinder) {}

    public function __invoke(AfterPagePreviewUriGeneratedEvent $event): void
    {
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER']->workspace !== 0) {
            return;
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($event->getPageId());
            $languageUid = $event->getLanguageId();
            $language = $languageUid === -1 ? null : $site->getLanguageById($languageUid);

            $headlessMode = GeneralUtility::makeInstance(HeadlessModeInterface::class);
            $headlessMode = $headlessMode->withRequest($GLOBALS['TYPO3_REQUEST']);
            $request = $headlessMode->overrideBackendRequestBySite($site, $language);

            $urlUtility = $this->urlUtility->withRequest($request);
            $event->setPreviewUri(new Uri($urlUtility->getFrontendUrlWithSite($event->getPreviewUri()->__toString(), $site)));
        } catch (SiteNotFoundException) {
        }
    }
}
