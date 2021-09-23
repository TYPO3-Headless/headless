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

namespace FriendsOfTYPO3\Headless\Controller;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;
use FriendsOfTYPO3\Headless\Service\BackendTsfeService;
use FriendsOfTYPO3\Headless\Service\JsonViewConfigurationService;
use FriendsOfTYPO3\Headless\Utility\JsonViewMenusUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\ContentFetcher;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;

class JsonViewController extends ActionController
{
    public const MODULE_NAME = 'web_HeadlessJsonview';

    /**
     * @var JsonViewDemandInterface
     */
    protected $demand;

    /**
     * @var bool
     */
    protected $bootContent = false;

    /**
     * @var array
     */
    protected $labels = [];

    /**
     * @var array
     */
    protected $moduleSettings = [];

    /**
     * @var JsonViewMenusUtility
     */
    protected $jsonViewMenusUtility;

    /**
     * @var string
     */
    protected $defaultViewObjectName = \TYPO3\CMS\Backend\View\BackendTemplateView::class;

    /**
     * @var JsonViewConfigurationService
     */
    protected $configurationService;

    /**
     * @var BackendTsfeService
     */
    protected $backendTsfeService;

    /**
     * @var ExtensionService
     */
    protected $extensionService;

    /**
     * @param JsonViewMenusUtility $jsonViewMenusUtility
     * @param JsonViewConfigurationService $configurationService
     * @param BackendTsfeService $backendTsfeService
     * @param ExtensionService $extensionService
     */
    public function __construct(
        JsonViewMenusUtility $jsonViewMenusUtility,
        JsonViewConfigurationService $configurationService,
        BackendTsfeService $backendTsfeService,
        ExtensionService $extensionService
    ) {
        $this->jsonViewMenusUtility = $jsonViewMenusUtility;
        $this->configurationService = $configurationService;
        $this->backendTsfeService = $backendTsfeService;
        $this->extensionService = $extensionService;
    }

    protected function initializeAction()
    {
        $this->moduleSettings = $this->configurationService->getSettings();
        $this->demand = $this->configurationService->createDemandWithPluginNamespace(
            $this->extensionService->getPluginNamespace('Headless', self::MODULE_NAME)
        );
        $this->bootContent = $this->configurationService->getBootContentFlagFromSettings();
    }

    protected function initializeView(ViewInterface $view): void
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $pageRenderer = $view->getModuleTemplate()->getPageRenderer();
            $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
            $pageRenderer->addCssFile('EXT:headless/Resources/Public/Css/prism.css');
            $pageRenderer->addCssFile('EXT:headless/Resources/Public/Css/JsonView.css');
            $pageRenderer->addJsFile('EXT:headless/Resources/Public/JavaScript/prism.js');
            $pageRenderer->addJsFile('EXT:headless/Resources/Public/JavaScript/JsonView.js');

            if ($this->demand->isInitialized()) {
                $this->jsonViewMenusUtility->addLanguageMenu($view, $this->demand);
                $this->jsonViewMenusUtility->addFrontendGroups($view, $this->demand);
                $this->jsonViewMenusUtility->addPageTypeMenu($view, $this->demand, $this->moduleSettings);
                $this->jsonViewMenusUtility->addShowHiddenContentOption($view, $this->demand);
            }

