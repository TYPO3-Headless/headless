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

/**
 * Adds custom :html:`<optgroup>` tags inside an :ref:`<f:form.select> <typo3-fluid-form-select>`,
 * supports further child :ref:`<f:form.select.option> <typo3-fluid-form-select-option>` tags.
 */
final class OptgroupViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('additionalAttributes', 'array', 'Additional tag attributes. They will be added directly to the resulting HTML tag.');
        $this->registerArgument('data', 'array', 'Additional data-* attributes. They will each be added with a "data-" prefix.');
        $this->registerArgument('disabled', 'boolean', 'If true, option group is rendered as disabled', false, false);
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        $this->data['type'] = 'selectOptionGroup';

        if ($this->arguments['disabled']) {
            $this->data['disabled'] = 'disabled';
        }

        $renderedChildren = trim($this->renderChildren());
        $renderedChildren = preg_replace('!}\s*{!', '},{', $renderedChildren);
        $renderedChildren = preg_replace("!\r?\n!", '', $renderedChildren);
        $renderedChildren = '{"elements": [' . $renderedChildren . ']}';
        $renderedChildren = json_decode($renderedChildren, true);

        if ($renderedChildren !== null) {
            $this->data['options'] = $renderedChildren['elements'];
        }

        return json_encode($this->data);
    }
}
