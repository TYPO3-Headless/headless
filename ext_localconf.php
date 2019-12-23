<?php
defined('TYPO3_MODE') || die();

call_user_func(
    function () {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe']['headless'] =
            \FriendsOfTYPO3\Headless\Hooks\IntScriptEncoderHook::class . '->performExtraJsonEncoding';

        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'] = array_merge($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'], [
            'JSON' => \FriendsOfTYPO3\Headless\ContentObject\JsonContentObject::class,
        ]);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['headless'] = [
            'FriendsOfTYPO3\Headless\ViewHelpers'
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc'][] = \FriendsOfTYPO3\Headless\Hooks\TypolinkHook::class . '->handleLink';

        /** @var \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry $rendererRegistry */
        $rendererRegistry = \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance();
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\YouTubeRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\VimeoRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\AudioTagRenderer::class);
        $rendererRegistry->registerRendererClass(\FriendsOfTYPO3\Headless\Resource\Rendering\VideoTagRenderer::class);
        unset($rendererRegistry);
    }
);
