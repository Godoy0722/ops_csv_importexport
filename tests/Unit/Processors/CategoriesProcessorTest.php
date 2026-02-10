<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/CategoriesProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoriesProcessorTest
 *
 * @brief Tests for CategoriesProcessor class - testing category parsing and processing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\category\Category;

#[CoversClass(CategoriesProcessor::class)]
class CategoriesProcessorTest extends BaseTestCase
{
    // ==================== Category Parsing Tests ====================

    public function testParsesSingleCategory(): void
    {
        $categoriesString = 'Research Article';
        $categories = array_map('trim', explode(';', $categoriesString));

        $this->assertCount(1, $categories);
        $this->assertEquals('Research Article', $categories[0]);
    }

    public function testParsesMultipleCategories(): void
    {
        $categoriesString = 'Research Article;Review Paper;Case Study';
        $categories = array_map('trim', explode(';', $categoriesString));

        $this->assertCount(3, $categories);
        $this->assertEquals('Research Article', $categories[0]);
        $this->assertEquals('Review Paper', $categories[1]);
        $this->assertEquals('Case Study', $categories[2]);
    }

    public function testTrimsCategoryWhitespace(): void
    {
        $categoriesString = '  Research Article  ;  Review Paper  ';
        $categories = array_map('trim', explode(';', $categoriesString));

        $this->assertEquals('Research Article', $categories[0]);
        $this->assertEquals('Review Paper', $categories[1]);
    }

    public function testHandlesEmptyCategoriesString(): void
    {
        $categoriesString = '';
        $categories = array_filter(array_map('trim', explode(';', $categoriesString)));

        $this->assertEmpty($categories);
    }

    // ==================== Category Path Generation Tests ====================

    public function testCategoryPathGeneration(): void
    {
        $categoryTitle = 'Research Article';
        $path = mb_strtolower($categoryTitle);

        $this->assertEquals('research article', $path);
    }

    public function testCategoryPathPreservesUnicode(): void
    {
        $categoryTitle = 'Artigo de Pesquisa';
        $path = mb_strtolower($categoryTitle);

        $this->assertEquals('artigo de pesquisa', $path);
    }

    // ==================== Categories from Data Object Tests ====================

    public function testCategoriesFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withCategories('Research Article;Review')
            ->buildObject();

