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

use APP\core\Application;
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

    // ==================== File Stage Tests ====================

    public function testFileStageProof(): void
    {
        $fileStage = SubmissionFile::SUBMISSION_FILE_PROOF;

        $this->assertIsInt($fileStage);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessCreatesAndReturnsSubmissionFile(): void
    {
        $subFileRepoMock = $this->mockSubmissionFileRepository();

        $returnedFile = new SubmissionFile();
        $returnedFile->setId(10);
        $returnedFile->setData('submissionId', 1);

        $subFileRepoMock->shouldReceive('add')->once()->andReturn(10);
        $subFileRepoMock->shouldReceive('get')->with(10)->andReturn($returnedFile);

        $filePath = $this->createTestFile($this->tempDir, 'test-paper.pdf', '%PDF-1.4 fake content');

        $result = SubmissionFileProcessor::process(
            'en',
            5,
            1,
            $filePath,
            2,
            100
        );

        $this->assertInstanceOf(SubmissionFile::class, $result);
        $this->assertEquals(10, $result->getId());
    }

    public function testProcessSetsCorrectData(): void
    {
        $capturedFile = null;
        $subFileRepoMock = $this->mockSubmissionFileRepository();

        $subFileRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($submissionFile) use (&$capturedFile) {
                $capturedFile = $submissionFile;
                return 10;
            });

        $returnedFile = new SubmissionFile();
        $returnedFile->setId(10);
        $subFileRepoMock->shouldReceive('get')->with(10)->andReturn($returnedFile);

        $filePath = $this->createTestFile($this->tempDir, 'my-research.pdf', '%PDF-1.4 test');

        SubmissionFileProcessor::process('en', 5, 42, $filePath, 3, 200);

        $this->assertNotNull($capturedFile);
        $this->assertEquals(42, $capturedFile->getData('submissionId'));
        $this->assertEquals(5, $capturedFile->getData('uploaderUserId'));
        $this->assertEquals(200, $capturedFile->getData('fileId'));
        $this->assertEquals(3, $capturedFile->getData('genreId'));
        $this->assertEquals(SubmissionFile::SUBMISSION_FILE_PROOF, $capturedFile->getData('fileStage'));
        $this->assertEquals('en', $capturedFile->getData('locale'));
        $this->assertEquals('my-research', $capturedFile->getData('name', 'en'));
    }

    public function testProcessWithDescription(): void
    {
        $capturedFile = null;
        $subFileRepoMock = $this->mockSubmissionFileRepository();

        $subFileRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($submissionFile) use (&$capturedFile) {
                $capturedFile = $submissionFile;
                return 10;
            });

        $returnedFile = new SubmissionFile();
        $returnedFile->setId(10);
        $subFileRepoMock->shouldReceive('get')->with(10)->andReturn($returnedFile);

        $filePath = $this->createTestFile($this->tempDir, 'supplement.xlsx', 'fake xlsx');

        SubmissionFileProcessor::process('en', 5, 1, $filePath, 2, 100, 'Supplementary data');

        $this->assertEquals('Supplementary data', $capturedFile->getData('description', 'en'));
    }

    public function testProcessWithoutDescription(): void
    {
        $capturedFile = null;
        $subFileRepoMock = $this->mockSubmissionFileRepository();

        $subFileRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($submissionFile) use (&$capturedFile) {
                $capturedFile = $submissionFile;
                return 10;
            });

        $returnedFile = new SubmissionFile();
        $returnedFile->setId(10);
        $subFileRepoMock->shouldReceive('get')->with(10)->andReturn($returnedFile);

        $filePath = $this->createTestFile($this->tempDir, 'paper.pdf', 'fake pdf');

        SubmissionFileProcessor::process('en', 5, 1, $filePath, 2, 100);

        $this->assertNull($capturedFile->getData('description', 'en'));
    }

    // ==================== updateAssocInfo() Integration Tests ====================

    public function testUpdateAssocInfoCallsEdit(): void
    {
        $editParams = null;
        $subFileRepoMock = $this->mockSubmissionFileRepository();

        $subFileRepoMock->shouldReceive('edit')
            ->andReturnUsing(function ($submissionFile, $params) use (&$editParams) {
                $editParams = $params;
            });

        $submissionFile = new SubmissionFile();
        $submissionFile->setId(1);

        SubmissionFileProcessor::updateAssocInfo($submissionFile, 99);

        $this->assertEquals(Application::ASSOC_TYPE_REPRESENTATION, $editParams['assocType']);
        $this->assertEquals(99, $editParams['assocId']);
    }
}
