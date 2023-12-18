<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Event\Listener;

use FriendsOfTYPO3\Headless\Utility\Headless;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\SiteFinder;

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
            $mode = (int)($site->getConfiguration()['headless'] ?? HeadlessMode::NONE);

            if ($mode === HeadlessMode::MIXED) {
                // in BE context we override it to force generate url
                $mode = HeadlessMode::FULL;
            }

            $request = $GLOBALS['TYPO3_REQUEST'];
            $request = $request->withAttribute('language', $site->getLanguageById($event->getLanguageId()));
            $request = $request->withAttribute('headless', new Headless($mode));

            $urlUtility = $this->urlUtility->withRequest($request);
            $event->setPreviewUri(new Uri($urlUtility->getFrontendUrlWithSite($event->getPreviewUri()->__toString(), $site)));
        } catch (SiteNotFoundException) {
        }
    }
}
