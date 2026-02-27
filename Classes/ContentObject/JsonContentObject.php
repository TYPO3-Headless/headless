<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonDecoderInterface;
use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;

use function is_array;

class JsonContentObject extends AbstractContentObject implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected ContentDataProcessor $contentDataProcessor,
        protected JsonEncoder $jsonEncoder,
        protected JsonDecoderInterface $jsonDecoder,
        protected HeadlessUserInt $headlessUserInt
    ) {}

    /**
     * Rendering the cObject, JSON
     *
     * @param array $conf Array of TypoScript properties
     * @return string JSON-encoded content
     */
    public function render($conf = []): string
    {
        if (!is_array($conf)) {
            $conf = [];
        }

        if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
            return '';
        }

        $nullableFieldsIfEmpty = array_flip(
            GeneralUtility::trimExplode(',', $conf['nullableFieldsIfEmpty'] ?? '', true)
        );

        $data = [];

        if (isset($conf['fields.'])) {
            $data = $this->cObjGet($conf['fields.'], '', $nullableFieldsIfEmpty);
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
     * @param array  $setup
     * @param string $addKey
     * @param array  $nullableFieldsIfEmpty
     * @return array
     */
    public function cObjGet(array $setup, string $addKey = '', array $nullableFieldsIfEmpty = []): array
    {
        $content = [];

        foreach ($setup as $theKey => $theValue) {
            if (!is_string($theKey) || $theKey === '') {
                continue;
            }

            if (!str_contains($theKey, '.')) {
                $conf = $setup[$theKey . '.'] ?? [];
                $content[$theKey] = $this->cObj->cObjGetSingle($theValue, $conf, $addKey . $theKey);

                if (!empty($conf['intval']) || $theValue === 'INT') {
                    $content[$theKey] = (int)$content[$theKey];
                } elseif (!empty($conf['floatval']) || $theValue === 'FLOAT') {
                    $content[$theKey] = (float)$content[$theKey];
                } elseif (!empty($conf['boolval']) || $theValue === 'BOOL') {
                    $content[$theKey] = (bool)(int)$content[$theKey];
                }

                $ifEmptyReturnNull = (int)($conf['ifEmptyReturnNull'] ?? 0) === 1;

                if ($theValue === 'USER_INT' || (is_string($content[$theKey]) && str_starts_with($content[$theKey], '<!--INT_SCRIPT.'))) {
                    $content[$theKey] = $this->headlessUserInt->wrap(
                        $content[$theKey],
                        $ifEmptyReturnNull ? HeadlessUserInt::STANDARD_NULLABLE : HeadlessUserInt::STANDARD
                    );
                }

                if ($content[$theKey] === '' && ($ifEmptyReturnNull || isset($nullableFieldsIfEmpty[$theKey]))) {
                    $content[$theKey] = null;
                }

                if ((int)($conf['ifEmptyUnsetKey'] ?? 0) === 1 && ($content[$theKey] === '' || $content[$theKey] === false)) {
                    unset($content[$theKey]);
                }

                if (isset($conf['dataProcessing.'])) {
                    $content[$theKey] = $this->processFieldWithDataProcessing(
                        ['dataProcessing.' => $conf['dataProcessing.']]
                    );
                }
            } elseif ($theKey[0] !== '.' && !isset($setup[rtrim($theKey, '.')])) {
                $trimmedKey = rtrim($theKey, '.');
                $contentFieldName = $theValue['source'] ?? $trimmedKey;

                if (array_key_exists('fields.', $theValue)) {
                    $content[$contentFieldName] = $this->cObjGet($theValue['fields.'], '', $nullableFieldsIfEmpty);
                }

                if (isset($theValue['dataProcessing.'])) {
                    $content[$trimmedKey] = $this->processFieldWithDataProcessing(
                        ['dataProcessing.' => $theValue['dataProcessing.']]
                    );
                }
            }
        }

        return $content;
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

        foreach ($this->findAsKeys($dataProcessing) as $value) {
            if (isset($data[$value])) {
                return $data[$value];
            }
        }

        return null;
    }

    /**
     * Collects all 'as' alias keys from the top-level dataProcessing processor configs.
     *
     * @param array $dataProcessing
     * @return array
     */
    private function findAsKeys(array $dataProcessing): array
    {
        $asKeys = [];
        foreach ($dataProcessing['dataProcessing.'] ?? [] as $value) {
            if (is_array($value) && isset($value['as'])) {
                $asKeys[] = $value['as'];
            }
        }
        return $asKeys;
    }
}
