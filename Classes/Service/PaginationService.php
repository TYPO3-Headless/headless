<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Service;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * @codeCoverageIgnore
 */
class PaginationService
{
    /**
     * @var array
     */
    protected $configuration = [
        'itemsPerPage' => 10,
        'maximumNumberOfLinks' => 99,
        'insertAbove' => false,
        'insertBelow' => true
    ];

    /**
     * @var QueryResultInterface
     */
    protected $objects;

    /**
     * @var int
     */
    protected $currentPage = 1;

    /**
     * @var int
     */
    protected $numberOfPages = 1;

    /**
     * @var int
     */
    protected $maximumNumberOfLinks = 99;

    /**
     * @var int
     */
    protected $initialOffset = 0;

    /**
     * @var int
     */
    protected $initialLimit = 0;

    /**
     * @var int
     */
    protected $recordId = 0;

    /**
     * @var int
     */
    protected $displayRangeStart = 0;

    /**
     * @var int
     */
    protected $displayRangeEnd = 0;

    /**
     * PaginationService constructor.
     * @param QueryResultInterface $objects
     * @param int $itemsPerPage
     * @param int $maximumNumberOfLinks
     * @param bool $insertAbove
     * @param bool $insertBelow
     */
    public function __construct(
        QueryResultInterface $objects,
        int $itemsPerPage = 10,
        int $maximumNumberOfLinks = 99,
        bool $insertAbove = false,
        bool $insertBelow = true
    ) {
        $this->objects = $objects;
        $this->configuration = [
            'itemsPerPage' => $itemsPerPage,
            'maximumNumberOfLinks' => $maximumNumberOfLinks,
            'insertAbove' => $insertAbove,
            'insertBelow' => $insertBelow
        ];
        $this->numberOfPages = (int)ceil(count($this->objects) / $itemsPerPage);
    }

    public function paginate(int $currentPage = 1): array
    {
        // set current page
        $this->currentPage = $currentPage;
        if ($this->currentPage < 1) {
            $this->currentPage = 1;
        }

        if ($this->currentPage > $this->numberOfPages) {
            // set $modifiedObjects to null if the page does not exist
            $modifiedObjects = null;
        } else {
            // modify query
            $itemsPerPage = (int)$this->configuration['itemsPerPage'];
            $query = $this->objects->getQuery();

            if ($this->currentPage === $this->numberOfPages && $this->initialLimit > 0) {
                $difference = $this->initialLimit - ((int)($itemsPerPage * ($this->currentPage - 1)));
                if ($difference > 0) {
                    $query->setLimit($difference);
                } else {
                    $query->setLimit($itemsPerPage);
                }
            } else {
                $query->setLimit($itemsPerPage);
            }

            if ($this->currentPage > 1) {
                $offset = (int)($itemsPerPage * ($this->currentPage - 1));
                $offset += $this->initialOffset;
                $query->setOffset($offset);
            } elseif ($this->initialOffset > 0) {
                $query->setOffset($this->initialOffset);
            }
            $modifiedObjects = $query->execute();
        }

        return [
            'objects' => $modifiedObjects,
            'configuration' => $this->configuration,
            'recordId' => $this->recordId,
            'pageId' => $this->getCurrentPageId(),
            'pagination' => $this->buildPagination()
        ];
    }

    protected function getCurrentPageId(): int
    {
        if (is_object($GLOBALS['TSFE'])) {
            return (int)$GLOBALS['TSFE']->id;
        }
        return 0;
    }

    /**
     * Returns an array with the keys "pages", "current", "numberOfPages", "nextPage" & "previousPage"
     */
    protected function buildPagination(): array
    {
        $this->calculateDisplayRange();
        $pages = [];
        for ($i = $this->displayRangeStart; $i <= $this->displayRangeEnd; $i++) {
            $pages[] = ['number' => $i, 'isCurrent' => $i === $this->currentPage];
        }
        $pagination = [
            'pages' => $pages,
            'current' => $this->currentPage,
            'numberOfPages' => $this->numberOfPages,
            'displayRangeStart' => $this->displayRangeStart,
            'displayRangeEnd' => $this->displayRangeEnd,
            'hasLessPages' => $this->displayRangeStart > 2,
            'hasMorePages' => $this->displayRangeEnd + 1 < $this->numberOfPages
        ];
        if ($this->currentPage < $this->numberOfPages) {
            $pagination['nextPage'] = $this->currentPage + 1;
        }
        if ($this->currentPage > 1) {
            $pagination['previousPage'] = $this->currentPage - 1;
        }
        return $pagination;
    }

    /**
     * If a certain number of links should be displayed, adjust before and after
     * amounts accordingly.
     */
    protected function calculateDisplayRange(): void
    {
        $maximumNumberOfLinks = $this->maximumNumberOfLinks;
        if ($maximumNumberOfLinks > $this->numberOfPages) {
            $maximumNumberOfLinks = $this->numberOfPages;
        }
        $delta = floor($maximumNumberOfLinks / 2);
        $this->displayRangeStart = $this->currentPage - $delta;
        $this->displayRangeEnd = $this->currentPage + $delta - ($maximumNumberOfLinks % 2 === 0 ? 1 : 0);
        if ($this->displayRangeStart < 1) {
            $this->displayRangeEnd -= $this->displayRangeStart - 1;
        }
        if ($this->displayRangeEnd > $this->numberOfPages) {
            $this->displayRangeStart -= $this->displayRangeEnd - $this->numberOfPages;
        }
        $this->displayRangeStart = (int)max($this->displayRangeStart, 1);
        $this->displayRangeEnd = (int)min($this->displayRangeEnd, $this->numberOfPages);
    }
}
