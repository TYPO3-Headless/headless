<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use CIMEOS\CimUsers\Domain\Repository\UserRepository;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use FriendsOfTYPO3\Headless\Utility\HeadlessMode;

class PasswordRecoveryController extends TYPO3\CMS\FrontendLogin\Controller\PasswordRecoveryController
{

    /**
     * Shows the recovery form. If $userIdentifier is set, an email will be sent, if the corresponding user exists and
     * has a valid email address set.
     */
    public function recoveryAction(string $userIdentifier = null): ResponseInterface
    {
        if (!$this->isHeadlessEnabled()) {
            return parent::loginAction();
        }

        $userIdentifier = $userIdentifier ?? $this->request->getParsedBody()['user'] ?? null;

        if (empty($userIdentifier)) {
            $this->jsonResponse();
        }

        $storagePageIds = ($GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'] ?? false)
            ? $this->pageRepository->getPageIdsRecursive(GeneralUtility::intExplode(',', (string)($this->settings['pages'] ?? ''), true), (int)($this->settings['recursive'] ?? 0))
            : [];

        $userData = $this->userRepository->findUserByUsernameOrEmailOnPages($userIdentifier, $storagePageIds);

        if ($userData && GeneralUtility::validEmail($userData['email'])) {
            $hash = $this->recoveryConfiguration->getForgotHash();
            $this->userRepository->updateForgotHashForUserByUid($userData['uid'], GeneralUtility::hmac($hash));
            $this->recoveryService->sendRecoveryEmail($this->request, $userData, $hash);
        }

        if ($this->exposeNoneExistentUser($userData)) {
            $this->addFlashMessage(
                $this->getTranslation('forgot_reset_message_error'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
        } else {
            $this->addFlashMessage($this->getTranslation('forgot_reset_message_emailSent'));
        }

        return $this->redirect('login', 'Login', 'felogin');
    }

    public function showChangePasswordAction(string $hash = ''): \Psr\Http\Message\ResponseInterface
    {
        if (!$this->isHeadlessEnabled()) {
            return parent::showChangePasswordAction($hash);
        }

        // Validate the lifetime of the hash
        if (($response = $this->validateIfHashHasExpired()) instanceof ResponseInterface) {
            return $this->jsonResponse();
        }

        $passwordRequirements = null;
        if ($this->features->isFeatureEnabled('security.usePasswordPolicyForFrontendUsers')) {
            $passwordRequirements = $this->getPasswordPolicyValidator()->getRequirements();
        }

        $this->view->assignMultiple([
            'form' => $this->buildShowForm($hash),
            'hash' => $hash,
            'passwordRequirements' => $passwordRequirements,
        ]);

        return $this->jsonResponse();
    }

    protected function buildShowForm($hash)
    {
        return [
            "id" => "felogin-recovery",
            'method' => 'POST',
            "action" => $this->uriBuilder
                ->reset()
                ->setTargetPageUid($GLOBALS['TSFE']->id)
                ->uriFor('changePassword'),
            "api" => [
                "label" => 'Forget password'
            ],
            "elements" => [
                [
                    "identifier" => "newPass",
                    "type" => "Password",
                    "label" => "Nouveau mot de passe",
                    "name" => 'tx_felogin_login[newPass]',
                    "value" => '',
                    "defaultValue" => '',
                    "validators" => [
                        [
                            "identifier" => "required",
                            "message" => "Le titre est obligatoire."
                        ]
                    ],
                ],
                [
                    "identifier" => "newPassRepeat",
                    "type" => "Password",
                    "label" => "Répeter le nouveau mot de passe",
                    "name" => 'tx_felogin_login[newPassRepeat]',
                    "value" => '',
                    "defaultValue" => '',
                    "validators" => [
                        [
                            "identifier" => "required",
                            "message" => "Le titre est obligatoire."
                        ]
                    ],
                ],
                [
                    "identifier" => "hash",
                    "type" => "Hidden",
                    "label" => "Hash",
                    "name" => 'tx_felogin_login[hash]',
                    "value" => $hash,
                    "defaultValue" => '',
                ],

            ]
        ];
    }


    public function changePasswordAction(string $newPass, string $hash): \Psr\Http\Message\ResponseInterface
    {
        if (!$this->isHeadlessEnabled()) {
            return parent::changePasswordAction($newPass, $hash);
        }

        $hash = !empty($hash) ? $hash : null ?? $this->request->getParsedBody()['hash'] ?? null;

        if (($response = $this->validateHashAndPasswords()) instanceof ResponseInterface) {
            return $this->jsonResponse();
        }

        $hashedPassword = GeneralUtility::makeInstance(PasswordHashFactory::class)
            ->getDefaultHashInstance('FE')
            ->getHashedPassword($newPass);

        if (($hashedPassword = $this->notifyPasswordChange(
                $newPass,
                $hashedPassword,
                $hash
            )) instanceof ForwardResponse) {

            return $this->jsonResponse();
        }

        $user = $this->userRepository->findOneByForgotPasswordHash(GeneralUtility::hmac($hash));
        $this->userRepository->updatePasswordAndInvalidateHash(GeneralUtility::hmac($hash), $hashedPassword);
        $this->invalidateUserSessions($user['uid']);

        $this->addFlashMessage($this->getTranslation('change_password_done_message'));

        return $this->redirect('login', 'Login', 'felogin');
    }

    private function isHeadlessEnabled(): bool
    {
        return GeneralUtility::makeInstance(HeadlessMode::class)->withRequest($GLOBALS['TYPO3_REQUEST'])->isEnabled();
    }
}
