<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form;

use FriendsOfTYPO3\Headless\Form\Service\FormTranslationService;

use function array_keys;
use function array_merge;
use function array_replace_recursive;
use function is_array;

class Translator
{
    private FormTranslationService $translator;

    public function __construct(FormTranslationService $service = null)
    {
        $this->translator = $service ?? FormTranslationService::getInstance();
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
                $formDefinition['i18n']['properties'][$prop] = $this->translator
                    ->translateElementValue($formDefinition['i18n'], [$prop], $formRuntime);
            }
        }

        foreach ($formDefinition['renderables'] as $page) {
            $pageTranslation = [
                'label' => $this->translator->translateElementValue($page, ['label'], $formRuntime),
            ];

            if (!isset($page['renderables']) || !is_array($page['renderables'])) {
                continue;
            }

            $pageTranslation['renderables'] = $this->translateRenderables($page['renderables'], $formRuntime);

            $result['renderables'][] = array_replace_recursive($page, $pageTranslation);
        }

        return array_merge($formDefinition, $result);
    }

    /**
     * @param array<int, mixed> $renderables
     * @param array<string, mixed> $formRuntime
     * @return array<int, mixed>
     */
    private function translateRenderables(array $renderables, array $formRuntime): array
    {
        foreach ($renderables as &$element) {
            $properties = [];

            if (isset($element['renderables']) && is_array($element['renderables'])) {
                $element['renderables'] = $this->translateRenderables($element['renderables'], $formRuntime);
            }

            if (isset($element['validators']) &&
                is_array($element['validators'])) {
                foreach ($element['validators'] as &$validator) {
                    if (!isset($validator['errorMessage'])) {
                        continue;
                    }

                    $validator['errorMessage'] = $this->translator->translateElementError(
                        $element,
                        $validator['errorMessage'],
                        $formRuntime,
                        is_array($validator['options'] ?? null) ? $validator['options'] : []
                    );
                }
            }

            if (isset($element['properties']) && is_array($element['properties'])) {
                foreach (array_keys($element['properties']) as $property
                ) {
                    $properties[$property] = $this->translator->translateElementValue(
                        $element,
                        [$property],
                        $formRuntime
                    );
                }
            }

            if (isset($element['properties']['validationErrorMessages']) &&
                is_array($element['properties']['validationErrorMessages'])) {
                $properties['validationErrorMessages'] = [];
                foreach ($element['properties']['validationErrorMessages'] as $error) {
                    $properties['validationErrorMessages'][] = [
                        'code' => $error['code'],
                        'message' => $this->translator->translateElementError(
                            $element,
                            $error['code'],
                            $formRuntime
                        ),
                    ];
                }
            }

            $translatedDefaultValue = $this->translator->translateElementValue(
                $element,
                ['defaultValue'],
                $formRuntime
            );

            $element['label'] = $this->translator->translateElementValue(
                $element,
                ['label'],
                $formRuntime
            );

            $element['defaultValue'] = $translatedDefaultValue ?: ($element['defaultValue'] ?? '');
            $element['properties'] = $properties;
        }

        return $renderables;
    }
}
