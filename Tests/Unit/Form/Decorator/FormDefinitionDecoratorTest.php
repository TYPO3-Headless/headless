<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Form\Decorator;

use FriendsOfTYPO3\Headless\Form\Decorator\DefinitionDecoratorInterface;
use FriendsOfTYPO3\Headless\Form\Decorator\FormDefinitionDecorator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FormDefinitionDecoratorTest extends UnitTestCase
{
    public function testCreation(): void
    {
        $formDefinition = new FormDefinitionDecorator([]);
        self::assertIsObject($formDefinition);
        self::assertTrue(is_a($formDefinition, DefinitionDecoratorInterface::class, true));
    }
}
