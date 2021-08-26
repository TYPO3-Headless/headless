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

namespace FriendsOfTYPO3\Headless\XClass\Domain\Model;

use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

class FormDefinition extends \TYPO3\CMS\Form\Domain\Model\FormDefinition
{
    /**
     * Get all form elements with their identifiers as keys
     *
     * @return FormElementInterface[]
     */
    public function getElements(): array
    {
        return $this->elementsByIdentifier;
    }
}
