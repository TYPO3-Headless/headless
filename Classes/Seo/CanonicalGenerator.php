<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Seo;

use FriendsOfTYPO3\Headless\Utility\HeadlessMode;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Domain\Page;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Seo\Event\ModifyUrlForCanonicalTagEvent;

use function htmlspecialchars;
use function json_encode;

/**
 * Overridden core version with headless implementation
 *
 * @codeCoverageIgnore
 */
class CanonicalGenerator extends \TYPO3\CMS\Seo\Canonical\CanonicalGenerator
{
    protected TypoScriptFrontendController $typoScriptFrontendController;
    protected PageRepository $pageRepository;
    protected EventDispatcherInterface $eventDispatcher;

    public function handle(array &$params): string
    {
        if ($this->typoScriptFrontendController->config['config']['disableCanonical'] ?? false) {
            return '';
        }

        $event = new ModifyUrlForCanonicalTagEvent('', $params['request'], new Page($params['page']));
        $event = $this->eventDispatcher->dispatch($event);
        $href = $event->getUrl();

        if (empty($href) && (int)$this->typoScriptFrontendController->page['no_index'] === 1) {
            return '';
        }

        if (empty($href)) {
            // 1) Check if page has canonical URL set
            $href = $this->checkForCanonicalLink();
        }
        if (empty($href)) {
            // 2) Check if page show content from other page
            $href = $this->checkContentFromPid();
        }
        if (empty($href)) {
            // 3) Fallback, create canonical URL
            $href = $this->checkDefaultCanonical();
        }

        if (!empty($href)) {
            if (GeneralUtility::makeInstance(HeadlessMode::class)->withRequest($params['request'])->isEnabled()) {
                $canonical = [
                    'href' => htmlspecialchars($href),
                    'rel' => 'canonical',
                ];

                $params['_seoLinks'][] = $canonical;
                $canonical = json_encode($canonical);
            } else {
                $canonical = '<link ' . GeneralUtility::implodeAttributes([
                    'rel' => 'canonical',
                    'href' => $href,
                ], true) . '/>' . LF;
                $this->typoScriptFrontendController->additionalHeaderData[] = $canonical;
            }

            return $canonical;
        }
        return '';
    }
}
