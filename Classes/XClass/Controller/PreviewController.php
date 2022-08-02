<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass\Controller;

use FriendsOfTYPO3\Headless\Utility\FrontendBaseUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Information\Typo3Information;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\UnableToLinkToPageException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Workspaces\Service\WorkspaceService;

/**
 * This XClass allows you to render frontend URLs for workspaces
 *
 * @codeCoverageIgnore
 */
class PreviewController extends \TYPO3\CMS\Workspaces\Controller\PreviewController
{
    /**
     * Basically makes sure that the workspace preview is rendered.
     * The preview itself consists of three frames, so there are
     * only the frames-urls we have to generate here
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $liveUrl = false;
        $this->initializeView('Index');

        // Get all the GET parameters to pass them on to the frames
        $queryParameters = $request->getQueryParams();

        $previewWS = $queryParameters['previewWS'] ?? null;
        $this->pageId = (int)$queryParameters['id'];

        // Remove the GET parameters related to the workspaces module
        unset($queryParameters['route'], $queryParameters['token'], $queryParameters['previewWS']);

        // fetch the next and previous stage
        $workspaceItemsArray = $this->workspaceService->selectVersionsInWorkspace(
            $this->stageService->getWorkspaceId(),
            $filter = 1,
            $stage = -99,
            $this->pageId,
            $recursionLevel = 0,
            $selectionType = 'tables_modify'
        );
        [, $nextStage] = $this->stageService->getNextStageForElementCollection($workspaceItemsArray);
        [, $previousStage] = $this->stageService->getPreviousStageForElementCollection($workspaceItemsArray);
        $availableWorkspaces = $this->workspaceService->getAvailableWorkspaces();
        $activeWorkspace = $this->getBackendUser()->workspace;
        if ($previewWS !== null && array_key_exists($previewWS, $availableWorkspaces) && $activeWorkspace != $previewWS) {
            $activeWorkspace = $previewWS;
            $this->getBackendUser()->setWorkspace($activeWorkspace);
            BackendUtility::setUpdateSignal('updatePageTree');
        }

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByPageId($this->pageId);
            if (isset($queryParameters['L'])) {
                $queryParameters['_language'] = $site->getLanguageById((int)$queryParameters['L']);
                unset($queryParameters['L']);
            }
            $parameters = $queryParameters;
            if (!WorkspaceService::isNewPage($this->pageId)) {
                $parameters['ADMCMD_prev'] = 'LIVE';
                $liveUrl = $this->prepareHeadlessUrl($site->getRouter()->generateUri($this->pageId, $parameters), $this->pageId, $site);
            }

            $parameters = $queryParameters;
            $parameters['ADMCMD_prev'] = 'IGNORE';
            $wsUrl = $this->prepareHeadlessUrl($site->getRouter()->generateUri($this->pageId, $parameters), $this->pageId, $site);
        } catch (SiteNotFoundException | InvalidRouteArgumentsException $e) {
            throw new UnableToLinkToPageException('The page ' . $this->pageId . ' had no proper connection to a site, no link could be built.', 1559794913);
        }

        // Evaluate available preview modes
        $splitPreviewModes = GeneralUtility::trimExplode(
            ',',
            BackendUtility::getPagesTSconfig($this->pageId)['workspaces.']['splitPreviewModes'] ?? '',
            true
        );
        $allPreviewModes = ['slider', 'vbox', 'hbox'];
        if (!array_intersect($splitPreviewModes, $allPreviewModes)) {
            $splitPreviewModes = $allPreviewModes;
        }
        $this->moduleTemplate->getPageRenderer()->addJsFile('EXT:backend/Resources/Public/JavaScript/backend.js');
        $this->moduleTemplate->getPageRenderer()->addInlineSetting('Workspaces', 'SplitPreviewModes', $splitPreviewModes);
        $this->moduleTemplate->getPageRenderer()->addInlineSetting('Workspaces', 'id', $this->pageId);

        $this->view->assignMultiple([
            'logoLink' => Typo3Information::URL_COMMUNITY,
            'liveUrl' => $liveUrl ?? false,
            'wsUrl' => $wsUrl,
            'activeWorkspace' => $availableWorkspaces[$activeWorkspace],
            'splitPreviewModes' => $splitPreviewModes,
            'firstPreviewMode' => current($splitPreviewModes),
            'enablePreviousStageButton' => $this->isValidStage($previousStage),
            'enableNextStageButton' => $this->isValidStage($nextStage),
            'enableDiscardStageButton' => $this->isValidStage($nextStage) || $this->isValidStage($previousStage),
            'nextStage' => $nextStage['title'],
            'nextStageId' => $nextStage['uid'],
            'prevStage' => $previousStage['title'],
            'prevStageId' => $previousStage['uid'],
        ]);

        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    protected function prepareHeadlessUrl($url, int $pageUid, $site): string
    {
        $siteConf = $site->getConfiguration();

        if ($siteConf['headless'] ?? false) {
            $frontendBase = GeneralUtility::makeInstance(FrontendBaseUtility::class);
            $frontendBaseUrl = $frontendBase->resolveWithVariants('', $siteConf['baseVariants'] ?? []);

            if ($frontendBaseUrl !== '') {
                $parsedFrontendBase = parse_url($frontendBaseUrl);
                $frontendHost = $parsedFrontendBase['host'] ?? '';
                $frontendPort = $parsedFrontendBase['port'] ?? null;
            }

            $url = $url->withHost($frontendHost)->withPort($frontendPort);
        }

        return (string)$url;
    }
}
