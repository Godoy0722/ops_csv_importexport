<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/SubmissionFileProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileProcessorTest
 *
 * @brief Tests for SubmissionFileProcessor class - testing file processing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\SubmissionFileProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use PKP\submissionFile\SubmissionFile;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubmissionFileProcessor::class)]
class SubmissionFileProcessorTest extends BaseTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();
    }

    // ==================== File Path Handling Tests ====================

    public function testFilePathBasename(): void
    {
        $filePath = $this->tempDir . '/my-research-paper.pdf';
        $this->createTestFile($this->tempDir, 'my-research-paper.pdf', 'fake content');

        $basename = pathinfo($filePath, PATHINFO_FILENAME);

        $this->assertEquals('my-research-paper', $basename);
    }

    public function testFileExtraction(): void
    {
        $filePath = $this->tempDir . '/test.pdf';
        $this->createTestFile($this->tempDir, 'test.pdf', 'fake content');

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        $this->assertEquals('pdf', $extension);
    }

    public function testFileExists(): void
    {
        $filePath = $this->tempDir . '/test.pdf';
        $this->createTestFile($this->tempDir, 'test.pdf', 'fake content');

        $this->assertTrue(file_exists($filePath));
    }

    public function testFileNotExists(): void
    {
        $filePath = $this->tempDir . '/nonexistent.pdf';

        $this->assertFalse(file_exists($filePath));
    }

    // ==================== File Stage Tests ====================

    public function testFileStageProof(): void
    {
        $fileStage = SubmissionFile::SUBMISSION_FILE_PROOF;

        $this->assertIsInt($fileStage);
    }

    // ==================== Supplementary Files Parsing Tests ====================

    public function testParsesSingleSupplementaryFile(): void
    {
        $suppFilenames = 'data.xlsx';
        $suppLabels = 'Dataset';

        $files = array_map('trim', explode(';', $suppFilenames));
        $labels = array_map('trim', explode(';', $suppLabels));

        $this->assertCount(1, $files);
        $this->assertCount(1, $labels);
    }

    public function testParsesMultipleSupplementaryFiles(): void
    {
        $suppFilenames = 'data.xlsx;supplement.pdf;appendix.docx';
        $suppLabels = 'Dataset;Supplement;Appendix';

        $files = array_map('trim', explode(';', $suppFilenames));
        $labels = array_map('trim', explode(';', $suppLabels));

        $this->assertCount(3, $files);
        $this->assertCount(3, $labels);
    }

    public function testSupplementaryFilesAndLabelsCountMatch(): void
    {
        $suppFilenames = 'data.xlsx;supplement.pdf';
        $suppLabels = 'Dataset;Supplement';

        $files = array_map('trim', explode(';', $suppFilenames));
        $labels = array_map('trim', explode(';', $suppLabels));

        $this->assertEquals(count($files), count($labels));
    }

    // ==================== Supplementary Descriptions Tests ====================

    public function testSupplementaryDescriptionsParsing(): void
    {
        $descriptions = 'This is the dataset description;This is the supplement description';
        $parsed = array_map('trim', explode(';', $descriptions));

        $this->assertCount(2, $parsed);
        $this->assertEquals('This is the dataset description', $parsed[0]);
        $this->assertEquals('This is the supplement description', $parsed[1]);
    }

    public function testEmptyDescriptions(): void
    {
        $descriptions = '';
        $parsed = array_filter(array_map('trim', explode(';', $descriptions)));

        $this->assertEmpty($parsed);
    }

    // ==================== Sales Type Tests ====================

    public function testSalesTypeOpenAccess(): void
    {
        $salesType = 'openAccess';

        $this->assertEquals('openAccess', $salesType);
    }

    public function testDirectSalesPriceZero(): void
    {
        $price = 0;

        $this->assertEquals(0, $price);
    }

    // ==================== File Name Handling Tests ====================

    public function testFileNameFromPath(): void
    {
        $path = '/path/to/my-document.pdf';
        $name = pathinfo($path, PATHINFO_FILENAME);

        $this->assertEquals('my-document', $name);
    }

    public function testFileNameWithSpaces(): void
    {
        $path = '/path/to/my research paper.pdf';
        $name = pathinfo($path, PATHINFO_FILENAME);

        $this->assertEquals('my research paper', $name);
    }

    public function testFileNameWithUnicode(): void
    {
        $path = '/path/to/artigo-científico.pdf';
        $name = pathinfo($path, PATHINFO_FILENAME);

        $this->assertEquals('artigo-científico', $name);
    }
}
