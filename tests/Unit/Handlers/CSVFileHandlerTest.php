<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Handlers/CSVFileHandlerTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVFileHandlerTest
 *
 * @brief Tests for CSVFileHandler class
 */

namespace APP\plugins\importexport\csv\tests\Unit\Handlers;

use APP\plugins\importexport\csv\classes\handlers\CSVFileHandler;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CSVFileHandler::class)]
class CSVFileHandlerTest extends BaseTestCase
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

    // ==================== createReadableCSVFile Tests ====================

    public function testCreateReadableCSVFileWithValidPath(): void
    {
        $filePath = $this->tempDir . '/test.csv';
        file_put_contents($filePath, "header1,header2,header3\nvalue1,value2,value3");

        $result = CSVFileHandler::createReadableCSVFile($filePath);

        $this->assertInstanceOf(\SplFileObject::class, $result);
    }

    public function testCreateReadableCSVFileWithInvalidPath(): void
    {
        $filePath = $this->tempDir . '/nonexistent.csv';

        // Capture output since the method echoes error message
        ob_start();
        $result = CSVFileHandler::createReadableCSVFile($filePath);
        ob_end_clean();

        $this->assertNull($result);
    }

    public function testCreateReadableCSVFileCanIterate(): void
    {
        $filePath = $this->tempDir . '/test.csv';
        file_put_contents($filePath, "col1,col2,col3\nrow1col1,row1col2,row1col3\nrow2col1,row2col2,row2col3");

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $rows = [];
        foreach ($file as $row) {
            $rows[] = $row;
        }

        $this->assertCount(3, $rows);
        $this->assertEquals(['col1', 'col2', 'col3'], $rows[0]);
        $this->assertEquals(['row1col1', 'row1col2', 'row1col3'], $rows[1]);
        $this->assertEquals(['row2col1', 'row2col2', 'row2col3'], $rows[2]);
    }

    // ==================== createCSVFileInvalidRows Tests ====================

    public function testCreateCSVFileInvalidRowsCreatesFile(): void
    {
        $filename = 'invalid_test.csv';
        $headers = ['header1', 'header2', 'header3'];

        $result = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);

        $this->assertInstanceOf(\SplFileObject::class, $result);
        $this->assertFileExists($this->tempDir . '/' . $filename);
    }

    public function testCreateCSVFileInvalidRowsWritesHeaders(): void
    {
        $filename = 'invalid_test.csv';
        $headers = ['header1', 'header2', 'header3'];

        $result = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);

        // Close file and read it back
        $result = null;

        $content = file_get_contents($this->tempDir . '/' . $filename);
        $this->assertStringContainsString('header1', $content);
        $this->assertStringContainsString('header2', $content);
        $this->assertStringContainsString('header3', $content);
        $this->assertStringContainsString('error', $content);
    }

    // ==================== processFailedRow Tests ====================

    public function testProcessFailedRowWritesRowAndIncrementsCounter(): void
    {
        $filename = 'invalid_test.csv';
        $headers = ['col1', 'col2', 'col3'];

        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $fields = ['value1', 'value2', 'value3'];
        $rowSize = 3;
        $reason = 'Test error reason';
        $failedRows = 0;

        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, $rowSize, $reason, $failedRows);

        $this->assertEquals(1, $failedRows);
    }

    public function testProcessFailedRowIncrementsCounterMultipleTimes(): void
    {
        $filename = 'invalid_test.csv';
        $headers = ['col1', 'col2', 'col3'];

        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $rowSize = 3;
        $failedRows = 0;

        CSVFileHandler::processFailedRow($invalidCsvFile, ['a', 'b', 'c'], $rowSize, 'Error 1', $failedRows);
        CSVFileHandler::processFailedRow($invalidCsvFile, ['d', 'e', 'f'], $rowSize, 'Error 2', $failedRows);
        CSVFileHandler::processFailedRow($invalidCsvFile, ['g', 'h', 'i'], $rowSize, 'Error 3', $failedRows);

        $this->assertEquals(3, $failedRows);
    }

    // ==================== CSV Format Tests ====================

    public function testCreateReadableCSVFileHandlesQuotedFields(): void
    {
        $filePath = $this->tempDir . '/quoted.csv';
        file_put_contents($filePath, "name,description,value\n\"John Doe\",\"This is a, quoted field\",100");

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $rows = [];
        foreach ($file as $row) {
            $rows[] = $row;
        }

        $this->assertEquals('John Doe', $rows[1][0]);
        $this->assertEquals('This is a, quoted field', $rows[1][1]);
    }

    public function testCreateReadableCSVFileHandlesUnicode(): void
    {
        $filePath = $this->tempDir . '/unicode.csv';
        file_put_contents($filePath, "name,description\nJoão,Descrição em português\n田中,日本語の説明");

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $rows = [];
        foreach ($file as $row) {
            $rows[] = $row;
        }

        $this->assertEquals('João', $rows[1][0]);
        $this->assertEquals('Descrição em português', $rows[1][1]);
        $this->assertEquals('田中', $rows[2][0]);
    }

    // ==================== Edge Cases ====================

    public function testCreateReadableCSVFileWithEmptyFile(): void
    {
        $filePath = $this->tempDir . '/empty.csv';
        file_put_contents($filePath, '');

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $this->assertInstanceOf(\SplFileObject::class, $file);

        $rows = [];
        foreach ($file as $row) {
            if (!empty(array_filter($row))) {
                $rows[] = $row;
            }
        }

        $this->assertEmpty($rows);
    }

    public function testCreateReadableCSVFileWithOnlyHeaders(): void
    {
        $filePath = $this->tempDir . '/headers_only.csv';
        file_put_contents($filePath, "col1,col2,col3");

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $rows = [];
        foreach ($file as $row) {
            if (!empty(array_filter($row))) {
                $rows[] = $row;
            }
        }

        $this->assertCount(1, $rows);
        $this->assertEquals(['col1', 'col2', 'col3'], $rows[0]);
    }
}
