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

use TYPO3\CMS\Core\Country\Country;
use TYPO3\CMS\Core\Country\CountryFilter;
use TYPO3\CMS\Core\Country\CountryProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Renders a :html:`<select>` tag with all available countries as options.
 *
 * Examples
 * ========
 *
 * Basic usage
 * -----------
 *
 * ::
 *
 *    <f:form.countrySelect name="country" value="{defaultCountry}" />
 *
 * Output::
 *
 *    {
 *      "name": "tx_extension_plugin[country]",
 *      "type": "countrySelect",
 *      "value": "{defaultCountry}",
 *      "options": [
 *          {
 *              "value": "AD",
 *              "label": "Andorra",
 *              "selected": false
 *          },
 *          ...
 *      ]
 *    }
 *
 * Prioritize countries
 * --------------------
 *
 * Define a list of countries which should be listed as first options in the
 * form element::
 *
 *    {
 *      "name": "tx_extension_plugin[country]",
 *      "type": "countrySelect",
 *      "value": "AT",
 *      "options": [
 *          {
 *              "value": "DE",
 *              "label": "Germany",
 *              "selected": false
 *          },
 *          {
 *              "value": "AT",
 *              "label": "Austria",
 *              "selected": true
 *          },
 *          {
 *              "value": "CH",
 *              "label": "Switzerland",
 *              "selected": false
 *          },
 *          {
 *          "value": "AD",
 *          "label": "Andorra",
 *          "selected": false
 *          },
 *          ...
 *      ]
 *    }
 *
 *  Additionally, Austria is pre-selected.
 *
 * Display another language
 * ------------------------
 *
 * A combination of optionLabelField and alternativeLanguage is possible. For
 * instance, if you want to show the localized official names but not in your
 * default language but in French. You can achieve this by using the following
 * combination::
 *
 *    <f:form.countrySelect
 *      name="country"
 *      optionLabelField="localizedOfficialName"
 *      alternativeLanguage="fr"
 *      sortByOptionLabel="true"
 *    />
 *
 * Bind an object
 * --------------
 *
 * You can also use the "property" attribute if you have bound an object to the form.
 * See :ref:`<f:form> <typo3-fluid-form>` for more documentation.
 */
