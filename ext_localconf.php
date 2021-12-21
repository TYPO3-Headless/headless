<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2021
 */

defined('TYPO3_MODE') || die();

call_user_func(
    static function () {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], [
            'JSON' => \FriendsOfTYPO3\Headless\ContentObject\JsonContentObject::class,
            'CONTENT_JSON' => \FriendsOfTYPO3\Headless\ContentObject\JsonContentContentObject::class,
            'INT' => \FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject::class,
            'BOOL' => \FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject::class,
        ]);
        $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder']['file'] = \FriendsOfTYPO3\Headless\Hooks\FileOrFolderLinkBuilder::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['headless'] = [
            'FriendsOfTYPO3\Headless\ViewHelpers'
        ];

        $features = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Features::class);

        if ($features->isFeatureEnabled('headless.frontendUrls')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Routing\PageRouter::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Routing\PageRouter::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Frontend\Typolink\PageLinkBuilder::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Typolink\PageLinkBuilder::class,
            ];
        }

        if ($features->isFeatureEnabled('headless.storageProxy')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\Driver\LocalDriver::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\ResourceLocalDriver::class
            ];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Extbase\Service\ImageService::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\ImageService::class
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

        if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('felogin')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\FrontendLogin\Controller\LoginController::class] = [
                'className' => FriendsOfTYPO3\Headless\XClass\Controller\LoginController::class
            ];
        }

        if ($features->isFeatureEnabled('headless.supportOldPageOutput')) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc'][] =
                \FriendsOfTYPO3\Headless\Hooks\TypolinkHook::class . '->handleLink';
        }

        $rendererRegistry = \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance();
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\VimeoRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\AudioTagRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\VideoTagRenderer::class);
        unset($rendererRegistry);
    }
);
