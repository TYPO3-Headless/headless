<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\FrontendLogin\Event\BeforeRedirectEvent;
use TYPO3\CMS\FrontendLogin\Event\LoginErrorOccurredEvent;
use TYPO3\CMS\FrontendLogin\Event\LogoutConfirmedEvent;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

use function implode;
use function json_encode;

/**
 * @codeCoverageIgnore
 */
class LoginController extends \TYPO3\CMS\FrontendLogin\Controller\LoginController
{
    /**
     * Show login form
     */
    public function loginAction(): ResponseInterface
    {
        if (!$this->isHeadlessEnabled()) {
            return parent::loginAction();
        }

        $status = 'success';

        if ($this->isLogoutSuccessful()) {
            $this->eventDispatcher->dispatch(new LogoutConfirmedEvent($this, $this->view));
        } elseif ($this->hasLoginErrorOccurred()) {
            $status = 'failure';
            $this->eventDispatcher->dispatch(new LoginErrorOccurredEvent());
        }

        if (($forwardResponse = $this->handleLoginForwards()) !== null) {
            return $forwardResponse;
        }
        if (($redirectResponse = $this->handleRedirect($status)) !== null) {
            return $redirectResponse;
        }

        $this->eventDispatcher->dispatch(new ModifyLoginFormViewEvent($this->view));

        $storagePageIds = ($GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'] ?? false)
            ? $this->pageRepository->getPageIdsRecursive(GeneralUtility::intExplode(',', (string)($this->settings['pages'] ?? ''), true), (int)($this->settings['recursive'] ?? 0))
            : [];

        $this->view->assignMultiple(
            [
                'status' => $status,
                'storagePid' => implode(',', $storagePageIds),
                'messageKey' => $this->getStatusMessageKey(),
                'permaloginStatus' => $this->getPermaloginStatus(),
                'redirectURL' => $this->redirectHandler->getLoginFormRedirectUrl($this->request, $this->configuration, $this->isRedirectDisabled()),
                'redirectReferrer' => $this->request->hasArgument('redirectReferrer') ? (string)$this->request->getArgument('redirectReferrer') : '',
                'referer' => $this->redirectHandler->getReferrerForLoginForm($this->request, $this->settings),
                'noRedirect' => $this->isRedirectDisabled(),
                'requestToken' => RequestToken::create('core/user-auth/fe')
                    ->withMergedParams(['pid' => implode(',', $storagePageIds)]),
            ]
        );

        return $this->jsonResponse();
    }

    protected function handleRedirect(string $status = 'success'): ?ResponseInterface
    {
        if ($this->redirectUrl === '') {
            return null;
        }

        if (!$this->isHeadlessEnabled()) {
            return parent::handleRedirect();
        }

        $event = $this->eventDispatcher->dispatch(new BeforeRedirectEvent(
            $this->loginType,
            $this->redirectUrl,
            $this->request
        ));

        $data = [
            'redirectUrl' => $event->getRedirectUrl(),
            'statusCode' => 303,
            'status' => $status,
        ];

        return $this->responseFactory->createResponse()->withHeader(
            'Content-Type',
            'application/json; charset=utf-8'
        )
            ->withBody($this->streamFactory->createStream(json_encode($data)));
    }

    private function isHeadlessEnabled(): bool
    {
        $typoScriptSetup = $this->request->getAttribute('frontend.typoscript')->getSetupArray();

        return (bool)($typoScriptSetup['plugin.']['tx_headless.']['staticTemplate'] ?? false);
    }
}
