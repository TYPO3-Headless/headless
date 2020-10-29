<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use FriendsOfTYPO3\Headless\Form\CustomOptionsInterface;
use FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface;
use FriendsOfTYPO3\Headless\Form\Decorator\FormDefinitionDecorator;
use FriendsOfTYPO3\Headless\Form\Translator;
use FriendsOfTYPO3\Headless\XClass\FormRuntime;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;

/**
 * The frontend controller
 *
 * Scope: frontend
 * @internal
 */
class FormFrontendController extends \TYPO3\CMS\Form\Controller\FormFrontendController
{
    private $jsonFormTranslator;

    public function __construct()
    {
        $this->hashService = GeneralUtility::makeInstance(HashService::class);
        $this->jsonFormTranslator = GeneralUtility::makeInstance(Translator::class);
    }

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
            $formDefinition = ArrayUtility::setValueByPath(
                $formDefinition,
                'renderingOptions._originalIdentifier',
                $formDefinition['identifier'],
                '.'
            );

            $formId = ($this->configurationManager->getContentObject() !== null ?
                ($this->configurationManager->getContentObject()->data['uid'] ?? 0) : 0);

            $formDefinition['identifier'] .= '-' . $formId;
        }

        $i18n = [];

        if (isset($formDefinition['i18n'])) {
            $i18n = $formDefinition['i18n'] ?? [];
            unset($formDefinition['i18n']);
        }

        $decoratorClass = null;

        if (isset($formDefinition['renderingOptions']['formDecorator'])) {
            $decoratorClass = $formDefinition['renderingOptions']['formDecorator'];
            unset($formDefinition['renderingOptions']['formDecorator']);
        }

        if (empty($decoratorClass)) {
            $decoratorClass = FormDefinitionDecorator::class;
        }

        $prototypeName = $formDefinition['prototypeName'] ?? 'standard';
        $factory = $this->objectManager->get(ArrayFormFactory::class);
        $formDefinitionObj = $factory->build($formDefinition, $prototypeName);
        $response = $this->controllerContext->getResponse() ?? $this->objectManager->get(Response::class);
        /**
          * @var FormRuntime $formRuntime
         */
        $formRuntime = $formDefinitionObj->bind($this->controllerContext->getRequest(), $response);
        $formState = $formRuntime->getFormState();
        $finisherResponse = $formRuntime->run();

        $elements = $formRuntime->getFormDefinition()->getElements();
        $honeyPot = null;

        if (isset($formRuntime->getFormDefinition()->getRenderingOptions()['honeypot']['enable']) &&
            $formRuntime->getFormDefinition()->getRenderingOptions()['honeypot']['enable'] === true) {
            $honeyPot = array_pop($elements);
        }

        $stateHash = $this->hashService->appendHmac(base64_encode(serialize($formState)));
        $formFieldsNames = [];

        $currentPageIndex = $formRuntime->getCurrentPage() ? $formRuntime->getCurrentPage()->getIndex() : 0;
        $currentPageId = $currentPageIndex + 1;
        $formFields = $formDefinition['renderables'][$currentPageIndex]['renderables'];

        // provides support for custom options providers (dynamic selects/radio/checkboxes)
        foreach ($formFields as &$field) {
            if (!empty($field['properties']['customOptions'])) {
                $customOptions = GeneralUtility::makeInstance($field['properties']['customOptions']);

                if ($customOptions instanceof CustomOptionsInterface) {
                    $field['properties']['options'] = $customOptions->get();
                }

                unset($field['properties']['customOptions']);
            }

            // phpcs:ignore Generic.Files.LineLength
            $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][' . $field['identifier'] . ']';
        }

        unset($field);

        if ($honeyPot) {
            $formFields[] = [
                'properties' => $honeyPot->getProperties(),
                'type' => $honeyPot->getType(),
                'identifier' => $honeyPot->getIdentifier(),
            ];
            $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][' . $honeyPot->getIdentifier() . ']';
        }

        $formFields[] = [
            'properties' => [],
            'type' => 'Hidden',
            'identifier' => '__currentPage',
            'defaultValue' => $currentPageId,
        ];

        $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][__currentPage]';
        $requestHash = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken(
            $formFieldsNames,
            'tx_form_formframework'
        );

        $formFields[] = [
            'properties' => [],
            'type' => 'Hidden',
            'identifier' => '__trustedProperties',
            'defaultValue' => $requestHash,
        ];

        $formFields[] = [
            'properties' => [],
            'type' => 'Hidden',
            'identifier' => '__state',
            'defaultValue' => $stateHash,
        ];

        $formDefinition['renderables'][$currentPageIndex]['renderables'] = $formFields;

        $formDefinition['i18n'] = $i18n;
        $formDefinition = $this->jsonFormTranslator->translate(
            $formDefinition,
            $formRuntime->getFormDefinition()->getRenderingOptions()
        );

        $formStatus['status'] = null;
        $formStatus['errors'] = null;
        $formStatus['actionAfterSuccess'] = $finisherResponse ? json_decode($finisherResponse) : null;
        $formStatus['page'] = [
            'current' => $currentPageIndex,
            'nextPage' => $this->getNextPage($formRuntime),
            'pages' => \count($formRuntime->getPages()),
        ];

        if ($formState &&
            $formState->isFormSubmitted() &&
            $this->getControllerContext()->getRequest()->getMethod() === 'POST') {
            $result = $formRuntime->getRequest()->getOriginalRequestMappingResults();
            /**
             * @var array<string,\TYPO3\CMS\Extbase\Error\Error[]>
             */
            $errors = $result->getFlattenedErrors();
            $formStatus['status'] = $result->hasErrors() ? 'failure' : 'success';
            $formStatus['errors'] = $this->prepareErrors($errors, $formDefinition['identifier']);
        }

        /**
         * @var DefinitionDecoratorInterface $definitionDecorator
         */
        $definitionDecorator = GeneralUtility::makeInstance($decoratorClass, $formStatus);

        if (!($definitionDecorator instanceof DefinitionDecoratorInterface)) {
            $definitionDecorator = GeneralUtility::makeInstance(FormDefinitionDecorator::class, $formStatus);
        }

        $this->view->assign('formConfiguration', $definitionDecorator($formDefinition, $currentPageIndex));
    }

    /**
     * @param array<string,\TYPO3\CMS\Extbase\Error\Error[]> $errors
     * @param string $formIdentifier
     * @return array<string, string>|null
     */
    private function prepareErrors(array $errors, string $formIdentifier): ?array
    {
        $parsedErrors = [];

        foreach ($errors as $key => $errorObj) {
            $parsedErrors[str_replace($formIdentifier . '.', '', $key)] = $errorObj[0]->getMessage();
        }

        return \count($parsedErrors) ? $parsedErrors : null;
    }

    private function getNextPage(\TYPO3\CMS\Form\Domain\Runtime\FormRuntime $formRuntime): ?int
    {
        if ($formRuntime->getCurrentPage() && $formRuntime->getNextEnabledPage()) {
            return $formRuntime->getNextEnabledPage()->getIndex();
        }

        return null;
    }
}
