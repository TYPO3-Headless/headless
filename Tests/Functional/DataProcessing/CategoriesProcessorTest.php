<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Functional\DataProcessing;

use FriendsOfTYPO3\Headless\Tests\Functional\BaseHeadlessTesting;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

use const JSON_THROW_ON_ERROR;

/**
 * CategoriesProcessor (alias "headless-categories"), rendered into the
 * initial data of the root page (see Fixtures/categories_processor.typoscript).
 */
class CategoriesProcessorTest extends BaseHeadlessTesting
{
    protected array $typoScriptSetupFiles = [
        'EXT:headless/Configuration/TypoScript/setup.typoscript',
        'EXT:headless/Tests/Functional/Fixtures/categories_processor.typoscript',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $initialData;

    public function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/categories.csv');

        file_put_contents(
            Environment::getConfigPath() . '/sites/headless/config.yaml',
            "rootPageId: 1\nbase: /\nheadless: 1\nbaseVariants: { }\nlanguages: { }\nroutes: { }\n"
        );

        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/?type=834'));
        self::assertSame(200, $response->getStatusCode());

        $this->initialData = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCategoriesOfRelationFieldAreRenderedInRelationOrder(): void
    {
        self::assertSame(
            [
                ['id' => 2, 'title' => 'Category 2'],
                ['id' => 1, 'title' => 'Category 1'],
            ],
            $this->initialData['byRelation'],
            'hidden categories, other fields and other tables must not be rendered'
        );
        self::assertSame([['id' => 3, 'title' => 'Category 3']], $this->initialData['byOtherRelationField']);
    }

    public function testCategoriesByUidListAreRenderedInGivenOrder(): void
    {
        self::assertSame(
            [
                ['id' => 3, 'title' => 'Category 3'],
                ['id' => 1, 'title' => 'Category 1'],
                ['id' => 2, 'title' => 'Category 2'],
            ],
            $this->initialData['byUidList']
        );
    }

    public function testPidInListRestrictsCategories(): void
    {
        self::assertSame([['id' => 5, 'title' => 'Category in folder']], $this->initialData['byUidListWithPid']);
    }

    public function testFieldsAndOrderByAreConfigurable(): void
    {
        self::assertSame(
            [['id' => 1, 'title' => 'Category 1', 'description' => 'Description 1']],
            $this->initialData['withFields']
        );
        self::assertSame(
            ['Category 2', 'Category 1'],
            array_column($this->initialData['withOrderBy'], 'title')
        );
    }
}
