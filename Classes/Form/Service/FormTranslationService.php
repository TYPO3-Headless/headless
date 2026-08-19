<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Service;

use InvalidArgumentException;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException;
use TYPO3\CMS\Form\Service\TranslationService;

class FormTranslationService extends TranslationService
{
    /**
     * @param array<string,mixed> $element
     * @param array<int,mixed> $propertyParts
     * @param array<string,mixed> $formRuntime
     * @return string|array<int,string>
     * @throws InvalidArgumentException
     * @internal
     */
    public function translateElementValue(
        array $element,
        array $propertyParts,
        array $formRuntime,
        Locale|string|null $locale = null
    ) {
        if (empty($propertyParts)) {
            throw new InvalidArgumentException('The argument "propertyParts" is empty', 1476216007);
        }

        $propertyType = 'properties';
        $property = implode('.', $propertyParts);
        $renderingOptions = $element['renderingOptions'] ?? [];
        $element['properties'] = $element['properties'] ?? [];

        if ($property === 'label') {
            $defaultValue = $element['label'] ?? '';
        } elseif (($element['type'] ?? '') !== 'Page') {
            try {
                $defaultValue = ArrayUtility::getValueByPath($element['properties'], $propertyParts, '.');
            } catch (MissingArrayPathException $exception) {
                $defaultValue = null;
            }
        } else {
            $propertyType = 'renderingOptions';
            try {
                $defaultValue = ArrayUtility::getValueByPath($renderingOptions, $propertyParts, '.');
            } catch (MissingArrayPathException $exception) {
                $defaultValue = null;
            }
        }

        $translatePropertyValueIfEmpty = $renderingOptions['translation']['translatePropertyValueIfEmpty'] ?? true;

        if ($this->isEmptyTranslatedValue($defaultValue) && !$translatePropertyValueIfEmpty) {
            return $defaultValue;
        }

        $defaultValue = $this->isEmptyTranslatedValue($defaultValue) ? '' : $defaultValue;
        $translationFiles = $renderingOptions['translation']['translationFiles'] ?? [];
        if (empty($translationFiles)) {
            $formRuntime['renderingOptions'] = $formRuntime['renderingOptions'] ?? [];
            $translationFiles = $formRuntime['renderingOptions']['translation']['translationFiles'];
        }

        $translationFiles = $this->sortArrayWithIntegerKeysDescending($translationFiles);
        if (!$locale && isset($renderingOptions['translation']['language'])) {
            $locale = $renderingOptions['translation']['language'];
        }

        try {
            $arguments = ArrayUtility::getValueByPath(
                $renderingOptions['translation']['arguments'] ?? [],
                $propertyParts,
                '.'
            );
        } catch (MissingArrayPathException $e) {
            $arguments = [];
        }

        $originalFormIdentifier = null;
        if (isset($formRuntime['renderingOptions']['_originalIdentifier'])) {
            $originalFormIdentifier = $formRuntime['renderingOptions']['_originalIdentifier'];
        }

        if ($property === 'options' && is_array($defaultValue)) {
            foreach ($defaultValue as $optionValue => &$optionLabel) {
                $chain = $this->buildElementTranslationKeyChain(
                    $translationFiles,
                    $element,
                    $formRuntime,
                    $propertyType,
                    $property . '.' . $optionValue,
                    $originalFormIdentifier,
                );
                $translatedValue = $this->processTranslationChain($chain, $locale, $arguments);
                $optionLabel = $this->isEmptyTranslatedValue($translatedValue) ? $optionLabel : $translatedValue;
            }
            return $defaultValue;
        }

        if ($property === 'fluidAdditionalAttributes' && is_array($defaultValue)) {
            foreach ($defaultValue as $propertyName => &$propertyValue) {
                $chain = $this->buildElementTranslationKeyChain(
                    $translationFiles,
                    $element,
                    $formRuntime,
                    $propertyType,
                    (string)$propertyName,
                    $originalFormIdentifier,
                );
                $translatedValue = $this->processTranslationChain($chain, $locale, $arguments);
                $propertyValue = $this->isEmptyTranslatedValue($translatedValue) ? $propertyValue : $translatedValue;
            }
            return $defaultValue;
        }

        $chain = $this->buildElementTranslationKeyChain(
            $translationFiles,
            $element,
            $formRuntime,
            $propertyType,
            $property,
            $originalFormIdentifier,
        );
        $translatedValue = $this->processTranslationChain($chain, $locale, $arguments);

        return $this->isEmptyTranslatedValue($translatedValue) ? $defaultValue : $translatedValue;
    }

