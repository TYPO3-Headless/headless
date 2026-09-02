<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\ViewHelpers\Iterator;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\ViewHelpers\Iterator\ExplodeViewHelper;

class ExplodeViewHelperTest extends HeadlessUnitTestCase
{
    public function testExplodesContentByDefaultGlue(): void
    {
        $viewHelper = new ExplodeViewHelper();
        $viewHelper->setArguments(['content' => 'a,b,c', 'glue' => ',', 'as' => null]);

        self::assertSame(['a', 'b', 'c'], $viewHelper->render());
    }

    public function testExplodesByConstantGlue(): void
    {
        $viewHelper = new ExplodeViewHelper();
        $viewHelper->setArguments(['content' => "a\nb", 'glue' => 'constant:LF', 'as' => null]);

        self::assertSame(['a', 'b'], $viewHelper->render());
    }

    public function testExplodesChildrenWhenContentIsEmpty(): void
    {
        $viewHelper = new ExplodeViewHelper();
        $viewHelper->setArguments(['content' => '', 'glue' => '|', 'as' => null]);
        $viewHelper->setRenderChildrenClosure(static fn(): string => 'x|y');

        self::assertSame(['x', 'y'], $viewHelper->render());
    }

    public function testDeclaresContentGlueAndAsArguments(): void
    {
        self::assertSame(['content', 'glue', 'as'], array_keys((new ExplodeViewHelper())->prepareArguments()));
    }

    public function testUnknownGlueTypePrefixFallsBackToRawValue(): void
    {
        $viewHelper = new ExplodeViewHelper();
        $viewHelper->setArguments(['content' => 'a;b', 'glue' => 'raw:;', 'as' => null]);

        self::assertSame(['a', 'b'], $viewHelper->render());
    }

    public function testAsArgumentReturnsTrimmedContent(): void
    {
        $viewHelper = new ExplodeViewHelper();
        $viewHelper->setArguments(['content' => ' a,b ', 'glue' => ',', 'as' => 'items']);

        self::assertSame('a,b', $viewHelper->render());
    }
}
