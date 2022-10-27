<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Service\Parser;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;
use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * @codeCoverageIgnore
 */
class DefaultJsonParser implements JsonParserInterface
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
    }

    public function parseJson($jsonArray): bool
    {
        $pageContent = [];
        foreach ($jsonArray as $type => $typeContents) {
            $pageContent[$type] = json_encode($typeContents, JSON_PRETTY_PRINT);
        }

        $pageContent[$this->getRawTabName()] = json_encode($jsonArray, JSON_PRETTY_PRINT);
        $this->view->assignMultiple(
            [
                'data' => $pageContent,
                'tabs' => array_keys($pageContent),
                'rawTabName' => $this->getRawTabName(),
            ]
        );

        return true;
    }

    public function getRawTabName(): string
    {
        return 'raw';
    }
}