    /**
     * @param list<string> $translationFiles
     * @param array<string, mixed> $element
     * @param array<string, mixed> $formRuntime
     * @return list<string>
     */
    private function buildElementTranslationKeyChain(
        array $translationFiles,
        array $element,
        array $formRuntime,
        string $propertyType,
        string $leaf,
        ?string $originalFormIdentifier,
    ): array {
        $formIdentifier = (string)($formRuntime['identifier'] ?? '');
        $elementIdentifier = (string)($element['identifier'] ?? '');
        $isFormItself = $elementIdentifier === '' || $elementIdentifier === $formIdentifier;
        if ($isFormItself) {
            $elementIdentifier = $formIdentifier;
        }
        $elementType = (string)($element['type'] ?? ($isFormItself ? 'Form' : ''));

        $chain = [];
        foreach ($translationFiles as $translationFile) {
            if (!empty($originalFormIdentifier)) {
                if ($isFormItself) {
                    $chain[] = sprintf(
                        '%s:%s.element.%s.%s.%s',
                        $translationFile,
                        $originalFormIdentifier,
                        $originalFormIdentifier,
                        $propertyType,
                        $leaf,
                    );
                    $chain[] = sprintf(
                        '%s:element.%s.%s.%s',
                        $translationFile,
                        $originalFormIdentifier,
                        $propertyType,
                        $leaf,
                    );
                } else {
                    $chain[] = sprintf(
                        '%s:%s.element.%s.%s.%s',
                        $translationFile,
                        $originalFormIdentifier,
                        $elementIdentifier,
                        $propertyType,
                        $leaf,
                    );
                }
            }
            $chain[] = sprintf(
                '%s:%s.element.%s.%s.%s',
                $translationFile,
                $formIdentifier,
                $elementIdentifier,
                $propertyType,
                $leaf,
            );
            $chain[] = sprintf(
                '%s:element.%s.%s.%s',
                $translationFile,
                $elementIdentifier,
                $propertyType,
                $leaf,
            );
            $chain[] = sprintf(
                '%s:element.%s.%s.%s',
                $translationFile,
                $elementType,
                $propertyType,
                $leaf,
            );
        }
        return $chain;
    }

    /**
     * @param array<string,mixed> $element
     * @param int $code
     * @param array<string,mixed> $formRuntime
     * @param array<string,mixed> $arguments
     * @param string $defaultValue
     * @return string
     */
    public function translateElementError(
        array $element,
        int $code,
        array $formRuntime,
        array $arguments = [],
        string $defaultValue = ''
    ): string {
        if (empty($code)) {
            throw new InvalidArgumentException('The argument "code" is empty', 1489272978);
        }

        $renderingOptions = $element['renderingOptions'] ?? [];
        $translationFiles = $renderingOptions['translation']['translationFiles'] ?? [];
        if (empty($translationFiles)) {
            $translationFiles = $formRuntime['renderingOptions']['translation']['translationFiles'];
        }

        $translationFiles = $this->sortArrayWithIntegerKeysDescending($translationFiles);
        $language = $renderingOptions['language'] ?? null;
        $originalFormIdentifier = $formRuntime['renderingOptions']['_originalIdentifier'] ?? null;

        $formIdentifier = (string)($formRuntime['identifier'] ?? '');
        $elementIdentifier = (string)($element['identifier'] ?? '');
        $isFormItself = $elementIdentifier === '' || $elementIdentifier === $formIdentifier;
        if ($isFormItself) {
            $elementIdentifier = $formIdentifier;
        }

        $translationKeyChain = [];
        foreach ($translationFiles as $translationFile) {
            if (!empty($originalFormIdentifier)) {
                if ($isFormItself) {
                    $translationKeyChain[] = sprintf(
                        '%s:%s.validation.error.%s.%s',
                        $translationFile,
                        $originalFormIdentifier,
                        $originalFormIdentifier,
                        $code
                    );
                    $translationKeyChain[] = sprintf(
                        '%s:validation.error.%s.%s',
                        $translationFile,
                        $originalFormIdentifier,
                        $code
                    );
                } else {
                    $translationKeyChain[] = sprintf(
                        '%s:%s.validation.error.%s.%s',
                        $translationFile,
                        $originalFormIdentifier,
                        $elementIdentifier,
                        $code
                    );
                }

                $translationKeyChain[] = sprintf(
                    '%s:%s.validation.error.%s',
                    $translationFile,
                    $originalFormIdentifier,
                    $code
                );
            }
            $translationKeyChain[] = sprintf(
                '%s:%s.validation.error.%s.%s',
                $translationFile,
                $formIdentifier,
                $elementIdentifier,
                $code
            );
            $translationKeyChain[] = sprintf(
                '%s:%s.validation.error.%s',
                $translationFile,
                $formIdentifier,
                $code
            );
            $translationKeyChain[] = sprintf(
                '%s:validation.error.%s.%s',
                $translationFile,
                $elementIdentifier,
                $code
            );
            $translationKeyChain[] = sprintf('%s:validation.error.%s', $translationFile, $code);
        }

        $translatedValue = $this->processTranslationChain($translationKeyChain, $language, $arguments);

        return $this->isEmptyTranslatedValue($translatedValue) ? $defaultValue : $translatedValue;
    }
}
