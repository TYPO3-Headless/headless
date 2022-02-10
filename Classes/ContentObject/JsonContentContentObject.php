<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use JsonException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function count;
use function json_decode;
use function json_encode;
use function strpos;
use function trim;

class JsonContentContentObject extends ContentContentObject
{
    private HeadlessUserInt $headlessUserInt;

    public function __construct(ContentObjectRenderer $cObj)
    {
        parent::__construct($cObj);
        $this->headlessUserInt = GeneralUtility::makeInstance(HeadlessUserInt::class);
    }

    /**
     * @param array<string,mixed> $conf
     */
    public function render($conf = []): string
    {
        if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
            return '';
        }

        $frontendController = $this->getFrontendController();
        $theValue = [];
        $originalRec = $frontendController->currentRecord;
        // If the currentRecord is set, we register, that this record has invoked this function.
        // It's should not be allowed to do this again then!!
        if ($originalRec) {
            ++$frontendController->recordRegister[$originalRec];
        }
        $conf['table'] = isset($conf['table.']) ? trim($this->cObj->stdWrap($conf['table'], $conf['table.'])) : trim($conf['table']);
        $conf['select.'] = !empty($conf['select.']) ? $conf['select.'] : [];
        $renderObjName = $conf['renderObj'] ?: '<' . $conf['table'];
        $renderObjKey = $conf['renderObj'] ? 'renderObj' : '';
        $renderObjConf = $conf['renderObj.'];
        $slide = isset($conf['slide.']) ? (int)$this->cObj->stdWrap($conf['slide'], $conf['slide.']) : (int)$conf['slide'];
        if (!$slide) {
            $slide = 0;
        }
        $slideCollect = isset($conf['slide.']['collect.']) ? (int)$this->cObj->stdWrap($conf['slide.']['collect'], $conf['slide.']['collect.']) : (int)$conf['slide.']['collect'];
        if (!$slideCollect) {
            $slideCollect = 0;
        }
        $slideCollectReverse = isset($conf['slide.']['collectReverse.']) ? (int)$this->cObj->stdWrap($conf['slide.']['collectReverse'], $conf['slide.']['collectReverse.']) : (int)$conf['slide.']['collectReverse'];
        $slideCollectReverse = (bool)$slideCollectReverse;
        $slideCollectFuzzy = isset($conf['slide.']['collectFuzzy.'])
            ? (bool)$this->cObj->stdWrap($conf['slide.']['collectFuzzy'], $conf['slide.']['collectFuzzy.'])
            : (bool)$conf['slide.']['collectFuzzy'];
        if (!$slideCollect) {
            $slideCollectFuzzy = true;
        }
        $again = false;
        $tmpValue = '';

        do {
            $records = $this->cObj->getRecords($conf['table'], $conf['select.']);
            $cobjValue = [];
            if (!empty($records)) {
                $this->cObj->currentRecordTotal = count($records);
                $this->getTimeTracker()->setTSlogMessage('NUMROWS: ' . count($records));

                /** @var ContentObjectRenderer $cObj */
                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $cObj->setParent($this->cObj->data, $this->cObj->currentRecord);
                $this->cObj->currentRecordNumber = 0;

                foreach ($records as $row) {
                    // Call hook for possible manipulation of database row for cObj->data
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content_content.php']['modifyDBRow'] ?? [] as $className) {
                        $_procObj = GeneralUtility::makeInstance($className);
                        $_procObj->modifyDBRow($row, $conf['table']);
                    }
                    if (!$frontendController->recordRegister[$conf['table'] . ':' . $row['uid']]) {
                        $this->cObj->currentRecordNumber++;
                        $cObj->parentRecordNumber = $this->cObj->currentRecordNumber;
                        $frontendController->currentRecord = $conf['table'] . ':' . $row['uid'];
                        $this->cObj->lastChanged($row['tstamp']);
                        $cObj->start($row, $conf['table']);
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
                $conf['select.']['pidInList'] = $this->cObj->getSlidePids($conf['select.']['pidInList'], $conf['select.']['pidInList.']);
                if (isset($conf['select.']['pidInList.'])) {
                    unset($conf['select.']['pidInList.']);
                }
                $again = (string)$conf['select.']['pidInList'] !== '';
            }
        } while ($again && $slide && (((string)$tmpValue === '' && $slideCollectFuzzy) || $slideCollect));

        $theValue = $this->groupContentElementsByColPos($theValue);
        // Restore
        $frontendController->currentRecord = $originalRec;
        if ($originalRec) {
            --$frontendController->recordRegister[$originalRec];
        }

        try {
            $theValue = json_encode($theValue, JSON_THROW_ON_ERROR);

            $wrap = $this->cObj->stdWrapValue('wrap', $conf ?? []);
            if ($wrap) {
                $theValue = $this->cObj->wrap($theValue, $wrap);
            }
            if (isset($conf['stdWrap.'])) {
                $theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
            }

            return  $theValue;
        } catch (JsonException $e) {
            return '';
        }
    }

    /**
     * @param array<string, mixed> $contentElements
     * @return array<string,<array<int, mixed>>
     */
    protected function groupContentElementsByColPos(array $contentElements): array
    {
        $data = [];

        foreach ($contentElements as $key => $element) {
            if (strpos($element, '<!--INT_SCRIPT') !== false
                && strpos($element, HeadlessUserInt::STANDARD) === false) {
                $element = $this->headlessUserInt->wrap($element);
            }

            $element = json_decode($element);
            if ($element->colPos >= 0) {
                $data['colPos' . $element->colPos][] = $element;
            }
        }

        return $data;
    }
}
