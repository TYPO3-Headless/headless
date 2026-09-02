<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessUserInt;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\Event\ModifyRecordsAfterFetchingContentEvent;

use function array_merge;
use function count;
use function is_array;
use function json_decode;
use function json_encode;
use function str_contains;
use function trim;

use const JSON_FORCE_OBJECT;

/**
 * This cObject basically behaves like TYPO3's CONTENT,
 * the main difference is that content elements are
 * grouped by colPol & encoded into JSON by default.
 *
 * CONTENT_JSON has the same options as CONTENT but also
 * offers a few new options for edge cases in json context.
 *
 * ** merge ** option
 * This option allows to generate another CONTENT_JSON call
 * in one definition & then merge both results into one
 * dataset (useful for handling slide feature of CONTENT cObject).
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
 * This option allows to return a flat array (without grouping
 * by colPos) but still encoded into JSON.
 *
 * lib.content = CONTENT_JSON
 * lib.content {
 *    table = tt_content
 *    select {
 *        orderBy = sorting
 *        where = {#colPos} != 1
 *    }
 *    doNotGroupByColPos = 1
 * }
 *
 * ** sortByBackendLayout = 0(default)|1 **
 * This option allows to return sorted CE by colPos with order by used backendLayout
 *
 * lib.content = CONTENT_JSON
 * lib.content {
 *    table = tt_content
 *    select {
 *        orderBy = sorting
 *    }
 *    sortByBackendLayout = 1
 * }
 *
 * ** returnSingleRow = 0(default)|1 **
 * This option allows to return only one row instead of array with one element
 *
 * lib.content = CONTENT_JSON
 * lib.content {
 *    table = tt_content
 *    select {
 *        orderBy = sorting
 *    }
 *    returnSingleRow = 1
 * }
 *
 * @codeCoverageIgnore
 */
class JsonContentContentObject extends ContentContentObject
{
    protected const CORE_ERROR_MARKER = 'Oops, an error occurred!';

    /**
     * @var array<string, int>
     */
    protected array $recordRegister = [];

    public function __construct(
        protected TimeTracker $timeTracker,
        protected EventDispatcherInterface $eventDispatcher,
        protected JsonEncoderInterface $jsonEncoder,
        protected HeadlessUserInt $headlessUserInt,
        protected BackendLayoutView $backendLayoutView
    ) {}

    /**
     * @param array<string,mixed> $conf
     */
    public function render($conf = []): string
    {
        if (!is_array($conf)) {
            $conf = [];
        }

        if (!empty($conf['if.']) && !$this->cObj->checkIf($conf['if.'])) {
            return '';
        }

        $theValue = $this->prepareValue($conf);

        if (isset($conf['merge.']) && is_array($conf['merge.'])) {
            $theValue = array_merge($theValue, $this->prepareValue($conf['merge.']));
        }

        $encodeFlags = 0;

        if ($theValue === [] && $this->isColPosGroupingEnabled($conf)) {
            $encodeFlags |= JSON_FORCE_OBJECT;
        }

        $theValue = $this->jsonEncoder->encode($theValue, $encodeFlags);

        $wrap = $this->cObj->stdWrapValue('wrap', $conf);
        if ($wrap) {
            $theValue = $this->cObj->wrap($theValue, $wrap);
        }
        if (isset($conf['stdWrap.'])) {
            $theValue = $this->cObj->stdWrap($theValue, $conf['stdWrap.']);
        }

        return $theValue;
    }

