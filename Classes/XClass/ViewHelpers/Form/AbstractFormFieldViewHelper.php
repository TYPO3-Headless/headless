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

use TYPO3\CMS\Fluid\ViewHelpers\Form\AbstractFormFieldViewHelper as CoreAbstractFormFieldViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;

/**
 * Abstract Form ViewHelper. Bundles functionality related to direct property access of objects in other Form ViewHelpers.
 *
 * If you set the "property" attribute to the name of the property to resolve from the object, this class will
 * automatically set the name and value of a form element.
 *
 * Note this set of ViewHelpers is tailored to be used only in extbase context.
 */
abstract class AbstractFormFieldViewHelper extends CoreAbstractFormFieldViewHelper
{
    protected array $data = [];

    public function render(): string
    {
        parent::render();

        $attributes = [];
        foreach ($this->tag->getAttributes() as $key => $value) {
            if (str_starts_with($key, 'data-')) {
                $key = substr($key, strpos('data-', $key) + 5);
                $attributes['data'][$key] = $value;
                continue;
            }

            $attributes[$key] = $value;
        }
        return json_encode($attributes);
    }

    /**
     * Renders a hidden field with the same name as the element, to make sure the empty value is submitted
     * in case nothing is selected. This is needed for checkbox and multiple select fields
     */
    protected function renderHiddenFieldForEmptyValue(): string
    {
        $hiddenFieldNames = [];
        $viewHelperVariableContainer = $this->renderingContext->getViewHelperVariableContainer();
        if ($viewHelperVariableContainer->exists(
            FormViewHelper::class,
            'renderedHiddenFields'
        )
        ) {
            $hiddenFieldNames = $viewHelperVariableContainer->get(
                FormViewHelper::class,
                'renderedHiddenFields'
            );
        }
        $fieldName = $this->getName();
        if (substr($fieldName, -2) === '[]') {
            $fieldName = substr($fieldName, 0, -2);
        }
        if (!in_array($fieldName, $hiddenFieldNames, true)) {
            $hiddenFieldNames[] = $fieldName;
            $viewHelperVariableContainer->addOrUpdate(
                FormViewHelper::class,
                'renderedHiddenFields',
                $hiddenFieldNames
            );
            return json_encode($this->addHiddenField(htmlspecialchars($fieldName), ''));
        }
        return '';
    }

    protected function addHiddenField(string $name, mixed $value): array
    {
        $tmp = [];
        $tmp['name'] = $name;
        $tmp['type'] = 'hidden';
        $tmp['value'] = $value;

        return $tmp;
    }
}
