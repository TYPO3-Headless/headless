<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\DataProcessing;

use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Basic TypoScript configuration:
 * Processing the field pi_flexform and overrides the values stored in data
 *
 * 10 = FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor
 *
 * Advanced TypoScript configuration:
 * Processing the field assigned in fieldName and stores data to new key
 *
 * 10 = FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor
 * 10 {
 *   fieldName = pi_flexform
 *   as = flexform
 * }
 *
 * Apply TypoScript to individual fields
 *
 * 10 = FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor
 * 10 {
 *   fieldName = pi_flexform
 *   as = flexform
 *   overrideFields {
 *     image = TEXT
 *     image {
 *       dataProcessing {
 *         10 = FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor
 *         10 {
 *           references {
 *             table = tt_content
 *             fieldName = image
 *           }
 *           as = image
 *         }
 *       }
 *     }
 *     link = TEXT
 *     link {
 *       field = link
 *       htmlSpecialChars = 1
 *       typolink {
 *         parameter {
 *           field = link
 *         }
 *         returnLast = result
 *       }
 *     }
 *   }
 * }
 *
 * @codeCoverageIgnore
 */
class FlexFormProcessor implements DataProcessorInterface
{
    /**
     * @var FlexFormService
     */
    protected $flexFormService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
    }

    /**
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array $contentObjectConfiguration The configuration of Content Object
     * @param array $processorConfiguration The configuration of this processor
     * @param array $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array the processed data as key/value store
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        $fieldName = $cObj->stdWrapValue('fieldName', $processorConfiguration);

        // default flexform field name
        if (empty($fieldName)) {
            $fieldName = 'pi_flexform';
        }

        if (!$processedData['data'][$fieldName] && !$processedData[$fieldName]) {
            return $processedData;
        }

        // processing the flexform data
        $originalValue = $processedData['data'][$fieldName] ?? $processedData[$fieldName];

        if (\is_array($originalValue)) {
            $flexformData = $originalValue;
        } elseif (\is_string($originalValue)) {
            $flexformData = $this->flexFormService->convertFlexFormContentToArray($originalValue);
        } else {
            return $processedData;
        }

        // save result in "data" (default) or given variable name
        $targetVariableName = $cObj->stdWrapValue('as', $processorConfiguration);

        if (isset($processorConfiguration['overrideFields.'])) {
            $flexformData = $this->processOverrideFields($cObj->data, $flexformData, $processorConfiguration);
        }

        if (!empty($targetVariableName)) {
            $processedData[$targetVariableName] = $flexformData;
        } else {
            if ($processedData['data'][$fieldName]) {
                $processedData['data'][$fieldName] = $flexformData;
            } else {
                $processedData[$fieldName] = $flexformData;
            }
        }

        return $processedData;
    }

    /**
     * @param array $data Current data-record
     * @param array $flexformData
     * @param array $processorConfiguration
     * @return array
     */
    public function processOverrideFields(array $data, array $flexformData, array $processorConfiguration): array
    {
        $recordContentObjectRenderer = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $data = array_merge($data, $flexformData);
        $recordContentObjectRenderer->start($data);

        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
        $overrideFields = $typoScriptService->convertTypoScriptArrayToPlainArray($processorConfiguration['overrideFields.']);
        $jsonCE = $typoScriptService->convertPlainArrayToTypoScriptArray(['fields' => $overrideFields, '_typoScriptNodeValue' => 'JSON']);
        $record = \json_decode($recordContentObjectRenderer->cObjGetSingle('JSON', $jsonCE), true);

        foreach ($record as $fieldName => $overrideData) {
            $flexformData[$fieldName] = $overrideData;
        }

        return $flexformData;
    }
}
