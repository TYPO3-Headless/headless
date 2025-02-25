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
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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
 * offers two new options for edge cases in json context.
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
    private HeadlessUserInt $headlessUserInt;
    private JsonEncoderInterface $jsonEncoder;
    /**
     * @var mixed|object|\Psr\Log\LoggerAwareInterface|\TYPO3\CMS\Core\SingletonInterface|TimeTracker|(TimeTracker&\Psr\Log\LoggerAwareInterface)|(TimeTracker&\TYPO3\CMS\Core\SingletonInterface)|null
     */
    private TimeTracker $timeTracker;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct()
    {
        $this->headlessUserInt = GeneralUtility::makeInstance(HeadlessUserInt::class);
        $this->jsonEncoder = GeneralUtility::makeInstance(JsonEncoder::class);
        $this->timeTracker = GeneralUtility::makeInstance(TimeTracker::class);
        $this->eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
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

        $groupingEnabled = $this->isColPolsGroupingEnabled($conf);

        foreach ($contentElements as $element) {
            if ($element === '' || str_contains($element, 'Oops, an error occurred!')) {
                continue;
            }

            if (str_contains($element, '<!--INT_SCRIPT') && !str_contains($element, HeadlessUserInt::STANDARD)) {
                $element = $this->headlessUserInt->wrap($element);
            }

            $element = json_decode($element, true);

            if ($element === []) {
                continue;
            }

            $colPos = $this->getColPosFromElement($groupingEnabled, $element);

            if ($groupingEnabled && $colPos >= 0) {
                $data['colPos' . $colPos][] = $element;
            } else {
                $data[] = $element;
            }
        }

        if ($groupingEnabled && $this->isSortByBackendLayoutEnabled($conf)) {
            $backendLayoutView = GeneralUtility::makeInstance(BackendLayoutView::class);
            $backendLayout = $backendLayoutView->getSelectedBackendLayout($this->request->getAttribute('routing')->getPageId());

            $sorted = [];
            foreach ($backendLayout['__colPosList'] ?? [] as $value) {
                $sorted['colPos' . $value] = $data['colPos' . $value];
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
    private function prepareValue(array $conf): array
    {
        $t3v13andAbove = (new Typo3Version())->getMajorVersion() >= 13;

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
            if ($t3v13andAbove) {
                $modifyRecordsEvent = $this->eventDispatcher->dispatch(
                    new \TYPO3\CMS\Frontend\ContentObject\Event\ModifyRecordsAfterFetchingContentEvent(
                        $this->cObj->getRecords($conf['table'], $conf['select.']),
                        json_encode($theValue, JSON_THROW_ON_ERROR),
                        $slide,
                        $slideCollect,
                        $slideCollectReverse,
                        $slideCollectFuzzy,
                        $conf
                    )
                );

                $records = $modifyRecordsEvent->getRecords();
                $theValue = json_decode($modifyRecordsEvent->getFinalContent(), true, 512, JSON_THROW_ON_ERROR);
                $slide = $modifyRecordsEvent->getSlide();
                $slideCollect = $modifyRecordsEvent->getSlideCollect();
                $slideCollectReverse = $modifyRecordsEvent->getSlideCollectReverse();
                $slideCollectFuzzy = $modifyRecordsEvent->getSlideCollectFuzzy();
                $conf = $modifyRecordsEvent->getConfiguration();
            } else {
                $records = $this->cObj->getRecords($conf['table'], $conf['select.']);
            }
            $cobjValue = [];
            if (!empty($records)) {
                $this->timeTracker->setTSlogMessage('NUMROWS: ' . count($records));

                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class, $frontendController);
                $cObj->setParent($this->cObj->data, $this->cObj->currentRecord);
                $this->cObj->currentRecordNumber = 0;

                foreach ($records as $row) {
                    if (!$t3v13andAbove) {
                        // Call hook for possible manipulation of database row for cObj->data
                        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content_content.php']['modifyDBRow'] ?? [] as $className) {
                            $_procObj = GeneralUtility::makeInstance($className);
                            $_procObj->modifyDBRow($row, $conf['table']);
                        }
                    }
                    $registerField = $conf['table'] . ':' . ($row['uid'] ?? 0);
                    if (!($frontendController->recordRegister[$registerField] ?? false)) {
                        $this->cObj->currentRecordNumber++;
                        $cObj->parentRecordNumber = $this->cObj->currentRecordNumber;
                        $frontendController->currentRecord = $registerField;
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
        $frontendController->currentRecord = $originalRec;
        if ($originalRec) {
            --$frontendController->recordRegister[$originalRec];
        }

        return $theValue;
    }

    private function isSortByBackendLayoutEnabled(array $conf): bool
    {
        return isset($conf['sortByBackendLayout']) && (int)$conf['sortByBackendLayout'] === 1;
    }

    private function isColPolsGroupingEnabled(array $conf): bool
    {
        return !isset($conf['doNotGroupByColPos']) || (int)$conf['doNotGroupByColPos'] === 0;
    }

    private function returnSingleRowEnabled(array $conf): bool
    {
        return isset($conf['returnSingleRow']) && (int)$conf['returnSingleRow'] === 1;
    }

    private function getColPosFromElement(bool $groupingEnabled, array $element): int
    {
        if ($groupingEnabled && !array_key_exists('colPos', $element)) {
            throw new RuntimeException('Content element by ID: "' . ($element['id'] ?? 0) . '" does not have "colPos" field defined. Disable grouping or fix TypoScript definition of the element.', 1739347200);
        }

        return (int)($element['colPos'] ?? 0);
    }
}
