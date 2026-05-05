<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

use FriendsOfTYPO3\Headless\Utility\HeadlessModeInterface;
use Throwable;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

use TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException;

use function extract;
use function ob_end_clean;
use function ob_get_clean;
use function ob_start;

class TemplateView extends \TYPO3Fluid\Fluid\View\TemplateView
{
    /**
     * Lazy-loaded HeadlessMode instance. While this XClass is technically registered as a
     * Symfony service alias in Configuration/Services.php (when the headless.overrideFluidTemplates
     * feature is enabled), the parent class accepts a RenderingContextInterface via constructor
     * which is supplied by callers (and by tests instantiating with `new TemplateView($context)`).
     * Constructor / #[Required] setter injection would either break that contract or fail to run
     * under direct instantiation. Lazy-resolving via the container keeps full BC and remains
     * mockable in tests by registering a stub HeadlessModeInterface in the GeneralUtility container.
     */
    private ?HeadlessModeInterface $headlessMode = null;

    private function getHeadlessMode(): HeadlessModeInterface
    {
        return $this->headlessMode ??= GeneralUtility::makeInstance(HeadlessModeInterface::class);
    }

    public function render($actionName = null)
    {
        $headlessMode = $this->getHeadlessMode()->withRequest($GLOBALS['TYPO3_REQUEST']);

        if (!ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend() || !$headlessMode->isEnabled()) {
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
            throw new InvalidTemplateResourceException('Template is not found', 1740000000);
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
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return $__jsonContent;
    }
}
