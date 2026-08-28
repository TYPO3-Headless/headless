<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\ContentObject\BooleanContentObject;
use FriendsOfTYPO3\Headless\ContentObject\FloatContentObject;
use FriendsOfTYPO3\Headless\ContentObject\IntegerContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentContentObject;
use FriendsOfTYPO3\Headless\ContentObject\JsonContentObject;
use FriendsOfTYPO3\Headless\DataProcessing\DatabaseQueryProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\ExtractPropertyProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\GalleryProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\LanguageMenuProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\MenuProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\DomainSchema;
use FriendsOfTYPO3\Headless\DataProcessing\RootSiteProcessing\SiteProvider;
use FriendsOfTYPO3\Headless\DataProcessing\RootSitesProcessor;
use FriendsOfTYPO3\Headless\Event\Listener\HeadlessHreflangGeneratorListener;
use FriendsOfTYPO3\Headless\Event\Listener\HeadlessRedirectResponseListener;
use FriendsOfTYPO3\Headless\Event\Listener\LoginConfirmedEventListener;
use FriendsOfTYPO3\Headless\Event\Listener\ProxyResourcePublicUrlListener;
use FriendsOfTYPO3\Headless\Form\Translator;
use FriendsOfTYPO3\Headless\Frontend\BackendEditorUrl;
use FriendsOfTYPO3\Headless\Json\JsonDecoder;
use FriendsOfTYPO3\Headless\Json\JsonDecoderInterface;
use FriendsOfTYPO3\Headless\Json\JsonEncoder;
use FriendsOfTYPO3\Headless\Json\JsonEncoderInterface;
use FriendsOfTYPO3\Headless\Redirects\Form\Element\QrCodeElement;
use FriendsOfTYPO3\Headless\Redirects\Form\Element\ShortUrlElement;
use FriendsOfTYPO3\Headless\Resource\Service\HeadlessImageService;
use FriendsOfTYPO3\Headless\Seo\MetaHandler;
use FriendsOfTYPO3\Headless\Seo\MetaHandlerInterface;
use FriendsOfTYPO3\Headless\Utility\FileUtility;
use FriendsOfTYPO3\Headless\Utility\FileUtilityInterface;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use FriendsOfTYPO3\Headless\View\HeadlessViewFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\ExpressionLanguage\Resolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Fluid\View\FluidViewFactory;
use TYPO3\CMS\Form\Controller\FormFrontendController;
use TYPO3\CMS\FrontendLogin\Controller\LoginController;
use TYPO3\CMS\Redirects\Service\RedirectService;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();

    $toLoad = $services->load('FriendsOfTYPO3\\Headless\\', '../Classes/*');

    $excludes = [];
    $cmsFormsInstalled = class_exists(FormFrontendController::class, false);

    if (!$cmsFormsInstalled) {
        $excludes = array_merge($excludes, [
            '../Classes/Form/*',
            '../Classes/XClass/Controller/FormFrontendController.php',
            '../Classes/XClass/FormRuntime.php',
        ]);
    }

    $feloginInstalled = class_exists(LoginController::class, false);

    if (!$feloginInstalled) {
        $excludes = array_merge($excludes, [
            '../Classes/XClass/Controller/LoginController.php',
        ]);
    }

    $redirectsInstalled = class_exists(RedirectService::class, false);

    if (!$redirectsInstalled) {
        $excludes[] = '../Classes/Redirects/*';
        $excludes[] = '../Classes/Middleware/RedirectModuleSourceUrlRewriter.php';
    }

    $toLoad->exclude($excludes);

    $toLoad->set(JsonContentObject::class)->tag('frontend.contentobject', ['identifier' => 'JSON']);
    $toLoad->set(JsonContentContentObject::class)->tag('frontend.contentobject', ['identifier' => 'CONTENT_JSON']);
    $toLoad->set(BooleanContentObject::class)->tag('frontend.contentobject', ['identifier' => 'BOOL']);
    $toLoad->set(IntegerContentObject::class)->tag('frontend.contentobject', ['identifier' => 'INT']);
    $toLoad->set(FloatContentObject::class)->tag('frontend.contentobject', ['identifier' => 'FLOAT']);

    $services->set(SiteProvider::class)->public();
    $services->set(DomainSchema::class)->public();
    $services->set(BackendEditorUrl::class)->public();
    $services->set(FileUtility::class)->public();
    $services->alias(FileUtilityInterface::class, FileUtility::class)->public();
    $services->set(JsonEncoderInterface::class, JsonEncoder::class)->public();
    $services->set(JsonDecoderInterface::class, JsonDecoder::class);
    $services->set('headless.expression_language.resolver.site', Resolver::class)
        ->args(['site', []]);

    $services->set(UrlUtility::class)
        ->arg('$resolver', service('headless.expression_language.resolver.site'));
    $services->alias(HeadlessFrontendUrlInterface::class, UrlUtility::class)->public();
    $services->alias(MetaHandlerInterface::class, MetaHandler::class)->public();

    if ($feloginInstalled) {
        $services->set(LoginConfirmedEventListener::class)->tag(
            'event.listener',
            ['identifier' => 'headless/LoginConfirmedEvent']
        );
    }

    if ($redirectsInstalled) {
        $services->set(HeadlessRedirectResponseListener::class)->tag(
            'event.listener',
            ['identifier' => 'headless/RedirectWasHit']
        );
        $services->set(QrCodeElement::class)->public();
        $services->set(ShortUrlElement::class)->public();
    }

    if (class_exists(\TYPO3\CMS\Seo\HrefLang\HrefLangGenerator::class)) {
        $services->set(HeadlessHreflangGeneratorListener::class)->tag(
            'event.listener',
            [
                'identifier' => 'headless/hreflangGenerator',
                'after' => 'typo3-seo/hreflangGenerator',
            ]
        );
    }

    if ($cmsFormsInstalled) {
        $services->set(Translator::class)->public();
    }

    $features = GeneralUtility::makeInstance(Features::class);
    if ($features->isFeatureEnabled('headless.overrideFluidTemplates')) {
        $services->set(HeadlessViewFactory::class)
            ->arg('$inner', service(FluidViewFactory::class))
            ->arg('$configurationManager', service(ConfigurationManagerInterface::class)->nullOnInvalid())
            ->public();
        $services->alias(ViewFactoryInterface::class, HeadlessViewFactory::class)->public();
    }

    if ($features->isFeatureEnabled('headless.storageProxy')) {
        $services->set(ProxyResourcePublicUrlListener::class)->tag(
            'event.listener',
            ['identifier' => 'headless/ProxyResourcePublicUrl']
        );
        $services->set(HeadlessImageService::class);
        $services->alias(ImageService::class, HeadlessImageService::class)->public();
    }

    foreach (
        [
            FilesProcessor::class => ['identifier' => 'headless-files', 'share' => true, 'public' => true],
            RootSitesProcessor::class => ['identifier' => 'headless-root-sites', 'share' => true, 'public' => false],
            MenuProcessor::class => ['identifier' => 'headless-menu', 'share' => false, 'public' => true],
            LanguageMenuProcessor::class => ['identifier' => 'headless-language-menu', 'share' => false, 'public' => true],
            GalleryProcessor::class => ['identifier' => 'headless-gallery', 'share' => false, 'public' => true],
            DatabaseQueryProcessor::class => [
                'identifier' => 'headless-database-query',
                'share' => false,
                'public' => true,
            ],
            FlexFormProcessor::class => ['identifier' => 'headless-flex-form', 'share' => false, 'public' => true],
            ExtractPropertyProcessor::class => [
                'identifier' => 'headless-extract-property',
                'share' => false,
                'public' => true,
            ],
        ] as $class => $processorConfig
    ) {
        $service = $services->set($class)
            ->tag('data.processor', ['identifier' => $processorConfig['identifier']])
            ->share($processorConfig['share']);

        if ($processorConfig['public']) {
            $service->public();
        }
    }
};
