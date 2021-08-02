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

namespace FriendsOfTYPO3\Headless\Service;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandDtoInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final class BackendTsfeService implements BackendTsfeServiceInterface
{
    /**
     * @var array
     */
    private $backendExtensionConfiguration = [];

    /**
     * @param int $pageId
     * @param JsonViewDemandDtoInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @param bool $bootContent
     */
    public function bootFrontendControllerForPage(
        int $pageId,
        JsonViewDemandDtoInterface $demand,
        JsonViewConfigurationServiceInterface $configurationService,
        array $settings,
        bool $bootContent = false
    ): void {
        /** @var VisibilityAspect $visibilityAspect */
        $visibilityAspect = GeneralUtility::makeInstance(VisibilityAspect::class, true, $demand->isHiddenContentVisible());
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        /** @var UserAspect $userAspect */
        $userAspect = GeneralUtility::makeInstance(UserAspect::class, $feUser);

        if ($demand->getFeGroup() > 0) {
            $feUser->user = [
                'usergroup' => $demand->getFeGroup()
            ];
        }

        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('visibility', $visibilityAspect);
        $context->setAspect('frontend.user', $userAspect);

        $controller = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            $context,
            $demand->getSite(),
            $demand->getSiteLanguage(),
            $configurationService->getPageArgumentsForDemand($demand, $settings, $pageId),
            $feUser
        );

        $controller->fetch_the_id();
        $controller->getConfigArray();
        $controller->settingLanguage();
        $controller->newCObj();

        if (!$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $GLOBALS['TSFE'] = $controller;
        }

        if (!$GLOBALS['TSFE']->sys_page instanceof PageRepository) {
            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        }

        if ($bootContent === true) {
            $controller->preparePageContentGeneration($this->getFrontendRequest($demand, $configurationService, $settings));
        }
    }

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @return string
     */
    public function getPageFromTsfe(JsonViewDemandDtoInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings): string
    {
        if ($GLOBALS['TSFE'] === null) {
            $this->bootFrontendControllerForPage(
                $demand->getPageId(),
                $demand,
                $configurationService,
                $settings,
                $configurationService->getBootContentFlagFromSettings($demand, $settings)
            );
        }

        $controller = $GLOBALS['TSFE'];

        $backendRequest = $GLOBALS['TYPO3_REQUEST'];
        $this->useFrontendExtensionConfiguration();
        $GLOBALS['TYPO3_REQUEST'] = $this->getFrontendRequest($demand, $configurationService, $settings);

        $pageContent = $controller->cObj->cObjGet($controller->pSetup) ?: '';
        if ($controller->pSetup['wrap'] ?? false) {
            $pageContent = $controller->cObj->wrap($pageContent, $controller->pSetup['wrap']);
        }
        if ($controller->pSetup['stdWrap.'] ?? false) {
            $pageContent = $controller->cObj->stdWrap($pageContent, $controller->pSetup['stdWrap.']);
        }

        $this->restoreBackendExtensionConfiguration();
        $GLOBALS['TYPO3_REQUEST'] = $backendRequest;

        return $pageContent;
    }

    /**
     * Trick TYPO3 into using plugins configuration in backend environment
     */
    private function useFrontendExtensionConfiguration()
    {
        $frontendExtensionConfiguration = [];
        $this->backendExtensionConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'];

        foreach ($this->backendExtensionConfiguration as $extension => $config) {
            if (isset($config['plugins'])) {
                $frontendExtensionConfiguration[$extension]['modules'] = $config['plugins'];
            }
        }

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'] = $frontendExtensionConfiguration;
    }

    private function restoreBackendExtensionConfiguration()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'] = $this->backendExtensionConfiguration;
    }

    /**
     * @param JsonViewDemandDtoInterface $demand
     * @param JsonViewConfigurationServiceInterface $configurationService
     * @param array $settings
     * @return ServerRequest
     */
    public function getFrontendRequest(JsonViewDemandDtoInterface $demand, JsonViewConfigurationServiceInterface $configurationService, array $settings)
    {
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        if ($demand->getFeGroup() > 0) {
            $feUser->user = [
                'usergroup' => $demand->getFeGroup()
            ];
        }

        $frontendRequest = new ServerRequest();
        $pageTypeArguments = $configurationService->getPageTypeArguments($settings, $demand);

        return $frontendRequest
            ->withQueryParams($pageTypeArguments)
            ->withAttribute('routing', $pageTypeArguments)
            ->withAttribute('applicationType', 1)
            ->withAttribute('site', $demand->getSite())
            ->withAttribute('language', $demand->getSiteLanguage())
            ->withAttribute('frontend.user', $feUser)
            ->withAttribute('noCache', true);
    }
}
