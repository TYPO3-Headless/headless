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
 * ViewHelper which creates a simple Password Text Box :html:`<input type="password">`.
 *
 * Examples
 * ========
 *
 * Example::
 *
 *    <f:form.password name="myPassword" />
 *
 * Output::
 *
 *    {
 *       "type": "password",
 *       "name": "tx_extension_plugin[myPassword]"
 *    }
 */
final class PasswordViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('errorClass', 'string', 'CSS class to set if there are errors for this ViewHelper', false, 'f3-form-error');
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $name = $this->getName();
        $this->registerFieldNameForFormTokenGeneration($name);
        $this->setRespectSubmittedDataValue(true);

        $this->data['type'] = 'password';
        $this->data['name'] = $name;
        $this->data['value'] = $this->getValueAttribute();

        $this->addAdditionalIdentityPropertiesIfNeeded();
        $this->setErrorClassAttribute();

        return json_encode($this->data);
    }
}
