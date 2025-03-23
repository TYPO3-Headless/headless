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
 * Creates a submit button.
 *
 * Examples
 * ========
 *
 * Defaults
 * --------
 *
 * ::
 *
 *    <f:form.submit value="Send Mail" />
 *
 * Output::
 *
 *    {
 *      "type": "submit",
 *      "value": "Send Mail"
 *    }
 *
 * Dummy content for template preview
 * ----------------------------------
 *
 * ::
 *
 *    <f:form.submit name="mySubmit" value="Send Mail"><button>dummy button</button></f:form.submit>
 *
 * Output::
 *
 *    <input type="submit" name="mySubmit" value="Send Mail" />
 */
final class SubmitViewHelper extends AbstractFormFieldViewHelper
{
    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $name = $this->getName();
        $this->registerFieldNameForFormTokenGeneration($name);

        $this->data['type'] = 'submit';
        $this->data['value'] = $this->getValueAttribute();
        if (!empty($name)) {
            $this->data['name'] = $name;
        }

        return json_encode($this->data);
    }
}
