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
 * Creates a button.
 *
 * Examples
 * ========
 *
 * Defaults::
 *
 *    <f:form.button>Send Mail</f:form.button>
 *
 * Output::
 *
 *    {
 *        "type": "submit"
 *        "content": "Send mail"
 *    }
 *
 * Disabled cancel button with some HTML5 attributes::
 *
 *    <f:form.button type="reset" disabled="disabled"
 *        name="buttonName" value="buttonValue"
 *        formmethod="post" formnovalidate="formnovalidate"
 *    >
 *        Cancel
 *    </f:form.button>
 *
 * Output::
 *
 *    {
 *      "disabled": "disabled",
 *      "formmethod": "post",
 *      "formnovalidate": "formnovalidate",
 *      "name": "tx_extension_plugin[buttonName]",
 *      "type": "reset",
 *      "value": "buttonValue",
 *      "content": "Cancel"
 *    },
 */
final class ButtonViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('type', 'string', 'Specifies the type of button (e.g. "button", "reset" or "submit")', false, 'submit');
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $type = $this->arguments['type'];
        $name = $this->getName();
        $this->registerFieldNameForFormTokenGeneration($name);

        $this->data['name'] = $name;
        $this->data['type'] = $type;

        $value = $this->getValueAttribute();

        if ($value !== null) {
            $this->data['value'] = $value;
        }

        $this->data['content'] = (string)$this->renderChildren();

        return json_encode($this->data);
    }
}
