<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Form\Decorator;

interface DefinitionDecoratorInterface
{
    /**
     * @param array<mixed> $definition
     * @return array<string,array<mixed>>
     */
    public function __invoke(array $definition, int $currentPage): array;
}
