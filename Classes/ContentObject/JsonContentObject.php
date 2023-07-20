<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonDecoder;
use FriendsOfTYPO3\Headless\Json\JsonDecoderInterface;
use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;

use function is_array;
use function strpos;

class JsonContentObject extends AbstractContentObject implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ContentDataProcessor $contentDataProcessor;
    private HeadlessUserInt $headlessUserInt;
    private JsonEncoderInterface $jsonEncoder;
    private JsonDecoderInterface $jsonDecoder;
    private array $conf;

    public function __construct(ContentDataProcessor $contentDataProcessor = null)
    {
        $this->contentDataProcessor = $contentDataProcessor ?? GeneralUtility::makeInstance(ContentDataProcessor::class);
        $this->jsonEncoder = GeneralUtility::makeInstance(JsonEncoder::class);
        $this->jsonDecoder = GeneralUtility::makeInstance(JsonDecoder::class);
        $this->headlessUserInt = GeneralUtility::makeInstance(HeadlessUserInt::class);
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

        $this->conf = $conf;

        if (isset($conf['fields.'])) {
            $data = $this->cObjGet($conf['fields.']);
        }
        if (isset($conf['dataProcessing.'])) {
            $data = $this->processFieldWithDataProcessing($conf);
        }

        $json = '';

        if (is_array($data)) {
            $json = $this->jsonEncoder->encode($this->jsonDecoder->decode($data));
        }

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
        $content = [];

        $sKeyArray = $this->filterByStringKeys($setup);
        foreach ($sKeyArray as $theKey) {
            $theValue = $setup[$theKey];
            if ((string)$theKey && !str_contains($theKey, '.')) {
                $conf = $setup[$theKey . '.'] ?? [];
                $contentDataProcessing['dataProcessing.'] = $conf['dataProcessing.'] ?? [];
                $content[$theKey] = $this->cObj->cObjGetSingle($theValue, $conf, $addKey . $theKey);
                if ((isset($conf['intval']) && $conf['intval']) || $theValue === 'INT') {
                    $content[$theKey] = (int)$content[$theKey];
                }
                if ((isset($conf['floatval']) && $conf['floatval']) || $theValue === 'FLOAT') {
                    $content[$theKey] = (float)$content[$theKey];
                }
                if ((isset($conf['boolval']) && $conf['boolval']) || $theValue === 'BOOL') {
                    $content[$theKey] = (bool)(int)$content[$theKey];
                }
                if ($theValue === 'USER_INT' || str_starts_with((string)$content[$theKey], '<!--INT_SCRIPT.')) {
                    $content[$theKey] = $this->headlessUserInt->wrap($content[$theKey], (int)($conf['ifEmptyReturnNull'] ?? 0) === 1 ? HeadlessUserInt::STANDARD_NULLABLE : HeadlessUserInt::STANDARD);
                }
                if ((int)($conf['ifEmptyReturnNull'] ?? 0) === 1 && $content[$theKey] === '') {
                    $content[$theKey] = null;
                }
                if (!empty($contentDataProcessing['dataProcessing.'])) {
                    $content[rtrim($theKey, '.')] = $this->processFieldWithDataProcessing($contentDataProcessing);
                }
            }
            if ((string)$theKey && strpos($theKey, '.') > 0 && !isset($setup[rtrim($theKey, '.')])) {
                $contentFieldName = $theValue['source'] ?? rtrim($theKey, '.');
                $contentFieldTypeProcessing['dataProcessing.'] = $theValue['dataProcessing.'] ?? [];

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
        return array_unique($filteredKeys);
    }

    /**
     * @param array $dataProcessing
     */
    protected function processFieldWithDataProcessing(array $dataProcessing): mixed
    {
        $data = $this->contentDataProcessor->process(
            $this->cObj,
            $dataProcessing,
            [
                'data' => $this->cObj->data,
                'current' => $this->cObj->data[$this->cObj->currentValKey ?? null] ?? null,
            ]
        );

        $dataProcessingData = null;
        $features = GeneralUtility::makeInstance(Features::class);

        foreach ($this->recursiveFind($dataProcessing, 'as') as $value) {
            if (isset($data[$value])) {
                $dataProcessingData = $data[$value];
            }
        }
        return $dataProcessingData;
    }

    /**
     * @param array<string, mixed> $haystack
     */
    protected function recursiveFind(array $haystack, string $needle): Generator
    {
        $iterator = new RecursiveArrayIterator($haystack);
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
}
