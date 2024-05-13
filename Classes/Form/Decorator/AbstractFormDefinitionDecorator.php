<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Decorator;

use function in_array;

abstract class AbstractFormDefinitionDecorator implements DefinitionDecoratorInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $formStatus;
    protected string $formId = '';

    public function __construct(array $formStatus = [])
    {
        $this->formStatus = $formStatus;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string,array<mixed>>
     */
    public function __invoke(array $definition, int $currentPage): array
    {
        $decorated = [];

        $pageElements = $definition['renderables'][$currentPage]['renderables'] ?? [];

        $this->formId = $definition['identifier'];

        $decorated['id'] = $this->formId;
        $decorated['api'] = $this->formStatus;
        $decorated['i18n'] = $definition['i18n']['properties'] ?? [];
        $decorated['elements'] = $this->handleRenderables($pageElements);

        return $this->overrideDefinition($decorated, $definition, $currentPage);
    }

    /**
     * @param array<string, mixed> $renderables
     * @return array<string, mixed>
     */
    protected function handleRenderables(array $renderables): array
    {
        foreach ($renderables as &$element) {
            if (in_array($element['type'], ['Fieldset', 'GridRow'], true) &&
                is_array($element['renderables'] ?? []) &&
                ($element['renderables'] ?? []) !== []) {
                $element['elements'] = $this->handleRenderables($element['renderables']);
                unset($element['renderables']);
            } else {
                $element = $this->prepareElement($element);
            }
        }

        return $renderables;
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>
     */
    protected function prepareElement(array $element): array
    {
        $element['name'] = 'tx_form_formframework[' . $this->formId . '][' . $element['identifier'] . ']';

        $element = $this->overrideElement($element);

        if (isset($element['renderingOptions']['FEOverrideType'])) {
            $element['type'] = $element['renderingOptions']['FEOverrideType'];
            unset($element['renderingOptions']['FEOverrideType']);
        }

        if (in_array($element['type'], ['ImageUpload', 'FileUpload'])) {
            unset($element['properties']['saveToFileMount']);
        }

        if (!isset($element['validators'])) {
            return $element;
        }

        foreach ($element['validators'] as &$validator) {
            if ($validator['identifier'] === 'RegularExpression') {
                $jsRegex = $validator['FERegularExpression'] ?? null;

                if ($jsRegex) {
                    $validator['options']['regularExpression'] = $jsRegex;
                    unset($validator['FERegularExpression']);
                }
            }
        }

        return $element;
    }

    /**
     * @param array<string, mixed> $element
     * @return array<string, mixed>
     */
    protected function overrideElement(array $element): array
    {
        return $element;
    }

    /**
     * @param array<string, mixed> $decorated
     * @param array<string, mixed> $definition
     * @param int $currentPage
     * @return array<string, mixed>
     */
    protected function overrideDefinition(array $decorated, array $definition, int $currentPage): array
    {
        return $decorated;
    }
}
