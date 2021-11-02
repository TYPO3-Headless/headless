<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Service\Parser;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;
use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

class PageJsonParser implements JsonParserInterface
{
    protected array $labels = [];
    protected array $pageRecord = [];
    protected ViewInterface $view;
    protected JsonViewDemandInterface $demand;
    protected ContentFetcher $contentFetcher;
    protected PageLayoutContext $pageLayoutContext;

    public function __construct(
        array $labels = [],
        array $pageRecord = [],
        ?ViewInterface $view = null,
        ?JsonViewDemandInterface $demand = null
    ) {
        $this->labels = $labels;
        $this->pageRecord = $pageRecord;
        $this->view = $view;
        $this->demand = $demand;
        $this->initializeContentContext($pageRecord);
    }

    protected function initializeContentContext(array $pageRecord): void
    {
        if (isset($pageRecord['uid'])) {
            /** @var PageLayoutContext $pageLayoutContext */
            $this->pageLayoutContext = GeneralUtility::makeInstance(
                PageLayoutContext::class,
                $pageRecord,
                GeneralUtility::makeInstance(BackendLayoutView::class)->getBackendLayoutForPage($pageRecord['uid'])
            );
            /** @var ContentFetcher $contentFetcher */
            $this->contentFetcher = GeneralUtility::makeInstance(ContentFetcher::class, $this->pageLayoutContext);
            $this->labels = $this->pageLayoutContext->getContentTypeLabels();
        }
    }

    public function parseJson($jsonArray): bool
    {
        $pageContent = [];
        foreach ($jsonArray as $type => $typeContents) {
            if (!empty($typeContents) && $type === $this->getContentTabName()) {
                $pageContent[$type] = [];
                foreach ($typeContents as $col => $colPosContents) {
                    $colNumber = (int)str_replace('colPos', '', $col);
                    if (!isset($pageContent[$type][$colNumber])) {
                        $pageContent[$type][$colNumber] = [];
                    }
                    $contentRecords = $this->getLanguageSynchronizedContent($colNumber);

                    foreach ($colPosContents as $contentElement) {
                        if ($contentElement === null) {
                            continue;
                        }

                        $databaseRow = $contentRecords[$contentElement['id']];
                        $pageContent[$type][$colNumber][] = $this->getElementArray(
                            $contentElement,
                            $databaseRow
                        );
                    }
                }
            } else {
                $pageContent[$this->getAggregatedName()][$type] = $typeContents;
            }
        }

        $pageContent[$this->getAggregatedName()] = json_encode($pageContent['page'], JSON_PRETTY_PRINT);
        $pageContent[$this->getRawTabName()] = json_encode($jsonArray, JSON_PRETTY_PRINT);
        $this->view->assignMultiple(
            [
                'data' => $pageContent,
                'tabs' => array_keys($pageContent),
                'parsedJson' => $jsonArray[$this->getAggregatedName()],
                'contentTabName' => $this->getContentTabName(),
                'rawTabName' => $this->getRawTabName(),
            ]
        );
        return true;
    }

    public function getContentTabName(): string
    {
        return 'content';
    }

    protected function getLanguageSynchronizedContent(int $colPosNumber): ?array
    {
        $records = $this->contentFetcher->getContentRecordsPerColumn(
            $colPosNumber,
            $this->demand->getLanguageId()
        );

        if ($records === []) {
            return null;
        }

        return $this->syncRecordsWithTranslation($records);
    }

    protected function syncRecordsWithTranslation(array $records): array
    {
        if ($this->demand->getLanguageId() > 0) {
            $syncedRecords = [];

            foreach ($records as $record) {
                switch ($this->demand->getSiteLanguage()->getFallbackType()) {
                    case 'free':
                        $syncedRecords[$record['uid']] = $record;
                        break;
                    case 'fallback':
                    case 'strict':
                        if ($record['l10n_source'] > 0) {
                            $syncedRecords[$record['l10n_source']] = $record;
                        } else {
                            $syncedRecords[$record['uid']] = $record;
                        }
                        break;
                }
            }

            return $syncedRecords;
        }

        $recordKeys = array_column($records, 'uid');
        return $recordKeys !== [] ? array_combine($recordKeys, $records) : [];
    }

    protected function getElementArray(array $arrayFromJson, array $contentData, bool $addJson = true): array
    {
        $contentElement = [
            'uid' => $contentData['uid'],
            'sectionId' => $this->getSectionId($contentData),
            'CType' => $this->labels[$contentData['CType']] ?: $contentData['CType'],
            'title' => $this->getElementTitle($contentData),
            'hidden' => $contentData['hidden']
        ];

        if ($addJson) {
            $contentElement['data'] = json_encode($arrayFromJson, JSON_PRETTY_PRINT);
        }

        return $contentElement;
    }

    public function getSectionId(array $data): string
    {
        return 'section-' . $data['uid'];
    }

    public function getElementTitle(array $data): string
    {
        return $data['header'] ?: $data['title'] ?: '';
    }

    public function getAggregatedName(): string
    {
        return 'page';
    }

    public function getRawTabName(): string
    {
        return 'raw';
    }

    public function setLabels(array $labels): void
    {
        $this->labels = $labels;
    }
}
