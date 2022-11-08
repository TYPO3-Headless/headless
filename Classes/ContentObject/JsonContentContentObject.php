<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function array_merge;
use function count;
use function is_array;
use function json_decode;
use function str_contains;
use function strpos;
use function trim;

use const JSON_FORCE_OBJECT;

/**
 * CONTENT_JSON Content object behaves & has the same options as standard TYPO3' Content
 * main difference is content is grouped by colPol field & encoded into JSON by default.
 *
 * CONTENT_JSON has the same options as CONTENT, also adds two new options for edge cases in json context
 *
 * ** merge ** option
 * New option allows to generate another CONTENT_JSON call in one definition & then merge both results into one dataset
 * (useful for handling slide feature of CONTENT cObject)
 *
 * for example:
 *
 * lib.content = CONTENT_JSON
 * lib.content {
 *    table = tt_content
 *    select {
 *        orderBy = sorting
 *        where = {#colPos} != 1
 *    }
 *    merge {
 *        table = tt_content
 *        select {
 *           orderBy = sorting
 *           where = {#colPos} = 1
 *       }
 *       slide = -1
 *    }
 *  }
 *
 * ** doNotGroupByColPos = 0(default)|1 **
 * Option allows return of flat array (without grouping by colPos) encoded into JSON
 *
 * lib.content = CONTENT_JSON
 * lib.content {
 *    table = tt_content
 *    select {
 *        orderBy = sorting
 *        where = {#colPos} != 1
 *    }
 *    doNotGroupByColPos = 1
 */
class JsonContentContentObject extends ContentContentObject
{
    private HeadlessUserInt $headlessUserInt;
    private JsonEncoderInterface $jsonEncoder;

    public function __construct(ContentObjectRenderer $cObj)
    {
        parent::__construct($cObj);
        $this->headlessUserInt = GeneralUtility::makeInstance(HeadlessUserInt::class);
        $this->jsonEncoder = GeneralUtility::makeInstance(JsonEncoder::class);
    }

    /**
     * @param array<string,mixed> $conf
     */
    public function render($conf = []): string
    {
        if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
            return '';
        }

        $theValue = $this->prepareValue($conf);

        if (isset($conf['merge.']) && is_array($conf['merge.'])) {
            $theValue = array_merge($theValue, $this->prepareValue($conf['merge.']));
        }

        $encodeFlags = 0;

        if ($theValue === [] && $this->isColPolsGroupingEnabled($conf)) {
            $encodeFlags |= JSON_FORCE_OBJECT;
        }

        $theValue = $this->jsonEncoder->encode($theValue, $encodeFlags);

        $wrap = $this->cObj->stdWrapValue('wrap', $conf ?? []);
        if ($wrap) {
            $theValue = $this->cObj->wrap($theValue, $wrap);
        }
        if (isset($conf['stdWrap.'])) {
            $theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
        }

