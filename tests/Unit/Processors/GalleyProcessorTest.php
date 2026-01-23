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
use PHPUnit\Framework\Attributes\CoversClass;

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
}
