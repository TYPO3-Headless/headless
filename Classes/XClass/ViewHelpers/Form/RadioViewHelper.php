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

/**
 * ViewHelper which creates a simple radio button :html:`<input type="radio">`.
 *
 * Examples
 * ========
 *
 * Simple
 * ------
 *
 * ::
 *
 *    <f:form.radio name="myRadioButton" value="someValue" />
 *
 * Output::
 *
 *    {
 *      "type": "radio",
 *      "name": "tx_extension_plugin[myRadioButton]",
 *      "value": "someValue"
 *    }
 *
 * Preselect
 * ---------
 *
 * ::
 *
 *    <f:form.radio name="myRadioButton" value="someValue" checked="{object.value} == 5" />
 *
 * Output::
 *
 *    {
 *       "type": "radio",
 *       "name": "tx_extension_plugin[myRadioButton]",
 *       "value": "someValue"
 *       "checked: "checked"
 *    }
 *
 * Depending on bound ``object`` to surrounding :ref:`f:form <typo3-fluid-form>`.
 *
 * Bind to object property
 * -----------------------
 *
 * ::
 *
 *    <f:form.radio property="newsletter" value="1" /> yes
 *    <f:form.radio property="newsletter" value="0" /> no
 *
 * Output::
 *
 *    {
 *      "type": "radio",
 *      "name": "tx_extension_plugin[user][newsletter]",
 *      "value": 1,
 *      "checked": "checked"
 *    },
 *    {
 *      "type": "radio",
 *      "name": "tx_extension_plugin[user][newsletter]",
 *      "value": 0
 *    }
 *
 * Depending on property ``newsletter``.
 */
final class RadioViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('errorClass', 'string', 'CSS class to set if there are errors for this ViewHelper', false, 'f3-form-error');
        $this->registerArgument('checked', 'bool', 'Specifies that the input element should be preselected');
        $this->registerArgument('value', 'string', 'Value of input tag. Required for radio buttons', true);
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $checked = $this->arguments['checked'];

        $this->data['type'] = 'radio';

        $nameAttribute = $this->getName();
        $valueAttribute = $this->getValueAttribute();

        $propertyValue = null;
        if ($this->hasMappingErrorOccurred()) {
            $propertyValue = $this->getLastSubmittedFormData();
        }
        if ($checked === null && $propertyValue === null) {
            $propertyValue = $this->getPropertyValue();
            $propertyValue = $this->convertToPlainValue($propertyValue);
        }

        if ($propertyValue !== null) {
            // no type-safe comparison by intention
            $checked = $propertyValue == $valueAttribute;
        }

        $this->registerFieldNameForFormTokenGeneration($nameAttribute);
        $this->data['name'] = $nameAttribute;
        $this->data['value'] = $valueAttribute;
        if ($checked === true) {
            $this->data['checked'] = 'checked';
        }

        $this->setErrorClassAttribute();

        return json_encode($this->data);
    }
}
