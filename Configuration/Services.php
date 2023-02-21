<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

use FriendsOfTYPO3\Headless\Form\Service\FormTranslationService;
use FriendsOfTYPO3\Headless\Seo\XmlSitemap\XmlSitemapRenderer;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use FriendsOfTYPO3\Headless\Utility\UrlUtility;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Form\Controller\FormFrontendController;
use TYPO3\CMS\FrontendLogin\Controller\LoginController;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autoconfigure()
        ->autowire()
        ->private();

    $toLoad = $services->load('FriendsOfTYPO3\\Headless\\', '../Classes/*');

    $excludes = ['../Classes/Seo/XmlSitemap/XmlSitemapRenderer.php'];

    if (!class_exists(FormFrontendController::class, false)) {
        $excludes = array_merge($excludes, [
            '../Classes/Form/*',
            '../Classes/XClass/Controller/FormFrontendController.php',
            '../Classes/XClass/FormRuntime.php',
        ]);
    }

    if (!class_exists(LoginController::class, false)) {
        $excludes = array_merge($excludes, [
            '../Classes/XClass/Controller/LoginController.php',
        ]);
    }

    $toLoad->exclude($excludes);

    $services->set(HeadlessFrontendUrlInterface::class, UrlUtility::class)->autowire(false);
    $services->set(XmlSitemapRenderer::class)->public()->share(false);
    $services->set(FormTranslationService::class)->arg('$runtimeCache', service('cache.runtime'))->public();
};
