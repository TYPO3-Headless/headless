<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject;
use FriendsOfTYPO3\Headless\ContentObject\FloatContentObject;
use FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentObject;
use FriendsOfTYPO3\Headless\Hooks\FileOrFolderLinkBuilder;
use FriendsOfTYPO3\Headless\Hooks\TypolinkHook;
use FriendsOfTYPO3\Headless\Resource\Rendering\AudioTagRenderer;
use FriendsOfTYPO3\Headless\Resource\Rendering\VideoTagRenderer;
use FriendsOfTYPO3\Headless\Resource\Rendering\VimeoRenderer;
use FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer;
use FriendsOfTYPO3\Headless\XClass\ResourceLocalDriver;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Resource\Driver\LocalDriver;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Form\Controller\FormFrontendController;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Frontend\Typolink\PageLinkBuilder;
use TYPO3\CMS\FrontendLogin\Controller\LoginController;
use TYPO3\CMS\Workspaces\Controller\PreviewController;
use TYPO3\CMS\Workspaces\Preview\PreviewUriBuilder;

defined('TYPO3') || die();

call_user_func(
    static function () {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/2.x/';
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], [
            'JSON' => JsonContentObject::class,
            'CONTENT_JSON' => JsonContentContentObject::class,
            'INT' => IntegerContentObject::class,
            'FLOAT' => FloatContentObject::class,
            'BOOL' => BooleanContentObject::class,
        ]);
        $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder']['file'] = FileOrFolderLinkBuilder::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['headless'] = [
            'FriendsOfTYPO3\Headless\ViewHelpers'
        ];

        $features = GeneralUtility::makeInstance(Features::class);

        if ($features->isFeatureEnabled('headless.frontendUrls')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PageRouter::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Routing\PageRouter::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PageLinkBuilder::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Typolink\PageLinkBuilder::class,
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

        if (ExtensionManagementUtility::isLoaded('workspaces') && $features->isFeatureEnabled('headless.workspaces')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PreviewController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\PreviewController::class
            ];
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][PreviewUriBuilder::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Preview\PreviewUriBuilder::class
            ];
        }

        if ($features->isFeatureEnabled('headless.supportOldPageOutput')) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc'][] =
                TypolinkHook::class . '->handleLink';
        }

        $rendererRegistry = GeneralUtility::makeInstance(RendererRegistry::class);
        $rendererRegistry->registerRendererClass(YouTubeRenderer::class);
        $rendererRegistry->registerRendererClass(VimeoRenderer::class);
        $rendererRegistry->registerRendererClass(AudioTagRenderer::class);
        $rendererRegistry->registerRendererClass(VideoTagRenderer::class);
        unset($rendererRegistry);
    }
);
