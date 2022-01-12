<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Dto;

use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @codeCoverageIgnore
 */
final class JsonViewDemand implements JsonViewDemandInterface
{
    /**
     * @var int
     */
    private $pageId = 0;

    /**
     * @var Site
     */
    private $site;

    /**
     * @var \TYPO3\CMS\Core\Site\Entity\SiteLanguage
     */
    private $siteLanguage;

    /**
     * @var int
     */
    private $feGroup = 0;

    /**
     * @var bool
     */
    private $hiddenContentVisible = true;

    /**
     * @var string
     */
    private $pageTypeMode = 'default';

    /**
     * @var string
     */
    private $pluginNamespace;

    /**
     * @var bool
     */
    private $initialized = false;

    /**
     * @param ServerRequest $request
     * @param string $pluginNamespace
     * @throws \TYPO3\CMS\Core\Exception\SiteNotFoundException
     */
    public function __construct(ServerRequest $request, string $pluginNamespace = '')
    {
        $this->pluginNamespace = $pluginNamespace;
        $site = $request->getAttribute('site');

        if (($site === null || $site instanceof NullSite) && $this->getActionArgument($request, 'site') !== null) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByIdentifier($this->getActionArgument($request, 'site', ''));
        }

        if ($site instanceof Site) {
            $this->site = $site;
            $this->pageId = (int)$this->getActionArgument($request, 'id', 0);
            $this->feGroup = (int)$this->getActionArgument($request, 'feGroup');
            $this->hiddenContentVisible = (bool)$this->getActionArgument($request, 'hidden', true);
            $this->pageTypeMode = (string)$this->getActionArgument($request, 'pageTypeMode', 'default');

            if ($this->site->getLanguages()) {
                $lang = (int)$this->getActionArgument($request, 'lang', 0);
                foreach ($this->site->getLanguages() as $language) {
                    if ($language->getLanguageId() === $lang) {
                        $this->siteLanguage = $language;
                        break;
                    }
                }
            }

            $this->initialized = true;
        }
    }

    /**
     * @param ServerRequest $request
     * @param string $argumentName
     * @param $defaultValue
     * @return mixed
     */
    protected function getActionArgument(ServerRequest $request, string $argumentName, $defaultValue = null)
    {
        return $request->getParsedBody()[$argumentName]
            ?? $request->getQueryParams()[$argumentName]
            ?? $request->getQueryParams()[$this->pluginNamespace][$argumentName]
            ?? $defaultValue;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    /**
     * @return \TYPO3\CMS\Core\Site\Entity\Site
     */
    public function getSite(): \TYPO3\CMS\Core\Site\Entity\Site
    {
        return $this->site;
    }

    /**
     * @return \TYPO3\CMS\Core\Site\Entity\SiteLanguage
     */
    public function getSiteLanguage(): \TYPO3\CMS\Core\Site\Entity\SiteLanguage
    {
        return $this->siteLanguage;
    }

    public function getFeGroup(): int
    {
        return $this->feGroup;
    }

    public function isHiddenContentVisible(): bool
    {
        return $this->hiddenContentVisible;
    }

    public function getPageTypeMode(): string
    {
        return $this->pageTypeMode;
    }

    public function getLanguageId(): int
    {
        return $this->getSiteLanguage()->getLanguageId();
    }

    public function getPluginNamespace(): string
    {
        return $this->pluginNamespace;
    }

    public function toArray(): array
    {
        return [
            'pageType' => $this->getPageTypeMode(),
            'lang' => $this->getLanguageId(),
            'id' => $this->getPageId(),
            'feGroup' => $this->getFeGroup(),
            'site' => $this->getSite()->getIdentifier(),
            'hidden' => $this->isHiddenContentVisible()
        ];
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
