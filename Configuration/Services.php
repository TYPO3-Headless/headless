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
use FriendsOfTYPO3\Headless\DataProcessing\FilesProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\FlexFormProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\GalleryProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\MenuProcessor;
use FriendsOfTYPO3\Headless\DataProcessing\RootSitesProcessor;
use FriendsOfTYPO3\Headless\Event\Listener\AfterLinkIsGeneratedListener;
use FriendsOfTYPO3\Headless\Event\Listener\AfterPagePreviewUriGeneratedListener;
use FriendsOfTYPO3\Headless\Event\Listener\LoginConfirmedEventListener;
use FriendsOfTYPO3\Headless\Form\Service\FormTranslationService;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use FriendsOfTYPO3\Headless\XClass\TemplateView;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Controller\FormFrontendController;
use TYPO3\CMS\FrontendLogin\Controller\LoginController;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $services = $configurator->services()
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();

    $toLoad = $services->load('FriendsOfTYPO3\\Headless\\', '../Classes/*');

    $excludes = [];
    $cmsFormsInstalled = class_exists(FormFrontendController::class, false);

    if (!$cmsFormsInstalled) {
        $excludes = [
            '../Classes/Form/*',
            '../Classes/XClass/Controller/FormFrontendController.php',
            '../Classes/XClass/FormRuntime.php',
        ];
    }

    $feloginInstalled = class_exists(LoginController::class, false);

    if (!$feloginInstalled) {
        $excludes = array_merge($excludes, [
            '../Classes/XClass/Controller/LoginController.php',
        ]);
    }

    $toLoad->exclude($excludes);

    $toLoad->set(JsonContentObject::class)->tag('frontend.contentobject', ['identifier' => 'JSON']);
    $toLoad->set(JsonContentContentObject::class)->tag('frontend.contentobject', ['identifier' => 'CONTENT_JSON']);
    $toLoad->set(BooleanContentObject::class)->tag('frontend.contentobject', ['identifier' => 'BOOL']);
    $toLoad->set(IntegerContentObject::class)->tag('frontend.contentobject', ['identifier' => 'INT']);
    $toLoad->set(FloatContentObject::class)->tag('frontend.contentobject', ['identifier' => 'FLOAT']);

    $services->set(HeadlessFrontendUrlInterface::class, UrlUtility::class)->autowire(false);
    $services->set(AfterLinkIsGeneratedListener::class)->tag(
        'event.listener',
        ['identifier' => 'headless/AfterLinkIsGenerated']
    );

    if ($feloginInstalled) {
        $services->set(LoginConfirmedEventListener::class)->tag(
            'event.listener',
            ['identifier' => 'headless/LoginConfirmedEvent']
        );
    }

    $services->set(AfterPagePreviewUriGeneratedListener::class)->tag(
        'event.listener',
        ['identifier' => 'headless/AfterPagePreviewUriGenerated']
    );

    if ($cmsFormsInstalled) {
        $services->set(FormTranslationService::class)->arg('$runtimeCache', service('cache.runtime'))->public();
    }

    $features = GeneralUtility::makeInstance(Features::class);

    if ($features->isFeatureEnabled('headless.overrideFluidTemplates')) {
        $templateService = $services->alias(
            \TYPO3\CMS\Fluid\View\TemplateView::class,
            TemplateView::class
        );

        $templateService->public();
    }

    foreach (
        [
            FilesProcessor::class => ['identifier' => 'headless-files', 'share' => true, 'public' => false],
            RootSitesProcessor::class => ['identifier' => 'headless-root-sites', 'share' => true, 'public' => false],
            MenuProcessor::class => ['identifier' => 'headless-menu', 'share' => false, 'public' => true],
            GalleryProcessor::class => ['identifier' => 'headless-gallery', 'share' => false, 'public' => false],
            DatabaseQueryProcessor::class => ['identifier' => 'headless-database-query', 'share' => false, 'public' => true],
            FlexFormProcessor::class => ['identifier' => 'headless-flex-form', 'share' => false, 'public' => false],
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
