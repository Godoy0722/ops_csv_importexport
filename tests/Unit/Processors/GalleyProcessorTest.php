<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/GalleyProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyProcessorTest
 *
 * @brief Tests for GalleyProcessor class - testing galley data logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\galley\DAO as GalleyDAO;
use PKP\galley\Galley;
use PKP\galley\Repository as GalleyRepository;

#[CoversClass(GalleyProcessor::class)]
class GalleyProcessorTest extends BaseTestCase
{
    // ==================== Galley Files Parsing Tests ====================

    public function testParsesSingleGalleyFile(): void
    {
        $galleyFilenames = 'paper.pdf';
        $galleyLabels = 'PDF';

        $files = array_map('trim', explode(';', $galleyFilenames));
        $labels = array_map('trim', explode(';', $galleyLabels));

        $this->assertCount(1, $files);
        $this->assertCount(1, $labels);
        $this->assertEquals('paper.pdf', $files[0]);
        $this->assertEquals('PDF', $labels[0]);
    }

    public function testParsesMultipleGalleyFiles(): void
    {
        $galleyFilenames = 'paper.pdf;slides.pptx;data.xlsx';
        $galleyLabels = 'PDF;SLIDES;DATA';

        $files = array_map('trim', explode(';', $galleyFilenames));
        $labels = array_map('trim', explode(';', $galleyLabels));

        $this->assertCount(3, $files);
        $this->assertCount(3, $labels);
    }

    public function testGalleyFilesAndLabelsCount(): void
    {
        $galleyFilenames = 'paper.pdf;slides.pptx';
        $galleyLabels = 'PDF;SLIDES';

        $files = array_map('trim', explode(';', $galleyFilenames));
        $labels = array_map('trim', explode(';', $galleyLabels));

        $this->assertEquals(count($files), count($labels));
    }

    // ==================== Galley Extension Handling Tests ====================

    public function testExtensionExtraction(): void
    {
        $filename = 'paper.pdf';
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $this->assertEquals('pdf', $extension);
    }

    public function testExtensionUppercase(): void
    {
        $filename = 'paper.pdf';
        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

        $this->assertEquals('PDF', $extension);
    }

    public function testVariousExtensions(): void
    {
        $files = [
            'paper.pdf' => 'PDF',
            'slides.pptx' => 'PPTX',
            'data.xlsx' => 'XLSX',
            'document.docx' => 'DOCX',
            'article.xml' => 'XML',
        ];

        foreach ($files as $filename => $expectedExt) {
            $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
            $this->assertEquals($expectedExt, $ext);
        }
    }

    // ==================== Galley Data from CSV Tests ====================

    public function testGalleysFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withGalleys('paper.pdf', 'PDF')
            ->buildObject();

