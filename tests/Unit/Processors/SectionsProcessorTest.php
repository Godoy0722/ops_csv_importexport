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

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\publication\Publication;
use APP\section\Section;
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

    // ==================== process() Integration Tests ====================

    public function testProcessUsesDefaultSectionWhenTitleEmpty(): void
    {
        $this->mockPublicationRepository();

        $defaultSection = $this->createMockSection(['id' => 10, 'title' => 'Preprints']);
        CachedEntities::$sections['Preprints_PRE'] = $defaultSection;

        $server = $this->createMockServer(['id' => 1, 'primaryLocale' => 'en']);
        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'sectionTitle' => '',
            'sectionAbbrev' => '',
            'locale' => 'en',
        ];

        SectionsProcessor::process($data, $server, $publication);

        $this->assertEquals(10, $publication->getData('sectionId'));
    }

    public function testProcessUsesCachedSectionWhenFound(): void
    {
        $this->mockPublicationRepository();

        $cachedSection = $this->createMockSection(['id' => 20, 'title' => 'Research', 'abbrev' => 'RES']);
        CachedEntities::$sections['Research_RES'] = $cachedSection;

        $server = $this->createMockServer(['id' => 1]);
        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'sectionTitle' => 'Research',
            'sectionAbbrev' => 'RES',
            'locale' => 'en',
        ];

        SectionsProcessor::process($data, $server, $publication);

        $this->assertEquals(20, $publication->getData('sectionId'));
    }

    public function testProcessCreatesNewSectionWhenNotCached(): void
    {
        $this->mockPublicationRepository();

        $capturedSection = null;
        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('add')->andReturnUsing(function ($section) use (&$capturedSection) {
            $capturedSection = $section;
            return 30;
        });

        $createdSection = $this->createMockSection(['id' => 30, 'title' => 'Custom Section', 'abbrev' => 'CUS']);
        $sectionRepoMock->shouldReceive('get')->with(30, 1)->andReturn($createdSection);

        $server = $this->createMockServer(['id' => 1]);
        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'sectionTitle' => 'Custom Section',
            'sectionAbbrev' => 'cus',
            'locale' => 'en',
        ];

        SectionsProcessor::process($data, $server, $publication);

        $this->assertEquals(30, $publication->getData('sectionId'));
        $this->assertNotNull($capturedSection);
        $this->assertEquals(1, $capturedSection->getContextId());
        $this->assertEquals('Custom Section', $capturedSection->getTitle('en'));
        $this->assertEquals('CUS', $capturedSection->getAbbrev('en'));
        $this->assertEquals('cus', $capturedSection->getPath());
        $this->assertEquals(REALLY_BIG_NUMBER, $capturedSection->getSequence());
        $this->assertFalse($capturedSection->getEditorRestricted());
        $this->assertTrue($capturedSection->getMetaIndexed());
        $this->assertTrue($capturedSection->getMetaReviewed());
        $this->assertFalse($capturedSection->getAbstractsNotRequired());
        $this->assertEquals(REALLY_BIG_NUMBER, $capturedSection->getAbstractWordCount());
        $this->assertFalse($capturedSection->getHideTitle());
        $this->assertFalse($capturedSection->getHideAuthor());
        $this->assertFalse($capturedSection->getIsInactive());
    }

    public function testProcessCachesNewlyCreatedSection(): void
    {
        $this->mockPublicationRepository();

        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('add')->andReturn(30);

        $createdSection = $this->createMockSection(['id' => 30, 'title' => 'Custom', 'abbrev' => 'CUS']);
        $sectionRepoMock->shouldReceive('get')->with(30, 1)->andReturn($createdSection);

        $server = $this->createMockServer(['id' => 1]);
        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'sectionTitle' => 'Custom',
            'sectionAbbrev' => 'cus',
            'locale' => 'en',
        ];

        SectionsProcessor::process($data, $server, $publication);

        $this->assertArrayHasKey('Custom_CUS', CachedEntities::$sections);
        $this->assertEquals($createdSection, CachedEntities::$sections['Custom_CUS']);
    }

    public function testProcessSetsIdentifyTypeAndPolicy(): void
    {
        $this->mockPublicationRepository();

        $capturedSection = null;
        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('add')->andReturnUsing(function ($section) use (&$capturedSection) {
            $capturedSection = $section;
            return 30;
        });
        $sectionRepoMock->shouldReceive('get')->andReturn($this->createMockSection(['id' => 30]));

        $server = $this->createMockServer(['id' => 1]);
        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'sectionTitle' => 'New Section',
            'sectionAbbrev' => 'NEW',
            'locale' => 'en',
        ];

        SectionsProcessor::process($data, $server, $publication);

        $this->assertEquals('', $capturedSection->getIdentifyType('en'));
        $this->assertEquals('', $capturedSection->getPolicy('en'));
    }
}