        return $theValue;
    }

    /**
     * @param array<string, mixed> $contentElements
     * @param array<string, mixed> $conf
     * @return array<string,<array<int, mixed>>
     */
    protected function groupContentElementsByColPos(array $contentElements, array $conf): array
    {
        $data = [];

        foreach ($contentElements as $element) {
            if (str_contains($element, 'Oops, an error occurred!')) {
                continue;
            }

            if (strpos($element, '<!--INT_SCRIPT') !== false
                && strpos($element, HeadlessUserInt::STANDARD) === false) {
                $element = $this->headlessUserInt->wrap($element);
            }

            $element = json_decode($element);

            if ($this->isColPolsGroupingEnabled($conf) && $element->colPos >= 0) {
                $data['colPos' . $element->colPos][] = $element;
            } else {
                $data[] = $element;
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $conf
     * @return array<string, mixed>
     */
    private function prepareValue(array $conf): array
    {
        $frontendController = $this->getTypoScriptFrontendController();
        $theValue = [];
        $originalRec = $frontendController->currentRecord;
        // If the currentRecord is set, we register, that this record has invoked this function.
        // It should not be allowed to do this again then!!
        if ($originalRec) {
            if (isset($frontendController->recordRegister[$originalRec])) {
                ++$frontendController->recordRegister[$originalRec];
            } else {
                $frontendController->recordRegister[$originalRec] = 1;
            }
        }
        $conf['table'] = trim((string)$this->cObj->stdWrapValue('table', $conf ?? []));
        $conf['select.'] = !empty($conf['select.']) ? $conf['select.'] : [];
        $renderObjName = ($conf['renderObj'] ?? false) ? $conf['renderObj'] : '<' . $conf['table'];
        $renderObjKey = ($conf['renderObj'] ?? false) ? 'renderObj' : '';
        $renderObjConf = $conf['renderObj.'] ?? [];
        $slide = (int)$this->cObj->stdWrapValue('slide', $conf ?? []);
        if (!$slide) {
            $slide = 0;
        }
        $slideCollect = (int)$this->cObj->stdWrapValue('collect', $conf['slide.'] ?? []);
        if (!$slideCollect) {
            $slideCollect = 0;
        }
        $slideCollectReverse = (bool)$this->cObj->stdWrapValue('collectReverse', $conf['slide.'] ?? []);
        $slideCollectFuzzy = (bool)$this->cObj->stdWrapValue('collectFuzzy', $conf['slide.'] ?? []);
        if (!$slideCollect) {
            $slideCollectFuzzy = true;
        }
        $again = false;
        $tmpValue = '';

        do {
            $records = $this->cObj->getRecords($conf['table'], $conf['select.']);
            $cobjValue = [];
            if (!empty($records)) {
                // @deprecated since v11, will be removed in v12. Drop together with ContentObjectRenderer->currentRecordTotal
                $this->cObj->currentRecordTotal = count($records);
                $this->getTimeTracker()->setTSlogMessage('NUMROWS: ' . count($records));

                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class, $frontendController);
                $cObj->setParent($this->cObj->data, $this->cObj->currentRecord);
                $this->cObj->currentRecordNumber = 0;

                foreach ($records as $row) {
                    // Call hook for possible manipulation of database row for cObj->data
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content_content.php']['modifyDBRow'] ?? [] as $className) {
                        $_procObj = GeneralUtility::makeInstance($className);
                        $_procObj->modifyDBRow($row, $conf['table']);
                    }
                    $registerField = $conf['table'] . ':' . ($row['uid'] ?? 0);
                    if (!($frontendController->recordRegister[$registerField] ?? false)) {
                        $this->cObj->currentRecordNumber++;
                        $cObj->parentRecordNumber = $this->cObj->currentRecordNumber;
                        $frontendController->currentRecord = $registerField;
                        $this->cObj->lastChanged($row['tstamp'] ?? 0);
                        $cObj->start($row, $conf['table'], $this->request);
                        $tmpValue = $cObj->cObjGetSingle($renderObjName, $renderObjConf, $renderObjKey);
                        $cobjValue[] = $tmpValue;
                    }
                }
            }
            if ($slideCollectReverse) {
                $theValue = array_merge($cobjValue, $theValue);
            } else {
                $theValue = array_merge($theValue, $cobjValue);
            }
            if ($slideCollect > 0) {
                $slideCollect--;
            }
            if ($slide) {
                if ($slide > 0) {
                    $slide--;
                }
                $conf['select.']['pidInList'] = $this->cObj->getSlidePids(
                    $conf['select.']['pidInList'] ?? '',
                    $conf['select.']['pidInList.'] ?? [],
                );
                if (isset($conf['select.']['pidInList.'])) {
                    unset($conf['select.']['pidInList.']);
                }
                $again = (string)$conf['select.']['pidInList'] !== '';
            }
        } while ($again && $slide && ((string)$tmpValue === '' && $slideCollectFuzzy || $slideCollect));

        $theValue = $this->groupContentElementsByColPos($theValue, $conf);
        // Restore
        $frontendController->currentRecord = $originalRec;
        if ($originalRec) {
            --$frontendController->recordRegister[$originalRec];
        }

        return $theValue;
    }

    private function isColPolsGroupingEnabled(array $conf): bool
    {
        return !isset($conf['doNotGroupByColPos']) || (int)$conf['doNotGroupByColPos'] === 0;
    }
}
