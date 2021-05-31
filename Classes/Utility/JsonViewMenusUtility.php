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

namespace FriendsOfTYPO3\Headless\Utility;

use FriendsOfTYPO3\Headless\Controller\JsonViewController;
use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDto;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class JsonViewMenusUtility
{
    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @param UriBuilder $uriBuilder
     */
    public function __construct(UriBuilder $uriBuilder)
    {
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * @param BackendTemplateView $view
     * @param JsonViewDemandDto $demand
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function addLanguageMenu(BackendTemplateView $view, JsonViewDemandDto $demand)
    {
        $menu = [];

        $languages = $demand->getSite()->getLanguages();
        foreach ($languages as $siteLanguage) {
            if ($this->getTranslatedPageRecord($siteLanguage->getLanguageId(), $demand)) {
                $item = [
                    'href' => (string)$this->uriBuilder->buildUriFromRoute(
                        JsonViewController::MODULE_NAME,
                        $this->getCurrentDemandWithOverride($demand, ['lang' => $siteLanguage->getLanguageId()])
                    ),
                    'title' => $siteLanguage->getTitle() . ' [' . $siteLanguage->getLanguageId() . ']',
                    'active' => $demand->getSiteLanguage() === $siteLanguage
                ];

                $menu[] = $item;
            }
        }

        if (count($menu) > 1) {
            $view->assign('languageMenu', $menu);
        }
    }

    /**
     * @param BackendTemplateView $view
     * @param JsonViewDemandDto $demand
     * @param array $yamlSettings
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function addPageTypeMenu(BackendTemplateView $view, JsonViewDemandDto $demand, array $yamlSettings = [])
    {
        if ($yamlSettings['pageTypeModes']) {
            $menu = [];

            foreach ($yamlSettings['pageTypeModes'] as $mode => $config) {
                if (isset($config['pageType'])) {
                    $menuItem = [
                        'title' => $config['title'] . ' [type=' . $config['pageType'] . '] ',
                        'href' => (string)$this->uriBuilder->buildUriFromRoute(
                            JsonViewController::MODULE_NAME,
                            $this->getCurrentDemandWithOverride($demand, ['pageTypeMode' => $mode])
                        )
                    ];

                    $menuItem['active'] = $demand->getPageTypeMode() === $mode;
                    $menu[] = $menuItem;
                }
            }

            $view->assign('pageTypesMenu', $menu);
        }
    }

    /**
     * @param BackendTemplateView $view
     * @param JsonViewDemandDto $demand
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function addShowHiddenContentOption(BackendTemplateView $view, JsonViewDemandDto $demand)
    {
        $menuItem = '';
        $contentOptions = [0, 1];

        foreach ($contentOptions as $option) {
            if ($demand->isHiddenContentVisible() != $option) {
                $menuItem = [
                    'title' => '',
                    'href' => (string)$this->uriBuilder->buildUriFromRoute(
                        JsonViewController::MODULE_NAME,
                        $this->getCurrentDemandWithOverride($demand, ['hidden' => $option])
                    )
                ];
            }
        }

        $view->assign('showHiddenContentOption', $menuItem);
    }

    /**
     * @param BackendTemplateView $view
     * @param JsonViewDemandDto $demand
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function addFrontendGroups(BackendTemplateView $view, JsonViewDemandDto $demand)
    {
        $menu = [];
        $groups = $this->getDatabaseFrontendGroups();

        if ($groups !== []) {
            $menu[] = $this->getDefaultMenuItemForUserGroups($demand);

            foreach ($this->getDatabaseFrontendGroups() as $group) {
                $menu[] = [
                    'href' => (string)$this->uriBuilder->buildUriFromRoute(
                        JsonViewController::MODULE_NAME,
                        $this->getCurrentDemandWithOverride($demand, ['feGroup' => $group['uid']])
                    ),
                    'title' => $group['title'] . ' [' . $group['uid'] . ']',
                    'active' => $demand->getFeGroup() === $group['uid']
                ];
            }
        }

        if (count($menu) > 1) {
            $view->assign('usergroupsMenu', $menu);
        }
    }

    /**
     * @return array
     */
    public function getDatabaseFrontendGroups()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $schemaManager = $connectionPool
            ->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME)
            ->getSchemaManager();

        if ($schemaManager->listTableColumns('fe_groups') !== []) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $connectionPool->getQueryBuilderForTable('fe_groups');

            return $queryBuilder
                ->select('uid', 'title')
                ->from('fe_groups')
                ->execute();
        }

        return [];
    }

    /**
     * @param JsonViewDemandDto $demand
     * @return string[]
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function getDefaultMenuItemForUserGroups(JsonViewDemandDto $demand)
    {
        return [
            'href' => (string)$this->uriBuilder->buildUriFromRoute(
                JsonViewController::MODULE_NAME,
                [
                    'id' => $demand->getPageId(),
                    'lang' => $demand->getLanguageId(),
                    'feGroup' => '',
                    'site' => $demand->getSite()->getIdentifier(),
                    'hidden' => $demand->isHiddenContentVisible()
                ]
            ),
            'title' => 'Show for all'
        ];
    }

    /**
     * @param int $languageId
     * @param JsonViewDemandDto $demand
     * @return mixed
     */
    public function getTranslatedPageRecord(int $languageId, JsonViewDemandDto $demand)
    {
        $targetSiteLanguage = $demand->getSite()->getLanguageById($languageId);
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($targetSiteLanguage);

        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('language', $languageAspect);

        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, $context);

        if ($languageId > 0) {
            return $pageRepository->getPageOverlay($demand->getPageId(), $languageId);
        }

        return $pageRepository->getPage($demand->getPageId());
    }

    /**
     * @param JsonViewDemandDto $demand
     * @param array $override
     * @return array
     */
    public function getCurrentDemandWithOverride(JsonViewDemandDto $demand, array $override = []): array
    {
        return array_merge($demand->getCurrentDemandArgumentsAsArray(), $override);
    }
}
