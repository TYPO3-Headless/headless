<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FriendsOfTYPO3\Headless\Hooks\FileOrFolderLinkBuilder;
use FriendsOfTYPO3\Headless\Seo\MetaTag\EdgeMetaTagManager;
use FriendsOfTYPO3\Headless\Seo\MetaTag\Html5MetaTagManager;
use FriendsOfTYPO3\Headless\Seo\MetaTag\OpenGraphMetaTagManager;
use FriendsOfTYPO3\Headless\Seo\MetaTag\TwitterCardMetaTagManager;
use FriendsOfTYPO3\Headless\Resource\Rendering\AudioTagRenderer;
use FriendsOfTYPO3\Headless\Resource\Rendering\VideoTagRenderer;
use FriendsOfTYPO3\Headless\Resource\Rendering\VimeoRenderer;
use FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer;
use FriendsOfTYPO3\Headless\Seo\CanonicalGenerator;
use FriendsOfTYPO3\Headless\XClass\ResourceLocalDriver;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Form\Controller\FormFrontendController;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\FrontendLogin\Controller\LoginController;
use TYPO3\CMS\Workspaces\Controller\PreviewController;
use TYPO3\CMS\Workspaces\Preview\PreviewUriBuilder;
use TYPO3\CMS\Fluid\ViewHelpers\FormViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\TextfieldViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\TextareaViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\ButtonViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\CheckboxViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\CountrySelectViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\HiddenViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\PasswordViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\RadioViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\SelectViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\Select\OptgroupViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\Select\OptionViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\SubmitViewHelper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\UploadViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\FormViewHelper as HeadlessFormViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\TextfieldViewHelper as HeadlessTextfieldViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\TextareaViewHelper as HeadlessTextareaViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\ButtonViewHelper as HeadlessButtonViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\CheckboxViewHelper as HeadlessCheckboxViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\CountrySelectViewHelper as HeadlessCountrySelectViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\HiddenViewHelper as HeadlessHiddenViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\PasswordViewHelper as HeadlessPasswordViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\RadioViewHelper as HeadlessRadioViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\SelectViewHelper as HeadlessSelectViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\Select\OptgroupViewHelper as HeadlessOptgroupViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\Select\OptionViewHelper as HeadlessOptionViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\SubmitViewHelper as HeadlessSubmitViewHelper;
use FriendsOfTYPO3\Headless\XClass\ViewHelpers\Form\UploadViewHelper as HeadlessUploadViewHelper;

defined('TYPO3') || die();

call_user_func(
    static function () {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';

        $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder']['file'] = FileOrFolderLinkBuilder::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['headless'] = [
            'FriendsOfTYPO3\Headless\ViewHelpers'
        ];

        $features = GeneralUtility::makeInstance(Features::class);

        if (ExtensionManagementUtility::isLoaded('fluid')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][FormViewHelper::class] = [
                'className' => HeadlessFormViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TextfieldViewHelper::class] = [
                'className' => HeadlessTextfieldViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TextareaViewHelper::class] = [
                'className' => HeadlessTextareaViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][ButtonViewHelper::class] = [
                'className' => HeadlessButtonViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CheckboxViewHelper::class] = [
                'className' => HeadlessCheckboxViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][CountrySelectViewHelper::class] = [
                'className' => HeadlessCountrySelectViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][HiddenViewHelper::class] = [
                'className' => HeadlessHiddenViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PasswordViewHelper::class] = [
                'className' => HeadlessPasswordViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][RadioViewHelper::class] = [
                'className' => HeadlessRadioViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][SelectViewHelper::class] = [
                'className' => HeadlessSelectViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][OptgroupViewHelper::class] = [
                'className' => HeadlessOptgroupViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][OptionViewHelper::class] = [
                'className' => HeadlessOptionViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][SubmitViewHelper::class] = [
                'className' => HeadlessSubmitViewHelper::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][UploadViewHelper::class] = [
                'className' => HeadlessUploadViewHelper::class
            ];
        }

        if ($features->isFeatureEnabled('headless.storageProxy')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][LocalDriver::class] = [
                'className' => ResourceLocalDriver::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][ImageService::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\ImageService::class
            ];
        }

        if (ExtensionManagementUtility::isLoaded('form')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][FormFrontendController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\FormFrontendController::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][FormRuntime::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\FormRuntime::class
            ];
        }

        if (ExtensionManagementUtility::isLoaded('felogin')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][LoginController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\LoginController::class
            ];
        }

        if (ExtensionManagementUtility::isLoaded('workspaces')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PreviewController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\PreviewController::class
            ];
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PreviewUriBuilder::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Preview\PreviewUriBuilder::class
            ];
        }

        if (ExtensionManagementUtility::isLoaded('seo')) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\CMS\Frontend\Page\PageGenerator']['generateMetaTags']['canonical'] =
                CanonicalGenerator::class . '->handle';

            $metaTagManagerRegistry = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);
            $metaTagManagerRegistry->registerManager(
                'html5',
                Html5MetaTagManager::class
            );
            $metaTagManagerRegistry->registerManager(
                'edge',
                EdgeMetaTagManager::class
            );
            $metaTagManagerRegistry->registerManager(
                'opengraph',
                OpenGraphMetaTagManager::class
            );
            $metaTagManagerRegistry->registerManager(
                'twitter',
                TwitterCardMetaTagManager::class
            );
            unset($metaTagManagerRegistry);
        }

        $rendererRegistry = GeneralUtility::makeInstance(RendererRegistry::class);
        $rendererRegistry->registerRendererClass(YouTubeRenderer::class);
        $rendererRegistry->registerRendererClass(VimeoRenderer::class);
        $rendererRegistry->registerRendererClass(AudioTagRenderer::class);
        $rendererRegistry->registerRendererClass(VideoTagRenderer::class);
        unset($rendererRegistry);
    }
);