final class CountrySelectViewHelper extends AbstractFormFieldViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('excludeCountries', 'array', 'Array with country codes that should not be shown.', false, []);
        $this->registerArgument('onlyCountries', 'array', 'If set, only the country codes in the list are rendered.', false, []);
        $this->registerArgument('optionLabelField', 'string', 'If specified, will call the appropriate getter on each object to determine the label. Use "name", "localizedName", "officialName" or "localizedOfficialName"', false, 'localizedName');
        $this->registerArgument('sortByOptionLabel', 'boolean', 'If true, List will be sorted by label.', false, false);
        $this->registerArgument('errorClass', 'string', 'CSS class to set if there are errors for this ViewHelper', false, 'f3-form-error');
        $this->registerArgument('prependOptionLabel', 'string', 'If specified, will provide an option at first position with the specified label.');
        $this->registerArgument('prependOptionValue', 'string', 'If specified, will provide an option at first position with the specified value.');
        $this->registerArgument('multiple', 'boolean', 'If set multiple options may be selected.', false, false);
        $this->registerArgument('required', 'boolean', 'If set no empty value is allowed.', false, false);
        $this->registerArgument('prioritizedCountries', 'array', 'A list of country codes which should be listed on top of the list.', false, []);
        $this->registerArgument('alternativeLanguage', 'string', 'If specified, the country list will be shown in the given language.');
    }

    public function render(): string
    {
        $this->data = json_decode(parent::render(), true);

        if ($this->arguments['required']) {
            $this->data['required'] = 'required';
        }
        $name = $this->getName();
        if ($this->arguments['multiple']) {
            $this->data['multiple'] = 'multiple';
            $name .= '[]';
        }
        $this->addAdditionalIdentityPropertiesIfNeeded();
        $this->setErrorClassAttribute();
        $this->registerFieldNameForFormTokenGeneration($name);
        $this->setRespectSubmittedDataValue(true);

        $this->data['name'] = $name;
        $this->data['type'] = 'countrySelect';

        $validCountries = $this->getCountryList();
        $options = $this->createOptions($validCountries);
        $selectedValue = $this->getValueAttribute();

        if ($selectedValue !== null) {
            $this->data['value'] = $selectedValue;
        }

        $prependOptionTag = $this->renderPrependOptionTag();
        $optionArray = [];

        if (!empty($prependOptionTag)) {
            $optionArray[] = $prependOptionTag;
        }

        foreach ($options as $value => $label) {
            $optionArray[] = $this->renderOptionTag($value, $label, $value === $selectedValue);
        }

        $this->tag->forceClosingTag(true);
        $this->data['options'] = $optionArray;
        return json_encode($this->data);
    }

    /**
     * @param Country[] $countries
     * @return array<string, string>
     */
    protected function createOptions(array $countries): array
    {
        $options = [];
        foreach ($countries as $code => $country) {
            switch ($this->arguments['optionLabelField']) {
                case 'localizedName':
                    $options[$code] = $this->translate($country->getLocalizedNameLabel());
                    break;
                case 'name':
                    $options[$code] = $country->getName();
                    break;
                case 'officialName':
                    $options[$code] = $country->getOfficialName();
                    break;
                case 'localizedOfficialName':
                    $name = $this->translate($country->getLocalizedOfficialNameLabel());
                    if (!$name) {
                        $name = $this->translate($country->getLocalizedNameLabel());
                    }
                    $options[$code] = $name;
                    break;
                default:
                    throw new \TYPO3Fluid\Fluid\Core\ViewHelper\Exception('Argument "optionLabelField" of <f:form.countrySelect> must either be set to "localizedName", "name", "officialName", or "localizedOfficialName".', 1674076708);
            }
        }
        if ($this->arguments['sortByOptionLabel']) {
            asort($options, SORT_LOCALE_STRING);
        } else {
            ksort($options, SORT_NATURAL);
        }
        if (($this->arguments['prioritizedCountries'] ?? []) !== []) {
            $finalOptions = [];
            foreach ($this->arguments['prioritizedCountries'] as $countryCode) {
                if (isset($options[$countryCode])) {
                    $label = $options[$countryCode];
                    $finalOptions[$countryCode] = $label;
                    unset($options[$countryCode]);
                }
            }
            foreach ($options as $countryCode => $label) {
                $finalOptions[$countryCode] = $label;
            }
            $options = $finalOptions;
        }
        return $options;
    }

    protected function translate(string $label): string
    {
        if ($this->arguments['alternativeLanguage']) {
            return (string)LocalizationUtility::translate($label, languageKey: $this->arguments['alternativeLanguage']);
        }
        return (string)LocalizationUtility::translate($label);
    }

    /**
     * Render prepended option tag
     */
    protected function renderPrependOptionTag(): array
    {
        if ($this->hasArgument('prependOptionLabel')) {
            $value = $this->hasArgument('prependOptionValue') ? $this->arguments['prependOptionValue'] : '';
            $label = $this->arguments['prependOptionLabel'];
            return $this->renderOptionTag((string)$value, (string)$label, false);
        }
        return [];
    }

    /**
     * Render one option tag
     *
     * @param string $value value attribute of the option tag (will be escaped)
     * @param string $label content of the option tag (will be escaped)
     * @param bool $isSelected specifies whether to add selected attribute
     * @return array the rendered option tag
     */
    protected function renderOptionTag(string $value, string $label, bool $isSelected): array
    {
        return [
            'value' => $value,
            'label' => $label,
            'selected' => $isSelected,
        ];
    }

    /**
     * @return Country[]
     */
    protected function getCountryList(): array
    {
        $filter = new CountryFilter();
        $filter->setOnlyCountries($this->arguments['onlyCountries'] ?? [])
            ->setExcludeCountries($this->arguments['excludeCountries'] ?? []);
        return GeneralUtility::makeInstance(CountryProvider::class)->getFiltered($filter);
    }
}
