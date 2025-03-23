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
 * Renders an :html:`<input type="hidden" ...>` tag.
 *
 * Examples
 * ========
 *
 * Example::
 *
 *    <f:form.hidden name="myHiddenValue" value="42" />
 *
 * Output::
 *
 *    {
 *       "type": "hidden",
 *       "name": "tx_extension_plugin[myHiddenValue]",
 *       "value": 42
 *    }
 *
 * You can also use the "property" attribute if you have bound an object to the form.
 * See :ref:`<f:form> <typo3-fluid-form>` for more documentation.
 */
final class HiddenViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument(
            'respectSubmittedDataValue',
            'bool',
            'enable or disable the usage of the submitted values',
            false,
            true
        );
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $name = $this->getName();
        $this->registerFieldNameForFormTokenGeneration($name);
        $this->setRespectSubmittedDataValue($this->arguments['respectSubmittedDataValue']);

        $this->data['type'] = 'hidden';
        $this->data['name'] = $name;
        $this->data['value'] = $this->getValueAttribute();

        $this->addAdditionalIdentityPropertiesIfNeeded();

        return json_encode($this->data);
    }
}
