<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\View;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use TYPO3\CMS\Core\Configuration\Features;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface as ExtbaseRequestInterface;

class HeadlessViewFactory implements ViewFactoryInterface
{
    protected readonly bool $enabled;

    public function __construct(
        protected readonly ViewFactoryInterface $inner,
        Features $features,
        protected readonly HeadlessModeInterface $headlessMode,
        protected readonly ?ConfigurationManagerInterface $configurationManager = null,
    ) {
        $this->enabled = $features->isFeatureEnabled('headless.overrideFluidTemplates');
    }

    public function create(ViewFactoryData $data): ViewInterface
    {
        if ($data->format !== 'php' || !$this->enabled) {
            return $this->inner->create($data);
        }

        $request = $data->request;
        if ($request === null
            || !ApplicationType::fromRequest($request)->isFrontend()
            || !$this->headlessMode->isEnabledFor($request)) {
            return $this->inner->create($data);
        }

        if ($request instanceof ExtbaseRequestInterface && !$this->isPhpFormatConfigured()) {
            return $this->inner->create($this->withoutFormat($data));
        }

        return new HeadlessPhpView($data);
    }

    protected function isPhpFormatConfigured(): bool
    {
        if ($this->configurationManager === null) {
            return false;
        }

        $configuration = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
        );

        return ($configuration['format'] ?? '') === 'php';
    }

    protected function withoutFormat(ViewFactoryData $data): ViewFactoryData
    {
        return new ViewFactoryData(
            templateRootPaths: $data->templateRootPaths,
            partialRootPaths: $data->partialRootPaths,
            layoutRootPaths: $data->layoutRootPaths,
            templatePathAndFilename: $data->templatePathAndFilename,
            request: $data->request,
        );
    }
}
