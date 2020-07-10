<?php

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Form\Domain\Runtime\FormState;

/**
 * The frontend controller
 *
 * Scope: frontend
 * @internal
 */
class FormFrontendController extends \TYPO3\CMS\Form\Controller\FormFrontendController
{

    /**
     * Take the form which should be rendered from the plugin settings
     * and overlay the formDefinition with additional data from
     * flexform and typoscript settings.
     * This method is used directly to display the first page from the
     * formDefinition because its cached.
     *
     * @internal
     */
    public function renderAction(): void
    {
        $formDefinition = [];
        if (!empty($this->settings['persistenceIdentifier'])) {
            $formDefinition = $this->formPersistenceManager->load($this->settings['persistenceIdentifier']);
            $formDefinition['persistenceIdentifier'] = $this->settings['persistenceIdentifier'];
            $formDefinition = $this->overrideByTypoScriptSettings($formDefinition);
            $formDefinition = $this->overrideByFlexFormSettings($formDefinition);
            $formDefinition = ArrayUtility::setValueByPath($formDefinition, 'renderingOptions._originalIdentifier', $formDefinition['identifier'], '.');
            $formDefinition['identifier'] .= '-' . ($this->configurationManager->getContentObject() !== null ? $this->configurationManager->getContentObject()->data['uid']: 0);
        }

        $formState = $this->getFormState();

        // Temporary hack form form processing work, but now form steps wont work
        $formState->setLastDisplayedPageIndex(0);

        $hashService = $this->getHashService();
        $formFields = $formDefinition['renderables'][0]['renderables'];
        $stateHash = $hashService->appendHmac(base64_encode(serialize($formState)));

        $hidden['__state'] = $stateHash;

        $formFieldsNames = [];
        foreach ($formFields as $field) {
            $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][' . $field['identifier'] . ']';
        }
        // Value of __currentPage should be get from form state
        $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][__currentPage]';
        $requestHash = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken($formFieldsNames, 'tx_form_formframework');
        $hidden['__trustedProperties'] = $requestHash;

        $this->view->assignMultiple(['formConfiguration' => $formDefinition, 'hidden' => $hidden]);
    }

    /**
     * @return HashService
     */
    protected function getHashService(): HashService
    {
        return GeneralUtility::makeInstance(HashService::class);
    }

    /**
     * @return FormState
     */
    protected function getFormState(): FormState
    {
        return GeneralUtility::makeInstance(FormState::class);
    }
}
