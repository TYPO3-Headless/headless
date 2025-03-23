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
 * ViewHelper which creates a text field :html:`<input type="text">`.
 *
 * Examples
 * ========
 *
 * Example::
 *
 *    <f:form.textfield name="myTextBox" value="default value" />
 *
 * Output::
 *
 *    {
 *      "name": "tx_extension_plugin[myTextBox]",
 *      "type": "text",
 *      "value": "default value"
 *    }
 */
final class TextfieldViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('errorClass', 'string', 'CSS class to set if there are errors for this ViewHelper', false, 'f3-form-error');
        $this->registerArgument('required', 'bool', 'If the field is required or not', false, false);
        $this->registerArgument('type', 'string', 'The field type, e.g. "text", "email", "url" etc.', false, 'text');
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $required = $this->arguments['required'];
        $type = $this->arguments['type'];

        $name = $this->getName();
        $this->registerFieldNameForFormTokenGeneration($name);
        $this->setRespectSubmittedDataValue(true);

        $this->data['name'] = $name;
        $this->data['type'] = $type;

        $value = $this->getValueAttribute();

        if ($value !== null) {
            $this->data['value'] = $value;
        }

        if ($required !== false) {
            $this->data['required'] = $required;
            $this->tag->addAttribute('required', 'required');
        }

        $this->addAdditionalIdentityPropertiesIfNeeded();
        $this->setErrorClassAttribute();

        return json_encode($this->data);
    }
}
