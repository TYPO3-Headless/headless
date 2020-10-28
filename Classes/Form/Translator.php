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

namespace FriendsOfTYPO3\Headless\Form;

use function array_keys;
use function array_merge;
use function array_replace_recursive;
use FriendsOfTYPO3\Headless\Form\Service\FormTranslationService;
use function is_array;

final class Translator
{
    protected static function getTranslationService(): FormTranslationService
    {
        return FormTranslationService::getInstance();
    }

    /**
     * @param array<mixed> $formDefinition
     * @param array<mixed> $renderingOptions
     * @return array<mixed>
     */
    public function translate(array $formDefinition, array $renderingOptions): array
    {
        $result['renderables'] = [];
        $formRuntime = [
            'identifier' => $formDefinition['identifier'],
            'renderingOptions' => $renderingOptions,
        ];

        if (isset($formDefinition['i18n']['properties'])) {
            foreach ($formDefinition['i18n']['properties'] as $prop => $value) {
                $formDefinition['i18n']['properties'][$prop] = self::getTranslationService()
                    ->translateElementValue($formDefinition['i18n'], [$prop], $formRuntime);
            }
        }

        foreach ($formDefinition['renderables'] as $page) {
            $pageTranslation = [
                'label' => self::getTranslationService()->translateElementValue($page, ['label'], $formRuntime),
            ];

            if (!isset($page['renderables']) || !is_array($page['renderables'])) {
                continue;
            }

            foreach ($page['renderables'] as &$element) {
                $properties = [];

                if (isset($element['validators']) &&
                    is_array($element['validators'])) {
                    foreach ($element['validators'] as &$validator) {
                        if (!isset($validator['errorMessage'])) {
                            continue;
                        }

                        $validator['errorMessage'] = self::getTranslationService()->translateElementError(
                            $element,
                            $validator['errorMessage'],
                            $formRuntime,
                            is_array($validator['options']) ? $validator['options'] : []
                        );
                    }
                }

                foreach (array_keys($element['properties']) as $property
                ) {
                    $properties[$property] = self::getTranslationService()->translateElementValue(
                        $element,
                        [$property],
                        $formRuntime
                    );
                }

                if (isset($element['properties']['validationErrorMessages']) &&
                    is_array($element['properties']['validationErrorMessages'])) {
                    $properties['validationErrorMessages'] = [];
                    foreach ($element['properties']['validationErrorMessages'] as $error) {
                        $properties['validationErrorMessages'][] = [
                            'code' => $error['code'],
                            'message' => self::getTranslationService()->translateElementError(
                                $element,
                                $error['code'],
                                $formRuntime
                            ),
                        ];
                    }
                }

                $translatedDefaultValue = self::getTranslationService()->translateElementValue(
                    $element,
                    ['defaultValue'],
                    $formRuntime
                );

                $pageTranslation['renderables'][] = [
                    'label' => self::getTranslationService()->translateElementValue(
                        $element,
                        ['label'],
                        $formRuntime
                    ),
                    'defaultValue' => $translatedDefaultValue ?: $element['defaultValue'],
                    'properties' => $properties,
                ];
            }

            $result['renderables'][] = array_replace_recursive($page, $pageTranslation);
        }

        return array_merge($formDefinition, $result);
    }
}
