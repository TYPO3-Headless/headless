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
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\Event\AfterPageUriGeneratedEvent;

final class AfterPageUriGeneratedListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly HeadlessFrontendUrlInterface $urlUtility,
        private readonly HeadlessModeInterface $headlessMode,
    ) {}

    public function __invoke(AfterPageUriGeneratedEvent $event): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return;
        }

        if (!ApplicationType::fromRequest($request)->isBackend()) {
            return;
        }

        $site = $event->getSite();
        $headlessMode = $this->headlessMode->withRequest($request);
        $boundRequest = $headlessMode->overrideBackendRequestBySite($site, $event->getLanguage());

        if (!$this->headlessMode->isEnabledFor($boundRequest)) {
            return;
        }

        $originalUri = (string)$event->getUri();
        $rewritten = $this->urlUtility
            ->withRequest($boundRequest)
            ->getFrontendUrlWithSite($originalUri, $site);

        if ($rewritten === $originalUri) {
            return;
        }

        try {
            $event->setUri(new Uri($rewritten));
        } catch (InvalidArgumentException $e) {
            $this->logger?->warning(
                'Headless: rewritten preview URI was invalid; keeping the original backend URI',
                ['originalUri' => $originalUri, 'rewritten' => $rewritten, 'exception' => $e]
            );
        }
    }
}
