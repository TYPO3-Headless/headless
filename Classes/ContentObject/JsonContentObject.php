<?php
declare(strict_types = 1);

namespace FriendsOfTYPO3\Headless\ContentObject;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Contains JSON class object
 */
class JsonContentObject extends AbstractContentObject
{
    /**
     * @var ContentDataProcessor
     */
    protected $contentDataProcessor;

    /**
     * @param ContentObjectRenderer $cObj
     */
    public function __construct(ContentObjectRenderer $cObj)
    {
        parent::__construct($cObj);
        $this->contentDataProcessor = GeneralUtility::makeInstance(ContentDataProcessor::class);
    }

    /**
     * Rendering the cObject, JSON
     * @param array $conf Array of TypoScript properties
     * @return string The HTML output
     */
    public function render($conf = []): string
    {
        $data = [];

        if (!is_array($conf)) {
            $conf = [];
        }

        if (isset($conf['fields.'])) {
            $data = $this->cObjGet($conf['fields.']);
        }
        if (isset($conf['dataProcessing.'])) {
            $data = $this->processFieldWithDataProcessing($conf);
        }

        $json = json_encode($this->decodeFieldsIfRequired($data));

        if (isset($conf['stdWrap.'])) {
            $json = $this->cObj->stdWrap($json, $conf['stdWrap.']);
        }

        return $json;
    }

    /**
     * Rendering of a "string array" of cObjects from TypoScript
     * Will call ->cObjGetSingle() for each cObject found and accumulate the output.
     *
     * @param array $setup array with cObjects as values.
     * @param string $addKey A prefix for the debugging information
     * @return array Rendered output from the cObjects in the array.
     * @see cObjGetSingle()
     */
    public function cObjGet(array $setup, string $addKey = ''): array
    {
        if (!is_array($setup)) {
            return [];
        }
        $content = [];

        $sKeyArray = $this->filterByStringKeys($setup);
        foreach ($sKeyArray as $theKey) {
            $theValue = $setup[$theKey];
            if ((string)$theKey && strpos($theKey, '.') === false) {
                $conf = $setup[$theKey . '.'];
                $intVal = (isset($conf['intval']) && $conf['intval']) ? true : false;
                $contentDataProcessing['dataProcessing.'] = isset($conf['dataProcessing.']) ? $conf['dataProcessing.'] : [];

                $content[$theKey] = $this->cObj->cObjGetSingle($theValue, $conf, $addKey . $theKey);
                if ($intVal) {
                    $content[$theKey] = (int)$content[$theKey];
                }
                if (!empty($contentDataProcessing['dataProcessing.'])) {
                    $content[rtrim($theKey, '.')] = $this->processFieldWithDataProcessing($contentDataProcessing);
                }
            }
            if ((string)$theKey && strpos($theKey, '.') > 0 && !isset($setup[rtrim($theKey, '.')])) {
                $contentFieldName = isset($theValue['source']) ? $theValue['source'] : rtrim($theKey, '.');
                $contentFieldTypeProcessing['dataProcessing.'] = isset($theValue['dataProcessing.']) ? $theValue['dataProcessing.'] : [];

                if (array_key_exists('fields.', $theValue)) {
                    $content[$contentFieldName] = $this->cObjGet($theValue['fields.']);
                }
                if (!empty($contentFieldTypeProcessing['dataProcessing.'])) {
                    $content[rtrim($theKey, '.')] = $this->processFieldWithDataProcessing($contentFieldTypeProcessing);
                }
            }
        }
        return $content;
    }

    /**
     * Takes a TypoScript array as input and returns an array which contains all string properties found which had a value (not only properties).
     *
     * @param array $setupArr TypoScript array with string array in
     * @param bool $acceptAnyKeys If set, then a value is not required - the properties alone will be enough.
     * @return array An array with all string properties.
     */
    protected function filterByStringKeys(array $setupArr, bool $acceptAnyKeys = false): array
    {
        $filteredKeys = [];
        $keys = array_keys($setupArr);
        foreach ($keys as $key) {
            if ($acceptAnyKeys || is_string($key)) {
                $filteredKeys[] = (string)$key;
            }
        }
        $filteredKeys = array_unique($filteredKeys);
        return $filteredKeys;
    }

    /**
     * @param array $haystack
     * @param $needle
     * @return string
     */
    protected function recursiveFind(array $haystack, $needle)
    {
        $iterator  = new RecursiveArrayIterator($haystack);
        $recursive = new RecursiveIteratorIterator(
            $iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        $iteration = 0;
        foreach ($recursive as $key => $value) {
            if ($key === 'dataProcessing.') {
                $iteration++;
                if ($iteration > 1) {
                    return;
                }
            }
            if ($key === $needle) {
                yield $value;
            }
        }
    }

    /**
     * @param array $data
     * @return array
     */
    protected function decodeFieldsIfRequired(array $data): array
    {
        $json = [];

        foreach ($data as $key => $singleData) {
            if (is_string($singleData)) {
                if (json_decode($singleData) === null) {
                    $json[$key] = $singleData;
                } else {
                    $json[$key] = json_decode($singleData);
                }
            } elseif (is_array($singleData)) {
                $json[$key] = $this->decodeFieldsIfRequired($singleData);
            } else {
                $json[$key] = $singleData;
            }
        }
        return $json;
    }

    /**
     * @param array $dataProcessing
     * @return array
     */
    protected function processFieldWithDataProcessing(array $dataProcessing): array
    {
        $data = $this->contentDataProcessor->process(
            $this->cObj,
            $dataProcessing,
            [
                'data' => $this->cObj->data,
                'current' => $this->cObj->data[$this->cObj->currentValKey ?? null] ?? null
            ]
        );

        $dataProcessingData = [];
        foreach ($this->recursiveFind($dataProcessing, 'as') as $value) {
            if (isset($data[$value])) {
                $dataProcessingData = $data[$value];
            }
        }
        return $dataProcessingData;
    }
}
