<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\Select;

use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\AbstractFormFieldViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\SelectViewHelper;
use Iterator;

/**
 * Adds custom :html:`<option>` tags inside an :ref:`<f:form.select> <typo3-fluid-form-select>`.
 */
final class OptionViewHelper extends AbstractFormFieldViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'option';

    public function initializeArguments(): void
    {
        $this->registerArgument('selected', 'boolean', 'If set, overrides automatic detection of selected state for this option.');
        $this->registerArgument('additionalAttributes', 'array', 'Additional tag attributes. They will be added directly to the resulting HTML tag.');
        $this->registerArgument('data', 'array', 'Additional data-* attributes. They will each be added with a "data-" prefix.');
        $this->registerArgument('value', 'mixed', 'Value to be inserted in HTML tag - must be convertible to string!');
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $childContent = $this->renderChildren();
        $this->data['content'] = (string)$childContent;
        $value = $this->arguments['value'] ?? $childContent;
        if ($this->arguments['selected'] ?? $this->isValueSelected((string)$value)) {
            $this->data['selected'] = 'selected';
        }
        $this->data['value'] = $value;
        $this->data['type'] = 'selectOption';
        $parentRequestedFormTokenFieldName = $this->renderingContext->getViewHelperVariableContainer()->get(
            SelectViewHelper::class,
            'registerFieldNameForFormTokenGeneration'
        );
        if ($parentRequestedFormTokenFieldName) {
            // parent (select field) has requested this option must add one more
            // entry in the token generation registry for one additional potential
            // value of the field. Happens when "multiple" is true on parent.
            $this->registerFieldNameForFormTokenGeneration($parentRequestedFormTokenFieldName);
        }
        return json_encode($this->data);
    }

    protected function isValueSelected(string $value): bool
    {
        $selectedValue = $this->renderingContext->getViewHelperVariableContainer()->get(SelectViewHelper::class, 'selectedValue');
        if (is_array($selectedValue)) {
            return in_array($value, array_map(strval(...), $selectedValue), true);
        }
        if ($selectedValue instanceof Iterator) {
            return in_array($value, array_map(strval(...), iterator_to_array($selectedValue)), true);
        }
        return $value === (string)$selectedValue;
    }
}
