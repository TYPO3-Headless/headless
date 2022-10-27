<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\XClass;

/**
 * @codeCoverageIgnore
 */
class FormRuntime extends \TYPO3\CMS\Form\Domain\Runtime\FormRuntime
{
    /**
     * Stripped "render" method
     * Does not render html form & handles finishers & variants
     *
     * @return string|null finisher response or null
     */
    public function run(): ?string
    {
        if ($this->isAfterLastPage()) {
            return $this->invokeFinishers();
        }
        $this->processVariants();

        $this->formState->setLastDisplayedPageIndex($this->currentPage->getIndex());

        return null;
    }
}
