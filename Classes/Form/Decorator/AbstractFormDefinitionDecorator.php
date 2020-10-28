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

namespace FriendsOfTYPO3\Headless\Form\Decorator;

abstract class AbstractFormDefinitionDecorator implements DefinitionDecoratorInterface
{
    /**
     * @param array<mixed> $definition
     * @return array<string,array<mixed>>
     */
    public function __invoke(array $definition, int $currentPage): array
    {
        $decorated = [];

        $pageElements = $definition['renderables'][$currentPage]['renderables'] ?? [];

        foreach ($pageElements as &$element) {
            $element['name'] = 'tx_form_formframework[' . $element['identifier'] . ']';

            $element = $this->overrideElement($element);

            if (\in_array($element['type'], ['ImageUpload', 'FileUpload'])) {
                unset($element['properties']['saveToFileMount']);
            }

            if (!isset($element['validators'])) {
                continue;
            }

            foreach ($element['validators'] as &$validator) {
                if ($validator['identifier'] === 'RegularExpression') {
                    $validator['options']['regularExpression'] = trim(
                        $validator['options']['regularExpression'],
                        '/'
                    );
                }
            }
        }

        $decorated['identifier'] = $definition['identifier'];
        $decorated['i18n'] = $definition['i18n']['properties'] ?? [];
        $decorated['elements'] = $pageElements;

        return $this->overrideDefinition($decorated, $definition, $currentPage);
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