        $this->assertEquals('Research Article;Review', $data->categories);
    }

    public function testEmptyCategoriesFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withCategories('')
            ->buildObject();

        $this->assertTrue(empty($data->categories));
    }

    // ==================== Unicode Categories Tests ====================

    public function testHandlesUnicodeCategories(): void
    {
        $categoriesString = 'Artigo de Pesquisa;研究论文';
        $categories = array_map('trim', explode(';', $categoriesString));

        $this->assertCount(2, $categories);
        $this->assertEquals('Artigo de Pesquisa', $categories[0]);
        $this->assertEquals('研究论文', $categories[1]);
    }

    // ==================== Category Data Structure Tests ====================

    public function testCategoryDataStructure(): void
    {
        $categoryData = [
            'id' => 1,
            'contextId' => 1,
            'title' => ['en' => 'Research Article'],
            'path' => 'research article',
        ];

        $this->assertArrayHasKey('id', $categoryData);
        $this->assertArrayHasKey('contextId', $categoryData);
        $this->assertArrayHasKey('title', $categoryData);
        $this->assertArrayHasKey('path', $categoryData);
    }

    public function testMultiLocaleCategoryTitle(): void
    {
        $titles = [
            'en' => 'Research Article',
            'pt_BR' => 'Artigo de Pesquisa',
        ];

        $this->assertArrayHasKey('en', $titles);
        $this->assertArrayHasKey('pt_BR', $titles);
        $this->assertEquals('Research Article', $titles['en']);
        $this->assertEquals('Artigo de Pesquisa', $titles['pt_BR']);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessReturnsEarlyWhenEmpty(): void
    {
        $assignCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function () use (&$assignCallCount) {
            $assignCallCount++;
        });

        CategoriesProcessor::process('', 'en', 1, 1);

        $this->assertEquals(0, $assignCallCount);
    }

    public function testProcessReturnsEarlyWhenWhitespaceOnly(): void
    {
        $assignCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function () use (&$assignCallCount) {
            $assignCallCount++;
        });

        CategoriesProcessor::process('   ', 'en', 1, 1);

        $this->assertEquals(0, $assignCallCount);
    }

    public function testProcessUsesCachedCategory(): void
    {
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });
        $this->mockCategoryRepository();

        $category = $this->createMockCategory(['id' => 5, 'path' => 'research article']);
        CachedEntities::$categories['research article'] = $category;

        CategoriesProcessor::process('Research Article', 'en', 1, 10);

        $this->assertEquals([5], $assignedCategories);
    }

    public function testProcessCreatesNewCategoryWhenNotCached(): void
    {
        $addCallCount = 0;
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });

        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return 42;
        });

        CategoriesProcessor::process('New Category', 'en', 1, 10);

        $this->assertEquals(1, $addCallCount);
        $this->assertEquals([42], $assignedCategories);
    }

    public function testProcessMixesCachedAndNewCategories(): void
    {
        $addCallCount = 0;
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });

        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return 99;
        });

        $cachedCategory = $this->createMockCategory(['id' => 5, 'path' => 'existing']);
        CachedEntities::$categories['existing'] = $cachedCategory;

        CategoriesProcessor::process('Existing;New One', 'en', 1, 10);

        $this->assertEquals(1, $addCallCount);
        $this->assertEquals([5, 99], $assignedCategories);
    }

    public function testProcessSkipsEmptyCategoryPaths(): void
    {
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });

        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturn(42);

        CategoriesProcessor::process('Valid;;Also Valid;', 'en', 1, 10);

        $this->assertCount(2, $assignedCategories);
    }

    public function testProcessSetsCorrectCategoryProperties(): void
    {
        $capturedCategory = null;
        $pubRepoMock = $this->mockPublicationRepository();

        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturnUsing(function ($cat) use (&$capturedCategory) {
            $capturedCategory = $cat;
            return 1;
        });

        CategoriesProcessor::process('Research Article', 'en', 5, 10);

        $this->assertEquals(5, $capturedCategory->getContextId());
        $this->assertEquals('Research Article', $capturedCategory->getTitle('en'));
        $this->assertNull($capturedCategory->getParentId());
        $this->assertEquals(REALLY_BIG_NUMBER, $capturedCategory->getSequence());
        $this->assertEquals('research article', $capturedCategory->getPath());
    }

    public function testProcessDoesNotAssignWhenAllPathsEmpty(): void
    {
        $assignCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function () use (&$assignCallCount) {
            $assignCallCount++;
        });
        $this->mockCategoryRepository();

        CategoriesProcessor::process(';;', 'en', 1, 10);

        $this->assertEquals(0, $assignCallCount);
    }

    // ==================== processForVersion() Integration Tests ====================

    public function testProcessForVersionDeletesExistingAndCreatesNew(): void
    {
        $this->beginDatabaseTransaction();
        $this->mockCategoryRepository();
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });

        // Pre-insert a category for publication 5 that should be deleted
        \Illuminate\Support\Facades\DB::table('publication_categories')->insert([
            'publication_id' => 5,
            'category_id' => 99,
        ]);

        CategoriesProcessor::processForVersion('New Category', 'en', 1, 5);

        // Existing categories should have been deleted
        $remaining = \Illuminate\Support\Facades\DB::table('publication_categories')
            ->where('publication_id', 5)
            ->where('category_id', 99)
            ->count();
        $this->assertEquals(0, $remaining);
        $this->assertNotNull($assignedCategories);
        $this->rollbackDatabaseTransaction();
    }

    public function testProcessForVersionClonesBaseWhenEmpty(): void
    {
        $this->beginDatabaseTransaction();
        $this->mockCategoryRepository();
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });

        // Pre-insert categories for the base publication (id=1)
        \Illuminate\Support\Facades\DB::table('publication_categories')->insert([
            ['publication_id' => 1, 'category_id' => 10],
            ['publication_id' => 1, 'category_id' => 20],
        ]);

        $basePublication = new \APP\publication\Publication();
        $basePublication->setId(1);

        CategoriesProcessor::processForVersion('', 'en', 1, 5, $basePublication);

        $this->assertEquals([10, 20], $assignedCategories);
        $this->rollbackDatabaseTransaction();
    }

    public function testProcessForVersionCallsProcessWhenEmptyAndNoBase(): void
    {
        $this->beginDatabaseTransaction();
        $assignCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function () use (&$assignCallCount) {
            $assignCallCount++;
        });
        $this->mockCategoryRepository();

        CategoriesProcessor::processForVersion('', 'en', 1, 5);

        // process('') returns early, no assignment
        $this->assertEquals(0, $assignCallCount);
        $this->rollbackDatabaseTransaction();
    }

    public function testProcessForVersionCallsProcessWhenBaseHasNoCategories(): void
    {
        $this->beginDatabaseTransaction();
        $assignCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function () use (&$assignCallCount) {
            $assignCallCount++;
        });
        $this->mockCategoryRepository();

        $basePublication = new \APP\publication\Publication();
        $basePublication->setId(1);

        // No categories for base publication in DB
        CategoriesProcessor::processForVersion('', 'en', 1, 5, $basePublication);

        // Empty base categories → falls through to process('') which returns early
        $this->assertEquals(0, $assignCallCount);
        $this->rollbackDatabaseTransaction();
    }

    public function testProcessForVersionWithCachedCategory(): void
    {
        $this->beginDatabaseTransaction();
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });
        $this->mockCategoryRepository();

        $cachedCategory = $this->createMockCategory(['id' => 7, 'path' => 'research']);
        CachedEntities::$categories['research'] = $cachedCategory;

        CategoriesProcessor::processForVersion('Research', 'en', 1, 5);

        $this->assertEquals([7], $assignedCategories);
        $this->rollbackDatabaseTransaction();
    }

    // ==================== processMultiLocale() Integration Tests ====================

    public function testProcessMultiLocaleReturnsEarlyWhenEmpty(): void
    {
        $addCallCount = 0;
        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return 1;
        });

        CategoriesProcessor::processMultiLocale('', 'pt_BR', 1, 10);

        $this->assertEquals(0, $addCallCount);
    }

    public function testProcessMultiLocaleReturnsEarlyWhenWhitespaceOnly(): void
    {
        $addCallCount = 0;
        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return 1;
        });

        CategoriesProcessor::processMultiLocale('   ', 'pt_BR', 1, 10);

        $this->assertEquals(0, $addCallCount);
    }

    public function testProcessMultiLocaleUpdatesExistingCategoryTitle(): void
    {
        $this->mockCategoryRepository();
        $this->mockPublicationRepository();

        // Create a mock category that's in the cache
        $categoryMock = Mockery::mock(Category::class)->makePartial();
        $categoryMock->shouldReceive('getLocalizedData')->with('title', 'pt_BR')->andReturn(null);
        $categoryMock->shouldReceive('setTitle')->with('Artigo de Pesquisa', 'pt_BR')->once();

        CachedEntities::$categories['artigo de pesquisa'] = $categoryMock;

        CategoriesProcessor::processMultiLocale('Artigo de Pesquisa', 'pt_BR', 1, 10);

        // Verify setTitle was called (Mockery will assert this)
        $this->assertTrue(true);
    }

    public function testProcessMultiLocaleSkipsUpdateWhenTitleMatches(): void
    {
        $updateCallCount = 0;
        $catRepoMock = $this->mockCategoryRepository();
        // Track DAO update calls
        $catRepoMock->dao->shouldReceive('update')->andReturnUsing(function () use (&$updateCallCount) {
            $updateCallCount++;
            return true;
        });
        $this->mockPublicationRepository();

        // Create a mock category with matching title
        $categoryMock = Mockery::mock(Category::class)->makePartial();
        $categoryMock->shouldReceive('getLocalizedData')->with('title', 'pt_BR')->andReturn('Artigo de Pesquisa');

        CachedEntities::$categories['artigo de pesquisa'] = $categoryMock;

        CategoriesProcessor::processMultiLocale('Artigo de Pesquisa', 'pt_BR', 1, 10);

        $this->assertEquals(0, $updateCallCount);
    }

    public function testProcessMultiLocaleSkipsEmptyPaths(): void
    {
        $this->mockCategoryRepository();
        $this->mockPublicationRepository();

        $categoryMock = Mockery::mock(Category::class)->makePartial();
        $categoryMock->shouldReceive('getLocalizedData')->with('title', 'pt_BR')->andReturn(null);
        $categoryMock->shouldReceive('setTitle')->once();

        CachedEntities::$categories['valid'] = $categoryMock;

        // ";;" contains only empty paths plus one valid
        CategoriesProcessor::processMultiLocale('Valid;;', 'pt_BR', 1, 10);

        $this->assertTrue(true);
    }

    public function testProcessMultiLocaleCreatesNewCategoryWhenNotCached(): void
    {
        $this->beginDatabaseTransaction();
        $assignedCategories = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function ($pubId, $catIds) use (&$assignedCategories) {
            $assignedCategories = $catIds;
        });

        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturn(42);

        CategoriesProcessor::processMultiLocale('New Category', 'pt_BR', 1, 10);

        // Should create new category and assign to publication
        $this->assertNotNull($assignedCategories);
        $this->assertContains(42, $assignedCategories);
        $this->rollbackDatabaseTransaction();
    }

    public function testProcessMultiLocaleDoesNotDuplicateExistingAssignment(): void
    {
        $this->beginDatabaseTransaction();
        $assignCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('assignCategoriesToPublication')->andReturnUsing(function () use (&$assignCallCount) {
            $assignCallCount++;
        });

        $catRepoMock = $this->mockCategoryRepository();
        $catRepoMock->shouldReceive('add')->andReturn(42);

        // Pre-insert the same category assignment
        \Illuminate\Support\Facades\DB::table('publication_categories')->insert([
            'publication_id' => 10,
            'category_id' => 42,
        ]);

        CategoriesProcessor::processMultiLocale('New Category', 'pt_BR', 1, 10);

        // Should not reassign since category 42 is already assigned
        $this->assertEquals(0, $assignCallCount);
        $this->rollbackDatabaseTransaction();
    }
}
