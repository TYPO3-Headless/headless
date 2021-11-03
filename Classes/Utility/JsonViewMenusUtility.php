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
use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
class JsonViewMenusUtility
{
    protected UriBuilder $uriBuilder;

    /**
     * @param UriBuilder $uriBuilder
     */
    public function __construct(UriBuilder $uriBuilder)
    {
        $this->uriBuilder = $uriBuilder;
    }

    public function addLanguageMenu(BackendTemplateView $view, JsonViewDemandInterface $demand): void
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

    public function getTranslatedPageRecord(int $languageId, JsonViewDemandInterface $demand): array
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

    public function getCurrentDemandWithOverride(JsonViewDemandInterface $demand, array $override = []): array
    {
        $demandArguments = $demand->toArray();
        ArrayUtility::mergeRecursiveWithOverrule($demandArguments, $override);

        return $demandArguments;
    }

    public function addPageTypeMenu(BackendTemplateView $view, JsonViewDemandInterface $demand, array $yamlSettings = []): void
    {
        $menu = [];

        if ($yamlSettings['pageTypeModes'] === []) {
            return;
        }

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

    public function addShowHiddenContentOption(BackendTemplateView $view, JsonViewDemandInterface $demand): void
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

    public function addFrontendGroups(BackendTemplateView $view, JsonViewDemandInterface $demand): void
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

    public function getDatabaseFrontendGroups(): array
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
                ->execute()
                ->fetchAllAssociative();
        }

        return [];
    }

    protected function getDefaultMenuItemForUserGroups(JsonViewDemandInterface $demand): array
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
}
