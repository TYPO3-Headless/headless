<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Frontend;

use FriendsOfTYPO3\Headless\Frontend\BackendEditorUrl;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use stdClass;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\Uri;

class BackendEditorUrlTest extends HeadlessUnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    public function testReturnsEmptyStringWithoutBackendUser(): void
    {
        unset($GLOBALS['BE_USER']);

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects(self::never())->method('buildUriFromRoute');

        $backendEditorUrl = new BackendEditorUrl($uriBuilder);

        self::assertSame('', $backendEditorUrl->page());
        self::assertSame('', $backendEditorUrl->record());
    }

    public function testPageBuildsEditUrlForPagesTable(): void
    {
        $GLOBALS['BE_USER'] = new stdClass();

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects(self::once())->method('buildUriFromRoute')
            ->with(
                'record_edit',
                ['edit' => ['pages' => ['__id__' => 'edit']]],
                UriBuilder::ABSOLUTE_URL
            )
            ->willReturn(new Uri('https://backend.tld/typo3/record/edit?pages'));

        self::assertSame(
            'https://backend.tld/typo3/record/edit?pages',
            (new BackendEditorUrl($uriBuilder))->page()
        );
    }

    public function testRecordBuildsEditUrlForContentTable(): void
    {
        $GLOBALS['BE_USER'] = new stdClass();

        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects(self::once())->method('buildUriFromRoute')
            ->with(
                'record_edit',
                ['edit' => ['tt_content' => ['__id__' => 'edit']]],
                UriBuilder::ABSOLUTE_URL
            )
            ->willReturn(new Uri('https://backend.tld/typo3/record/edit?tt_content'));

        self::assertSame(
            'https://backend.tld/typo3/record/edit?tt_content',
            (new BackendEditorUrl($uriBuilder))->record()
        );
    }
}
