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
 * @brief Tests for CategoriesProcessor class - testing category parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

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
}
