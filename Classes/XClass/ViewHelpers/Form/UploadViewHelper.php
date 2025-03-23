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
 * A ViewHelper which generates an :html:`<input type="file">` HTML element.
 * Make sure to set ``enctype="multipart/form-data"`` on the form!
 *
 * Examples
 * ========
 *
 * Example::
 *
 *    <f:form.upload name="file" />
 *
 * Output::
 *
 *    {
 *      "type": "file",
 *      "name": "tx_extension_plugin[file]"
 *    }
 */
final class UploadViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('errorClass', 'string', 'CSS class to set if there are errors for this ViewHelper', false, 'f3-form-error');
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $multiple = isset($this->additionalArguments['multiple']);
        $name = $this->getName();
        $allowedFields = ['name', 'type', 'tmp_name', 'error', 'size'];
        foreach ($allowedFields as $fieldName) {
            if ($multiple) {
                $formTokenFieldName = sprintf('%s[*][%s]', $name, $fieldName);
            } else {
                $formTokenFieldName = $name . '[' . $fieldName . ']';
            }
            $this->registerFieldNameForFormTokenGeneration($formTokenFieldName);
        }
        $this->data['type'] = 'file';

        if ($multiple) {
            $this->data['name'] = $name . '[]';
        } else {
            $this->data['name'] = $name;
        }

        $this->setErrorClassAttribute();
        return json_encode($this->data);
    }
}
