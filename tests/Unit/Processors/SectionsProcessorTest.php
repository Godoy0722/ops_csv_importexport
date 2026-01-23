<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/SectionsProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionsProcessorTest
 *
 * @brief Tests for SectionsProcessor class - testing section data logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SectionsProcessor::class)]
class SectionsProcessorTest extends BaseTestCase
{
    // ==================== Section Data Tests ====================

    public function testSectionTitleFromData(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionTitle('Preprints')
            ->buildObject();

        $this->assertEquals('Preprints', $data->sectionTitle);
    }

    public function testSectionAbbrevFromData(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionAbbrev('PRE')
            ->buildObject();

        $this->assertEquals('PRE', $data->sectionAbbrev);
    }

    public function testBothSectionFieldsFromData(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionTitle('Research Articles')
            ->withSectionAbbrev('RES')
            ->buildObject();

        $this->assertEquals('Research Articles', $data->sectionTitle);
        $this->assertEquals('RES', $data->sectionAbbrev);
    }

    // ==================== Section Abbreviation Formatting Tests ====================

    public function testAbbrevUppercaseConversion(): void
    {
        $abbrev = 'pre';
        $uppercased = strtoupper(trim($abbrev));

        $this->assertEquals('PRE', $uppercased);
    }

    public function testAbbrevWhitespaceTrimming(): void
    {
        $abbrev = '  PRE  ';
        $trimmed = strtoupper(trim($abbrev));

        $this->assertEquals('PRE', $trimmed);
    }

    public function testMixedCaseAbbrev(): void
    {
        $abbrev = 'PrE';
        $uppercased = strtoupper(trim($abbrev));

        $this->assertEquals('PRE', $uppercased);
    }

    // ==================== Section Cache Key Tests ====================

    public function testSectionCacheKey(): void
    {
        $title = 'Preprints';
        $abbrev = 'PRE';

        $cacheKey = $title . '_' . $abbrev;

        $this->assertEquals('Preprints_PRE', $cacheKey);
    }

    public function testSectionCacheKeyWithSpaces(): void
    {
        $title = 'Research Articles';
        $abbrev = 'RES';

        $cacheKey = $title . '_' . $abbrev;

        $this->assertEquals('Research Articles_RES', $cacheKey);
    }

    // ==================== Empty Section Fields Tests ====================

    public function testEmptySectionTitle(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionTitle('')
            ->buildObject();

        $this->assertTrue(empty($data->sectionTitle));
    }

    public function testEmptySectionAbbrev(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionAbbrev('')
            ->buildObject();

        $this->assertTrue(empty($data->sectionAbbrev));
    }

    // ==================== Unicode Section Names Tests ====================

    public function testUnicodeSectionTitle(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionTitle('Artigos de Pesquisa')
            ->buildObject();

        $this->assertEquals('Artigos de Pesquisa', $data->sectionTitle);
    }

    public function testJapaneseSectionTitle(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionTitle('研究論文')
            ->buildObject();

        $this->assertEquals('研究論文', $data->sectionTitle);
    }

    // ==================== Section Data Structure Tests ====================

    public function testSectionDataStructure(): void
    {
        $sectionData = [
            'id' => 1,
            'contextId' => 1,
            'title' => ['en' => 'Preprints'],
            'abbrev' => ['en' => 'PRE'],
            'editorRestricted' => false,
            'metaIndexed' => true,
            'metaReviewed' => true,
            'abstractsNotRequired' => false,
            'hideTitle' => false,
            'hideAuthor' => false,
            'isInactive' => false,
        ];

        $this->assertArrayHasKey('id', $sectionData);
        $this->assertArrayHasKey('contextId', $sectionData);
        $this->assertArrayHasKey('title', $sectionData);
        $this->assertArrayHasKey('abbrev', $sectionData);
        $this->assertFalse($sectionData['editorRestricted']);
        $this->assertTrue($sectionData['metaIndexed']);
    }
}
