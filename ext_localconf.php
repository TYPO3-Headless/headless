<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

defined('TYPO3_MODE') || die();

call_user_func(
    static function () {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['FrontendBaseUrlInPagePreview'])) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['FrontendBaseUrlInPagePreview'] = false;
        }

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe']['headless'] =
            \FriendsOfTYPO3\Headless\Hooks\IntScriptEncoderHook::class . '->performExtraJsonEncoding';

        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], [
            'JSON' => \FriendsOfTYPO3\Headless\ContentObject\JsonContentObject::class,
            'INT' => \FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject::class,
            'BOOL' => \FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject::class,
        ]);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['headless'] = [
            'FriendsOfTYPO3\Headless\ViewHelpers'
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc'][] =
            \FriendsOfTYPO3\Headless\Hooks\TypolinkHook::class . '->handleLink';

        if (\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class)->isFeatureEnabled('FrontendBaseUrlInPagePreview')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Viewpage\Controller\ViewModuleController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\ViewModuleController::class
            ];
        }

        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('form')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Form\Controller\FormFrontendController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\FormFrontendController::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Form\Domain\Runtime\FormRuntime::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\FormRuntime::class
            ];
        }

        /** @var \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry $rendererRegistry */
        $rendererRegistry = \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance();
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\VimeoRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\AudioTagRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\VideoTagRenderer::class);
        unset($rendererRegistry);
    }
);
