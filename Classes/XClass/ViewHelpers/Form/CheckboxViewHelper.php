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

namespace FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form;

use Traversable;

/**
 * ViewHelper which creates a simple checkbox :html:`<input type="checkbox">`.
 *
 * Examples
 * ========
 *
 * Simple one
 * ----------
 *
 * ::
 *
 *    <f:form.checkbox name="myCheckBox" value="someValue" />
 *
 * Output::
 *
 *    {
 *      "name": "tx_extension_plugin[myCheckBox]",
 *      "type": "hidden",
 *      "value": ""
 *    },
 *    {
 *      "name": "tx_extension_plugin[myCheckBox]",
 *      "type": "checkbox",
 *      "value": "someValue"
 *    }
 *
 * Preselect
 * ---------
 *
 * ::
 *
 *    <f:form.checkbox name="myCheckBox" value="someValue" checked="{object.value} == 5" />
 *
 * Output::
 *
 *    {
 *      "name": "tx_extension_plugin[myCheckBox]",
 *      "type": "checkbox",
 *      "value": "someValue",
 *      "checked": "checked"
 *    },
 *
 * Depending on bound ``object`` to surrounding :ref:`f:form <typo3-fluid-form>`.
 *
 * Bind to object property
 * -----------------------
 *
 * ::
 *
 *    <f:form.checkbox property="interests" value="TYPO3" multiple="1" />
 *
 * Output::
 *
 *    {
 *      "name": "tx_extension_plugin[customer][interests]",
 *      "type": "hidden",
 *      "value": ""
 *    },
 *    {
 *      "name": "tx_extension_plugin[customer][interests][]",
 *      "type": "checkbox",
 *      "value": "TYPO3"
 *    },
 *
 * Depending on property ``interests``.
 */
final class CheckboxViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument(
            'errorClass',
            'string',
            'CSS class to set if there are errors for this ViewHelper',
            false,
            'f3-form-error'
        );
        $this->registerArgument('value', 'string', 'Value of input tag. Required for checkboxes', true);
        $this->registerArgument('checked', 'bool', 'Specifies that the input element should be preselected');
        $this->registerArgument('multiple', 'bool', 'Specifies whether this checkbox belongs to a multivalue (is part of a checkbox group)', false, false);
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $checked = $this->arguments['checked'];
        $multiple = $this->arguments['multiple'];

        $nameAttribute = $this->getName();

        $valueAttribute = $this->getValueAttribute();
        $propertyValue = null;
        if ($this->hasMappingErrorOccurred()) {
            $propertyValue = $this->getLastSubmittedFormData();
        }
        if ($checked === null && $propertyValue === null) {
            $propertyValue = $this->getPropertyValue();
        }

        if ($propertyValue instanceof Traversable) {
            $propertyValue = iterator_to_array($propertyValue);
        }
        if (is_array($propertyValue)) {
            $propertyValue = array_map($this->convertToPlainValue(...), $propertyValue);
            if ($checked === null) {
                $checked = in_array($valueAttribute, $propertyValue);
            }
            $nameAttribute .= '[]';
        } elseif ($multiple === true) {
            $nameAttribute .= '[]';
        } elseif ($propertyValue !== null) {
            $checked = (bool)$propertyValue === (bool)$valueAttribute;
        }

        $this->registerFieldNameForFormTokenGeneration($nameAttribute);
        $this->data['name'] = $nameAttribute;
        $this->data['type'] = 'checkbox';
        $this->data['value'] = $valueAttribute;
        if ($checked === true) {
            $this->data['checked'] = 'checked';
        }

        $this->setErrorClassAttribute();
        $hiddenField = $this->renderHiddenFieldForEmptyValue();
        return $hiddenField . json_encode($this->data);
    }
}
