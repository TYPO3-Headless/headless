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
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\FrontendLogin\Event\BeforeRedirectEvent;
use TYPO3\CMS\FrontendLogin\Event\LoginConfirmedEvent;
use TYPO3\CMS\FrontendLogin\Event\LoginErrorOccurredEvent;
use TYPO3\CMS\FrontendLogin\Event\LogoutConfirmedEvent;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

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

        if ($this->redirectUrl !== '') {
            $event = $this->eventDispatcher->dispatch(new BeforeRedirectEvent($this->loginType, $this->redirectUrl, $this->request));
            $data = [
                'redirectUrl' => $event->getRedirectUrl(),
                'statusCode' => 303
            ];
            return $this->responseFactory->createResponse()->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream(json_encode($data)));
        }
        $this->eventDispatcher->dispatch(new ModifyLoginFormViewEvent($this->view));

        $this->view->assignMultiple(
            [
                'status' => $status,
                'cookieWarning' => $this->showCookieWarning,
                'messageKey' => $this->getStatusMessageKey(),
                'storagePid' => $this->shallEnforceLoginSigning() ? $this->getSignedStorageFolders() : implode(',', $this->getStorageFolders()),
                'permaloginStatus' => $this->getPermaloginStatus(),
                'redirectURL' => $this->redirectHandler->getLoginFormRedirectUrl($this->configuration, $this->isRedirectDisabled()),
                'redirectReferrer' => $this->request->hasArgument('redirectReferrer') ? (string)$this->request->getArgument('redirectReferrer') : '',
                'referer' => $this->redirectHandler->getReferrerForLoginForm($this->request, $this->settings),
                'noRedirect' => $this->isRedirectDisabled(),
            ]
        );

        return $this->jsonResponse();
    }

    /**
     * User overview for logged in users
     *
     * @param bool $showLoginMessage
     * @return ResponseInterface
     */
    public function overviewAction(bool $showLoginMessage = false): ResponseInterface
    {
        if (!$this->isHeadlessEnabled()) {
            return parent::overviewAction($showLoginMessage);
        }

        $status = 'success';

        if (!$this->userAspect->isLoggedIn()) {
            return new ForwardResponse('login');
        }

        $this->eventDispatcher->dispatch(new LoginConfirmedEvent($this, $this->view));

        if ($this->redirectUrl !== '') {
            $event = $this->eventDispatcher->dispatch(new BeforeRedirectEvent($this->loginType, $this->redirectUrl, $this->request));
            $data = [
                'redirectUrl' => $event->getRedirectUrl(),
                'statusCode' => 303,
                'status' => 'success'
            ];
            return $this->responseFactory->createResponse()->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($this->streamFactory->createStream(json_encode($data)));
        }

        $this->view->assignMultiple(
            [
                'status' => $status,
                'cookieWarning' => $this->showCookieWarning,
                'user' => $this->userService->getFeUserData(),
                'showLoginMessage' => $showLoginMessage,
            ]
        );

        return $this->htmlResponse();
    }

    private function isHeadlessEnabled(): bool
    {
        $typoScriptSetup = $GLOBALS['TSFE'] instanceof TypoScriptFrontendController ? $GLOBALS['TSFE']->tmpl->setup : [];

        return (bool)($typoScriptSetup['plugin.']['tx_headless.']['staticTemplate'] ?? false);
    }
}
