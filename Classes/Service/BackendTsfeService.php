<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Service;

use FriendsOfTYPO3\Headless\Dto\JsonViewDemandInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @codeCoverageIgnore
 */
class BackendTsfeService implements BackendTsfeServiceInterface
{
    private array $backendExtensionConfiguration = [];

    public function getPageFromTsfe(
        JsonViewDemandInterface $demand,
        JsonViewConfigurationServiceInterface $configurationService,
        array $settings
    ): string {
        $backendRequest = $GLOBALS['TYPO3_REQUEST'];
        $this->useFrontendExtensionConfiguration();
        $GLOBALS['TYPO3_REQUEST'] = $this->getFrontendRequest($demand, $configurationService);

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

    public function getFrontendRequest(
        JsonViewDemandInterface $demand,
        JsonViewConfigurationServiceInterface $configurationService
    ): ServerRequest {
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        if ($demand->getFeGroup() > 0) {
            $feUser->user = [
                'usergroup' => $demand->getFeGroup()
            ];
        }

        $frontendRequest = new ServerRequest();
        $pageTypeArguments = $configurationService->getPageTypeArguments($demand);

        return $frontendRequest
            ->withQueryParams($pageTypeArguments)
            ->withAttribute('routing', $pageTypeArguments)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $demand->getSite())
            ->withAttribute('language', $demand->getSiteLanguage())
            ->withAttribute('frontend.user', $feUser)
            ->withAttribute('noCache', true);
    }

    public function bootFrontendControllerForPage(
        int $pageId,
        JsonViewDemandInterface $demand,
        JsonViewConfigurationServiceInterface $configurationService,
        array $settings,
        bool $bootContent = false
    ): void {
        /** @var VisibilityAspect $visibilityAspect */
        $visibilityAspect = GeneralUtility::makeInstance(
            VisibilityAspect::class,
            true,
            $demand->isHiddenContentVisible()
        );
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $feUser->initializeUserSessionManager();
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
            $configurationService->getPageArgumentsForDemand($pageId, $demand),
            $feUser
        );
        $feRequest = $this->getFrontendRequest($demand, $configurationService, $settings);
        $feRequest->withAttribute('frontend.controller', $controller);
        $GLOBALS['TSFE'] = $controller;

        $controller->determineId($feRequest);
        $controller->getConfigArray();
        $controller->newCObj();
        $controller->no_cache = true;

        if (!$GLOBALS['TSFE']->sys_page instanceof PageRepository) {
            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        }

        if ($bootContent) {
            $controller->generatePage_preProcessing();
            $controller->preparePageContentGeneration($feRequest);
            $controller->generatePage_postProcessing();
        }
    }

    private function restoreBackendExtensionConfiguration()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions'] = $this->backendExtensionConfiguration;
    }

    /**
     * @return PageRenderer
     */
    protected function getPageRenderer(): PageRenderer
    {
        return GeneralUtility::makeInstance(PageRenderer::class);
    }
}
