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
use FriendsOfTYPO3\Headless\Service\Parser\PageJsonParser;
use FriendsOfTYPO3\Headless\Utility\JsonViewMenusUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;

/**
 * @codeCoverageIgnore
 */
class JsonViewController extends ActionController
{
    public const MODULE_NAME = 'web_HeadlessJsonview';

    protected JsonViewDemandInterface $demand;
    protected bool $bootContent = false;
    protected array $labels = [];
    protected array $moduleSettings = [];
    protected JsonViewMenusUtility $jsonViewMenusUtility;
    protected JsonViewConfigurationService $configurationService;
    protected BackendTsfeService $backendTsfeService;
    protected ExtensionService $extensionService;

    /**
     * @var string
     */
    protected $defaultViewObjectName = \TYPO3\CMS\Backend\View\BackendTemplateView::class;

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
                    'showContent' => (int)$this->demand->isHiddenContentVisible(),
                    'translationFile' => $this->configurationService->getDefaultModuleTranslationFile() . 'module.'
                ]
            );
        }
    }

    public function mainAction(): void
    {
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

        $pageJson = $this->backendTsfeService->getPageFromTsfe(
            $this->demand,
            $this->configurationService,
            $this->moduleSettings
        );

        $jsonArray = json_decode($pageJson, true);

        if (!is_array($jsonArray) || $jsonArray === []) {
            return;
        }

        /** @var PageJsonParser $parser */
        $parser = $this->configurationService->getParser($this->labels, $pageRecord, $this->view, $this->demand);

        if ($parser !== null) {
            $parser->parseJson($jsonArray);
        }

        $this->view->assign('columns', $this->getColumnLabels());
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

    protected function getColumnLabels(): array
    {
        $columns = [];

        if (isset($this->pageLayoutContext)) {
            foreach ($this->pageLayoutContext->getBackendLayout()->getUsedColumns() as $columnPos => $columnLabel) {
                $columns[$columnPos] = $GLOBALS['LANG']->sL($columnLabel);
            }
        }

        return $columns;
    }
}
