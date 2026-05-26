<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use FriendsOfTYPO3\Headless\Form\CustomOptionsInterface;
use FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface;
use FriendsOfTYPO3\Headless\Form\Decorator\FormDefinitionDecorator;
use FriendsOfTYPO3\Headless\Form\Translator;
use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use FriendsOfTYPO3\Headless\XClass\FormRuntime;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function array_merge;
use function array_pop;
use function base64_encode;
use function class_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function serialize;
use function str_contains;
use function str_replace;

/**
 * Overridden form implementation with headless flavor
 *
 * @internal
 * @codeCoverageIgnore
 */
class FormFrontendController extends \TYPO3\CMS\Form\Controller\FormFrontendController
{
    private ?HeadlessModeInterface $headlessMode = null;
    private ?Translator $translator = null;

    private function getHeadlessMode(): HeadlessModeInterface
    {
        return $this->headlessMode ??= GeneralUtility::makeInstance(HeadlessModeInterface::class);
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
    public function renderAction(): ResponseInterface
    {
        if (!$this->getHeadlessMode()->withRequest($this->request)->isEnabled()) {
            return parent::renderAction();
        }

        $formDefinition = [];
        if (!empty($this->settings['persistenceIdentifier'])) {
            $typoScriptSettings = $this->configurationManager->getConfiguration(
                ExtbaseConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'form'
            );

            $formDefinition = $this->formPersistenceManager->load(
                $this->settings['persistenceIdentifier'],
                $typoScriptSettings,
                $this->request
            );
            $formDefinition['persistenceIdentifier'] = $this->settings['persistenceIdentifier'];
            $formDefinition = $this->overrideByFlexFormSettings($formDefinition);
            $formDefinition = ArrayUtility::setValueByPath(
                $formDefinition,
                'renderingOptions._originalIdentifier',
                $formDefinition['identifier'],
                '.'
            );

            $formDefinition['identifier'] .= '-' . ($this->request->getAttribute('currentContentObject')?->data['uid'] ?? '');
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
        /**
         * @var ArrayFormFactory $factory
         */
        $factory = GeneralUtility::makeInstance(ArrayFormFactory::class);
        /**
         * @var FormDefinition $formDefinitionObj
         */
        $formDefinitionObj = $factory->build($formDefinition, $prototypeName);
        /**
         * @var FormRuntime $formRuntime
         */
        $formRuntime = $formDefinitionObj->bind($this->request);
        $formState = $formRuntime->getFormState();
        $finisherResponse = $formRuntime->run();

        $elements = $formRuntime->getFormDefinition()->getElements();
        $honeyPot = null;

        if (isset($formRuntime->getFormDefinition()->getRenderingOptions()['honeypot']['enable']) &&
            $formRuntime->getFormDefinition()->getRenderingOptions()['honeypot']['enable'] === true) {
            $honeyPot = array_pop($elements);
        }

        $stateHash = $this->getHashService()->appendHmac(
            base64_encode(serialize($formState)),
            class_exists(\TYPO3\CMS\Form\Security\HashScope::class) ? \TYPO3\CMS\Form\Security\HashScope::FormState->prefix() : '',
            HashAlgo::SHA3_256
        );

        $currentPageIndex = $formRuntime->getCurrentPage() ? $formRuntime->getCurrentPage()->getIndex() : 0;
        $currentPageId = $currentPageIndex + 1;
        $formFields = $formDefinition['renderables'][$currentPageIndex]['renderables'] ?? [];

        // provides support for custom options providers (dynamic selects/radio/checkboxes)
        $formFieldsNames = $this->generateFieldNamesAndReplaceCustomOptions(
            $formFields,
            $formDefinition['identifier'],
            $formRuntime
        );

        if ($honeyPot) {
            $formFields[] = [
                'properties' => $honeyPot->getProperties(),
                'type' => $honeyPot->getType(),
                'identifier' => $honeyPot->getIdentifier(),
            ];
            $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][' . $honeyPot->getIdentifier() . ']';
        }

        // ONLY assign `__session` if form is performing (POST request)
        if ($formRuntime->canProcessFormSubmission() && $formRuntime->getFormSession() !== null) {
            $formFields[] = [
                'properties' => [],
                'type' => 'Hidden',
                'identifier' => '__session',
                'defaultValue' => $formRuntime->getFormSession()->getAuthenticatedIdentifier(),
            ];

            $formFieldsNames[] = 'tx_form_formframework[' . $formDefinition['identifier'] . '][__session]';
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

        $formDefinition['i18n'] = count($i18n) ? $i18n : null;
        $formDefinition = $this->getFormTranslator()->translate(
            $formDefinition,
            $formRuntime->getFormDefinition()->getRenderingOptions(),
            $formRuntime->getFormState() ? $formRuntime->getFormState()->getFormValues() : []
        );

        // Resolve internal t3:// links in RTE-enabled label fields (e.g. Checkbox labels).
        // TYPO3 v14 introduced native RTE support for form element labels; without this step,
        // labels containing internal page links would be output as raw t3://page?uid=X URIs
        // instead of resolved frontend URLs.
        $formDefinition = $this->resolveRteLinksInFormDefinition($formDefinition);

        $formStatus['status'] = null;
        $formStatus['errors'] = null;
        $formStatus['actionAfterSuccess'] = $finisherResponse ? json_decode($finisherResponse) : null;
        $formStatus['page'] = [
            'current' => $currentPageIndex,
            'nextPage' => $this->getNextPage($formRuntime),
            'pages' => count($formRuntime->getPages()),
        ];

        if ($formState &&
            $formState->isFormSubmitted() &&
            $this->request->getMethod() === 'POST') {
            /** @var ExtbaseRequestParameters $extbaseRequestParameters */
            $extbaseRequestParameters = $formRuntime->getRequest()->getAttribute('extbase');
            $result = $extbaseRequestParameters->getOriginalRequestMappingResults();
            /**
             * @var array<string, Error[]>
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

        return $this->jsonResponse();
    }

    /**
     * Walks the form definition and resolves any internal t3:// URIs in RTE-enabled label
     * fields by passing them through lib.parseFunc_RTE — the same pipeline used for regular
     * RTE body-text content elements.
     *
     * Only strings that actually contain a t3:// scheme are processed, so plain-text labels
     * (the majority) incur no additional overhead.
     *
     * @param array<mixed> $formDefinition
     * @return array<mixed>
     */
    private function resolveRteLinksInFormDefinition(array $formDefinition): array
    {
        if (!isset($formDefinition['renderables']) || !is_array($formDefinition['renderables'])) {
            return $formDefinition;
        }

        foreach ($formDefinition['renderables'] as &$page) {
            if (isset($page['renderables']) && is_array($page['renderables'])) {
                $page['renderables'] = $this->resolveRteLinksInRenderables($page['renderables']);
            }
        }
        unset($page);

        return $formDefinition;
    }

    /**
     * Recursively resolves t3:// links in the `label` field of each form element.
     * Recurses into container elements (Fieldset, GridRow) automatically.
     *
     * @param array<mixed> $renderables
     * @return array<mixed>
     */
    private function resolveRteLinksInRenderables(array $renderables): array
    {
        $contentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        foreach ($renderables as &$element) {
            // Recurse into container elements (Fieldset, GridRow, …)
            if (isset($element['renderables']) && is_array($element['renderables'])) {
                $element['renderables'] = $this->resolveRteLinksInRenderables($element['renderables']);
            }

            // Only process labels that contain an internal TYPO3 URI — skip plain-text labels
            if (
                isset($element['label'])
                && is_string($element['label'])
                && str_contains($element['label'], 't3://')
            ) {
                $element['label'] = $contentObjectRenderer->parseFunc(
                    $element['label'],
                    [],
                    '< lib.parseFunc_RTE'
                );
            }
        }
        unset($element);

        return $renderables;
    }

    /**
     * @param array<string, Error[]> $errors
     * @param string $formIdentifier
     * @return array<string, string>|null
     */
    private function prepareErrors(array $errors, string $formIdentifier): ?array
    {
        $parsedErrors = [];

        foreach ($errors as $key => $errorObj) {
            $parsedErrors[str_replace($formIdentifier . '.', '', $key)] = $errorObj[0]->render();
        }

        return count($parsedErrors) ? $parsedErrors : null;
    }

    private function getNextPage(\TYPO3\CMS\Form\Domain\Runtime\FormRuntime $formRuntime): ?int
    {
        if ($formRuntime->getCurrentPage() && $formRuntime->getNextEnabledPage()) {
            return $formRuntime->getNextEnabledPage()->getIndex();
        }

        return null;
    }

    /**
     * @param array<mixed> $formFields
     * @return array<int, string>
     */
    private function generateFieldNamesAndReplaceCustomOptions(
        array &$formFields,
        string $identifier,
        FormRuntime $formRuntime
    ): array {
        $formFieldsNames = [];

        foreach ($formFields as &$field) {
            if (in_array($field['type'], ['Fieldset', 'GridRow'], true) &&
                isset($field['renderables']) &&
                is_array($field['renderables'])) {
                $formFieldsNames = array_merge(
                    $formFieldsNames,
                    $this->generateFieldNamesAndReplaceCustomOptions($field['renderables'], $identifier, $formRuntime)
                );
            } else {
                if (!empty($field['properties']['customOptions'])) {
                    $customOptions = GeneralUtility::makeInstance(
                        $field['properties']['customOptions'],
                        $field,
                        $formFields,
                        $identifier,
                        $formRuntime
                    );

                    if ($customOptions instanceof CustomOptionsInterface) {
                        $field['properties']['options'] = $customOptions->get();
                    }

                    unset($field['properties']['customOptions']);
                }

                $defaultValue = $formRuntime->getFormDefinition()->getElementDefaultValueByIdentifier($field['identifier']);

                if ($defaultValue) {
                    $field['properties']['defaultValue'] = $defaultValue;
                }

                $formFieldsNames[] = 'tx_form_formframework[' . $identifier . '][' . $field['identifier'] . ']';
            }
        }

        return $formFieldsNames;
    }

    private function getHashService(): HashService
    {
        return $this->hashService;
    }

    private function getFormTranslator(): Translator
    {
        return $this->translator ??= GeneralUtility::makeInstance(Translator::class);
    }
}