    /**
     * @param list<string> $contentElements
     * @param array<string, mixed> $conf
     * @return array<int|string, mixed>
     */
    protected function groupContentElementsByColPos(array $contentElements, array $conf): array
    {
        $data = [];

        $groupingEnabled = $this->isColPosGroupingEnabled($conf);

        foreach ($contentElements as $element) {
            if ($element === '' || str_contains($element, self::CORE_ERROR_MARKER)) {
                continue;
            }

            if (str_contains($element, '<!--INT_SCRIPT') && !str_contains($element, HeadlessUserInt::STANDARD)) {
                $element = $this->headlessUserInt->wrap($element);
            }

            $decoded = json_decode($element, true);

            if (!is_array($decoded) || $decoded === []) {
                continue;
            }
            $element = $decoded;

            if ($groupingEnabled) {
                $colPos = $this->getColPosFromElement($element);
                if ($colPos >= 0) {
                    $data['colPos' . $colPos][] = $element;
                    continue;
                }
            }

            $data[] = $element;
        }

        if ($groupingEnabled && $this->isSortByBackendLayoutEnabled($conf)) {
            $routing = $this->request->getAttribute('routing');
            $backendLayout = $routing !== null
                ? $this->backendLayoutView->getSelectedBackendLayout($routing->getPageId())
                : null;

            $sorted = [];
            foreach ($backendLayout['__colPosList'] ?? [] as $value) {
                $sorted['colPos' . $value] = $data['colPos' . $value] ?? [];
            }

            $data = $sorted;
        } elseif (!$groupingEnabled && $this->returnSingleRowEnabled($conf)) {
            return $data[0] ?? [];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $conf
     * @return array<string, mixed>
     */
    protected function prepareValue(array $conf): array
    {
        $theValue = [];
        $originalRec = $this->cObj->currentRecord;
        // If the currentRecord is set, we register, that this record has invoked this function.
        // It should not be allowed to do this again then!!
        if ($originalRec) {
            if (isset($this->recordRegister[$originalRec])) {
                ++$this->recordRegister[$originalRec];
            } else {
                $this->recordRegister[$originalRec] = 1;
            }
        }
        $conf['table'] = trim((string)$this->cObj->stdWrapValue('table', $conf));
        $conf['select.'] = !empty($conf['select.']) ? $conf['select.'] : [];
        $hasRenderObj = !empty($conf['renderObj']);
        $renderObjName = $hasRenderObj ? $conf['renderObj'] : '<' . $conf['table'];
        $renderObjKey = $hasRenderObj ? 'renderObj' : '';
        $renderObjConf = $conf['renderObj.'] ?? [];
        $slide = (int)$this->cObj->stdWrapValue('slide', $conf);
        $slideCollect = (int)$this->cObj->stdWrapValue('collect', $conf['slide.'] ?? []);
        $slideCollectReverse = (bool)$this->cObj->stdWrapValue('collectReverse', $conf['slide.'] ?? []);
        $slideCollectFuzzy = (bool)$this->cObj->stdWrapValue('collectFuzzy', $conf['slide.'] ?? []);
        if (!$slideCollect) {
            $slideCollectFuzzy = true;
        }
        $again = false;
        $tmpValue = '';

        do {
            $cobjValue = [];
            $encodedValue = json_encode($theValue, JSON_THROW_ON_ERROR);
            $modifyRecordsEvent = $this->eventDispatcher->dispatch(
                new ModifyRecordsAfterFetchingContentEvent(
                    $this->cObj->getRecords($conf['table'], $conf['select.']),
                    $encodedValue,
                    $slide,
                    $slideCollect,
                    $slideCollectReverse,
                    $slideCollectFuzzy,
                    $conf
                )
            );

            $records = $modifyRecordsEvent->getRecords();
            $finalContent = $modifyRecordsEvent->getFinalContent();
            if ($finalContent !== $encodedValue) {
                $theValue = json_decode($finalContent, true, 512, JSON_THROW_ON_ERROR);
            }
            $slide = $modifyRecordsEvent->getSlide();
            $slideCollect = $modifyRecordsEvent->getSlideCollect();
            $slideCollectReverse = $modifyRecordsEvent->getSlideCollectReverse();
            $slideCollectFuzzy = $modifyRecordsEvent->getSlideCollectFuzzy();
            $conf = $modifyRecordsEvent->getConfiguration();

            if ($records !== []) {
                $this->timeTracker->setTSlogMessage('NUMROWS: ' . count($records));

                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $cObj->setParent($this->cObj->data, $this->cObj->currentRecord);
                $recordNumber = 0;

                foreach ($records as $row) {
                    $registerField = $conf['table'] . ':' . ($row['uid'] ?? 0);
                    if (!($this->recordRegister[$registerField] ?? false)) {
                        $recordNumber++;
                        $cObj->parentRecordNumber = $recordNumber;
                        $this->cObj->currentRecord = $registerField;
                        $this->cObj->lastChanged($row['tstamp'] ?? 0);
                        $cObj->setRequest($this->request);
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
                $conf['select.']['pidInList'] = $this->cObj->getSlidePids(
                    $conf['select.']['pidInList'] ?? '',
                    $conf['select.']['pidInList.'] ?? [],
                );
                if (isset($conf['select.']['pidInList.'])) {
                    unset($conf['select.']['pidInList.']);
                }
                $again = (string)$conf['select.']['pidInList'] !== '';
            }
        } while ($again && $slide && (((string)$tmpValue === '' && $slideCollectFuzzy) || $slideCollect));

        $theValue = $this->groupContentElementsByColPos($theValue, $conf);
        // Restore
        $this->cObj->currentRecord = $originalRec;
        if ($originalRec) {
            --$this->recordRegister[$originalRec];
        }

        return $theValue;
    }

    /**
     * @param array<string, mixed> $conf
     */
    protected function isSortByBackendLayoutEnabled(array $conf): bool
    {
        return (int)($conf['sortByBackendLayout'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed> $conf
     */
    protected function isColPosGroupingEnabled(array $conf): bool
    {
        return (int)($conf['doNotGroupByColPos'] ?? 0) === 0;
    }

    /**
     * @param array<string, mixed> $conf
     */
    protected function returnSingleRowEnabled(array $conf): bool
    {
        return (int)($conf['returnSingleRow'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed> $element
     */
    protected function getColPosFromElement(array $element): int
    {
        if (!array_key_exists('colPos', $element)) {
            throw new RuntimeException('Content element by ID: "' . ($element['id'] ?? 0) . '" does not have "colPos" field defined. Disable grouping or fix TypoScript definition of the element.', 1739347200);
        }

        return (int)$element['colPos'];
    }
}