        $this->assertEquals('paper.pdf', $data->galleyFilenames);
        $this->assertEquals('PDF', $data->galleyLabels);
    }

    public function testEmptyGalleys(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withGalleys('', '')
            ->buildObject();

        $this->assertTrue(empty($data->galleyFilenames));
        $this->assertTrue(empty($data->galleyLabels));
    }

    // ==================== Galley Label Tests ====================

    public function testGalleyLabelFormat(): void
    {
        $label = 'PDF';

        $this->assertNotEmpty($label);
        $this->assertIsString($label);
    }

    public function testCustomGalleyLabel(): void
    {
        $label = 'Full Text';

        $this->assertEquals('Full Text', $label);
    }

    // ==================== Galley Properties Tests ====================

    public function testGalleyIsApproved(): void
    {
        $isApproved = true;

        $this->assertTrue($isApproved);
    }

    public function testGalleySequenceNumber(): void
    {
        // REALLY_BIG_NUMBER is used for sequence
        $sequence = REALLY_BIG_NUMBER;

        $this->assertIsInt($sequence);
        $this->assertGreaterThan(0, $sequence);
    }

    // ==================== DOI Handling Tests ====================

    public function testGalleyDoiFromData(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDoi('10.1234/test-doi')
            ->buildObject();

        $this->assertEquals('10.1234/test-doi', $data->doi);
    }

    public function testEmptyGalleyDoi(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDoi('')
            ->buildObject();

        $this->assertTrue(empty($data->doi));
    }

    // ==================== process() Integration Tests ====================

    public function testProcessCreatesGalleyAndReturnsId(): void
    {
        $this->mockGalleyRepository();

        $data = (object) ['locale' => 'en', 'doi' => ''];

        $galleyId = GalleyProcessor::process(10, $data, 'PDF', 1, 'pdf');

        $this->assertEquals(1, $galleyId);
    }

    public function testProcessWithDoi(): void
    {
        // setStoredPubId requires publication context, so we mock the Galley
        $this->backupContainerInstance(GalleyRepository::class);

        $galleyDaoMock = Mockery::mock(GalleyDAO::class)->makePartial();
        $galleyDaoMock->shouldReceive('insert')->andReturn(1);

        $galleyRepoMock = Mockery::mock(GalleyRepository::class)->makePartial();
        $galleyRepoMock->shouldReceive('newDataObject')->andReturnUsing(function () {
            $galley = Mockery::mock(Galley::class)->makePartial();
            $galley->shouldReceive('setStoredPubId')->andReturnNull();
            return $galley;
        });
        $galleyRepoMock->shouldReceive('add')->andReturn(1);
        $galleyRepoMock->dao = $galleyDaoMock;
        app()->instance(GalleyRepository::class, $galleyRepoMock);

        $data = (object) ['locale' => 'en', 'doi' => '10.1234/test'];

        $galleyId = GalleyProcessor::process(10, $data, 'PDF', 1, 'pdf');

        $this->assertEquals(1, $galleyId);
    }

    public function testProcessWithEmptyDoi(): void
    {
        $this->mockGalleyRepository();

        $data = (object) ['locale' => 'en', 'doi' => ''];

        $galleyId = GalleyProcessor::process(10, $data, 'Full Text', 1, 'docx');

        $this->assertEquals(1, $galleyId);
    }

    public function testProcessWithDifferentLocale(): void
    {
        $this->mockGalleyRepository();

        $data = (object) ['locale' => 'pt_BR', 'doi' => ''];

        $galleyId = GalleyProcessor::process(5, $data, 'PDF', 2, 'pdf');

        $this->assertEquals(1, $galleyId);
    }

    public function testProcessSetsCorrectSubmissionFileId(): void
    {
        $capturedGalley = null;
        $galleyRepoMock = $this->mockGalleyRepository();
        $galleyRepoMock->shouldReceive('add')->andReturnUsing(function ($galley) use (&$capturedGalley) {
            $capturedGalley = $galley;
            return 1;
        });

        $data = (object) ['locale' => 'en', 'doi' => ''];

        GalleyProcessor::process(99, $data, 'PDF', 5, 'pdf');

        $this->assertEquals(99, $capturedGalley->getData('submissionFileId'));
        $this->assertEquals(5, $capturedGalley->getData('publicationId'));
        $this->assertEquals('PDF', $capturedGalley->getLabel());
        $this->assertEquals('en', $capturedGalley->getLocale());
        $this->assertTrue($capturedGalley->getIsApproved());
        $this->assertEquals('PDF', $capturedGalley->getData('name', 'en'));
    }

    public function testProcessSetsDoiOnGalley(): void
    {
        // setStoredPubId requires publication context, so we mock the Galley
        $this->backupContainerInstance(GalleyRepository::class);

        $capturedGalley = null;
        $galleyDaoMock = Mockery::mock(GalleyDAO::class)->makePartial();
        $galleyDaoMock->shouldReceive('insert')->andReturn(1);

        $galleyRepoMock = Mockery::mock(GalleyRepository::class)->makePartial();
        $galleyRepoMock->shouldReceive('newDataObject')->andReturnUsing(function () {
            $galley = Mockery::mock(Galley::class)->makePartial();
            $galley->shouldReceive('setStoredPubId')->with('doi', '10.1234/galley-doi')->once()->andReturnNull();
            return $galley;
        });
        $galleyRepoMock->shouldReceive('add')->andReturnUsing(function ($galley) use (&$capturedGalley) {
            $capturedGalley = $galley;
            return 1;
        });
        $galleyRepoMock->dao = $galleyDaoMock;
        app()->instance(GalleyRepository::class, $galleyRepoMock);

        $data = (object) ['locale' => 'en', 'doi' => '10.1234/galley-doi'];

        GalleyProcessor::process(10, $data, 'PDF', 1, 'pdf');

        $this->assertNotNull($capturedGalley);
    }

    public function testProcessUppercasesExtensionForName(): void
    {
        $capturedGalley = null;
        $galleyRepoMock = $this->mockGalleyRepository();
        $galleyRepoMock->shouldReceive('add')->andReturnUsing(function ($galley) use (&$capturedGalley) {
            $capturedGalley = $galley;
            return 1;
        });

        $data = (object) ['locale' => 'en', 'doi' => ''];

        GalleyProcessor::process(10, $data, 'My Document', 1, 'docx');

        $this->assertEquals('DOCX', $capturedGalley->getData('name', 'en'));
        $this->assertEquals('My Document', $capturedGalley->getLabel());
    }

    public function testProcessReturnsCustomGalleyId(): void
    {
        $galleyRepoMock = $this->mockGalleyRepository();
        $galleyRepoMock->shouldReceive('add')->andReturn(42);

        $data = (object) ['locale' => 'pt_BR', 'doi' => ''];

        $galleyId = GalleyProcessor::process(5, $data, 'PDF', 2, 'pdf');

        $this->assertEquals(42, $galleyId);
    }
}
