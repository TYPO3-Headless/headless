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
use TYPO3\CMS\Core\Information\Typo3Version;
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

defined('TYPO3') || die();

call_user_func(
    static function () {
        if ((new Typo3Version)->getMajorVersion() === 12) {
            ExtensionManagementUtility::addTypoScriptSetup(
                '
lib {
    parseFunc {
        htmlSanitize = 1
        makelinks = 1
        makelinks {
            http {
                keep = {$styles.content.links.keep}
                extTarget = {$styles.content.links.extTarget}
            }

            mailto {
                keep = path
            }
        }

        tags {
            a = TEXT
            a {
                current = 1
                typolink {
                    parameter.data = parameters:href
                    title.data = parameters:title
                    ATagParams.data = parameters:allParams
                    # the target attribute takes precedence over config.intTarget
                    target.ifEmpty.data = parameters:target
                    # the target attribute takes precedence over the constant (styles.content.links.extTarget)
                    # which takes precedence over config.extTarget
                    # do not pass extTarget as reference, as it might not be set resulting in the string being
                    # written to the target attribute
                    extTarget {
                        ifEmpty < config.extTarget
                        ifEmpty.override = {$styles.content.links.extTarget}
                        override.data = parameters:target
                    }
                }
            }
        }

        allowTags = {$styles.content.allowTags}
        denyTags = *
        # @deprecated since TYPO3 v12, remove with v13
        constants = 1
        nonTypoTagStdWrap {
            HTMLparser = 1
            HTMLparser {
                keepNonMatchedTags = 1
                htmlSpecialChars = 2
            }
        }
    }

    # Creates persistent ParseFunc setup for RTE content (which is mainly HTML) based on the "default" transformation.
    parseFunc_RTE < lib.parseFunc
    parseFunc_RTE {
        # Processing <ol>, <ul> and <table> blocks separately
        externalBlocks = article, aside, blockquote, div, dd, dl, footer, header, nav, ol, section, table, ul, pre, figure, figcaption
        externalBlocks {
            ol {
                stripNL = 1
                stdWrap.parseFunc =< lib.parseFunc
            }

            ul {
                stripNL = 1
                stdWrap.parseFunc =< lib.parseFunc
            }

            pre {
                stdWrap.parseFunc < lib.parseFunc
            }

            table {
                stripNL = 1
                stdWrap {
                    HTMLparser = 1
                    HTMLparser {
                        tags.table.fixAttrib.class {
                            default = contenttable
                            always = 1
                            list = contenttable
                        }

                        keepNonMatchedTags = 1
                    }
                }

                HTMLtableCells = 1
                HTMLtableCells {
                    # Recursive call to self but without wrapping non-wrapped cell content
                    default.stdWrap {
                        parseFunc =< lib.parseFunc_RTE
                        parseFunc.nonTypoTagStdWrap.encapsLines {
                            nonWrappedTag =
                            innerStdWrap_all.ifBlank =
                        }
                    }

                    addChr10BetweenParagraphs = 1
                }
            }

            div {
                stripNL = 1
                callRecursive = 1
            }

            article < .div
            aside < .div
            figure < .div
            figcaption {
                stripNL = 1
            }

            blockquote < .div
            footer < .div
            header < .div
            nav < .div
            section < .div
            dl < .div
            dd < .div

        }

        nonTypoTagStdWrap {
            HTMLparser = 1
            HTMLparser {
                keepNonMatchedTags = 1
                htmlSpecialChars = 2
            }

            encapsLines {
                encapsTagList = p,pre,h1,h2,h3,h4,h5,h6,hr,dt
                remapTag.DIV = P
                nonWrappedTag = P
                innerStdWrap_all.ifBlank = &nbsp;
            }
        }
    }
}
'
            );
        }

        $GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';

        $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder']['file'] = FileOrFolderLinkBuilder::class;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['headless'] = [
            'FriendsOfTYPO3\Headless\ViewHelpers'
        ];

        $features = GeneralUtility::makeInstance(Features::class);

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
