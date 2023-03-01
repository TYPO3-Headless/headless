<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

use function extract;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;

class TemplateView extends \TYPO3\CMS\Fluid\View\TemplateView
{
    public function render($actionName = null)
    {
        if (!ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()) {
            return parent::render($actionName);
        }

        $renderingContext = $this->getCurrentRenderingContext();

        if ((int)($renderingContext->getVariableProvider()->get('settings')['phpTemplate'] ?? 0) !== 1) {
            return parent::render($actionName);
        }

        $templatePaths = $renderingContext->getTemplatePaths();
        if ($actionName) {
            $actionName = ucfirst($actionName);
            $renderingContext->setControllerAction($actionName);
        }

        $templateFile = $templatePaths->resolveTemplateFileForControllerAndActionAndFormat($renderingContext->getControllerName(), $renderingContext->getControllerAction(), 'php');

        if ($templateFile === null) {
            throw new InvalidTemplateResourceException('Template is not found');
        }

        return $this->loadTemplate($templateFile, $renderingContext);
    }

    private function loadTemplate(string $templateFile, RenderingContextInterface $renderingContext): string
    {
        $__jsonContent = '';

        try {
            extract($renderingContext->getVariableProvider()->getAll());

            ob_start();
            include $templateFile;
            $__jsonContent = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return $__jsonContent;
    }
}