            $this->view->assignMultiple(
                [
                    'contentTabName' => $this->configurationService->getContentTabName(),
                    'rawTabName' => $this->configurationService->getRawTabName(),
                    'showContent' => (int)$this->demand->isHiddenContentVisible(),
                    'translationFile' => $this->configurationService->getDefaultModuleTranslationFile() . 'module.'
                ]
            );
        }
    }

    public function mainAction(): void
    {
        $tabs = [];
        $pageContent = [];

        if ($this->getBackendUser() === null) {
            $this->view->assign('error', $this->getModuleTranslation('module.error_header'));
            return;
        }

        $pageRecord = $this->getPageRecord();
        $this->view->assign('pageRecord', $pageRecord);

        if ($this->isPageValid($pageRecord) === false) {
            return;
        }

        $this->backendTsfeService->bootFrontendControllerForPage(
            (int)$pageRecord['uid'],
            $this->demand,
            $this->configurationService,
            $this->moduleSettings,
            $this->bootContent
        );

        if (
            $GLOBALS['TSFE'] === null ||
            !isset($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_headless.']['staticTemplate']) ||
            (bool)$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_headless.']['staticTemplate'] === false
        ) {
            $this->view->assign('error', $this->getModuleTranslation('module.error.headless_or_tsfe'));
        }

        /** @var PageLayoutContext $pageLayoutContext */
        $pageLayoutContext = GeneralUtility::makeInstance(
            PageLayoutContext::class,
            $pageRecord,
            GeneralUtility::makeInstance(BackendLayoutView::class)->getBackendLayoutForPage($pageRecord['uid'])
        );
        /** @var ContentFetcher $contentFetcher */
        $contentFetcher = GeneralUtility::makeInstance(ContentFetcher::class, $pageLayoutContext);
        $this->labels = $pageLayoutContext->getContentTypeLabels();

        $pageJson = $this->backendTsfeService->getPageFromTsfe(
            $this->demand,
            $this->configurationService,
            $this->moduleSettings
        );
        $jsonArray = json_decode($pageJson, true);

        if (!is_array($jsonArray) || $jsonArray === []) {
            return;
        }

        foreach ($jsonArray as $type => $typeContents) {
            $tabs[] = $type;
            $pageContent[$type] = [];

            if ($type === $this->configurationService->getContentTabName()) {
                foreach ($typeContents as $col => $colPosContents) {
                    $colNumber = str_replace('colPos', '', $col);
                    $records = $contentFetcher->getContentRecordsPerColumn(
                        (int)$colNumber,
                        $this->demand->getLanguageId()
                    );

                    if ($records === []) {
                        continue;
                    }

                    $records = $this->syncRecordsWithTranslation($records);

                    foreach ($colPosContents as $contentElement) {
                        $databaseRow = $records[$contentElement['id']];
                        $pageContent[$type][$colNumber][] = $this->getElementArray(
                            $contentElement,
                            $databaseRow
                        );
                    }
                }
            } else {
                $pageContent[$type] = json_encode($typeContents, JSON_PRETTY_PRINT);
            }
        }

        $tabs[] = $this->configurationService->getRawTabName();
        $pageContent[$this->configurationService->getRawTabName()] = json_encode($jsonArray, JSON_PRETTY_PRINT);

        $this->view->assignMultiple(
            [
                'data' => $pageContent,
                'columns' => $this->getColumnLabels($pageLayoutContext),
                'tabs' => $tabs,
                'page' => $this->configurationService->getPageDataFromApi($jsonArray)
            ]
        );
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getModuleTranslation(string $key): string
    {
        return $this->getLanguageService()->sL($this->configurationService->getDefaultModuleTranslationFile() . $key);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getPageRecord(): array
    {
        $pageRecord = BackendUtility::readPageAccess(
            $this->demand->getPageId(),
            $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW)
        );

        return is_array($pageRecord) ? $pageRecord : [];
    }

    protected function isPageValid(array $pageRecord): bool
    {
        if (isset($pageRecord['uid'])) {
            if (!in_array((int)$pageRecord['doktype'], $this->configurationService->getDisallowedDoktypes())) {
                return true;
            }

            $this->view->assign('error', $this->getModuleTranslation('module.error.doktype_not_supported'));
            return false;
        }

        $this->view->assign('error', $this->getModuleTranslation('module.error.page_inaccessible'));
        return false;
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
            'sectionId' => $this->configurationService->getSectionId($contentData),
            'CType' => $this->labels[$contentData['CType']] ?: $contentData['CType'],
            'title' => $this->configurationService->getElementTitle($contentData),
            'hidden' => $contentData['hidden']
        ];

        if ($addJson) {
            $contentElement['data'] = json_encode($arrayFromJson, JSON_PRETTY_PRINT);
        }

        return $contentElement;
    }

    protected function getColumnLabels(PageLayoutContext $context): array
    {
        $columns = [];

        foreach ($context->getBackendLayout()->getUsedColumns() as $columnPos => $columnLabel) {
            $columns[$columnPos] = $GLOBALS['LANG']->sL($columnLabel);
        }

        return $columns;
    }
}
