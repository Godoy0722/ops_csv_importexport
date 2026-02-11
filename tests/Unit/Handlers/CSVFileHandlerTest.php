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

        $this->expectException(\Exception::class);
        CSVFileHandler::createReadableCSVFile($filePath);
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

    // ==================== processFailedRow Content Verification ====================

    public function testProcessFailedRowWritesCorrectContent(): void
    {
        $filename = 'invalid_content_test.csv';
        $headers = ['col1', 'col2', 'col3'];

        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $fields = ['value1', 'value2', 'value3'];
        $failedRows = 0;

        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, 3, 'Missing field X', $failedRows);

        // Close file and read content
        $invalidCsvFile = null;

        $content = file_get_contents($this->tempDir . '/' . $filename);
        $this->assertStringContainsString('value1', $content);
        $this->assertStringContainsString('value2', $content);
        $this->assertStringContainsString('value3', $content);
        $this->assertStringContainsString('Missing field X', $content);
    }

    public function testProcessFailedRowPadsShortRows(): void
    {
        $filename = 'invalid_pad_test.csv';
        $headers = ['col1', 'col2', 'col3', 'col4'];

        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $fields = ['only1', 'only2']; // Fewer fields than expected
        $failedRows = 0;

        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, 4, 'Too few fields', $failedRows);

        $invalidCsvFile = null;

        $content = file_get_contents($this->tempDir . '/' . $filename);
        $this->assertStringContainsString('only1', $content);
        $this->assertStringContainsString('Too few fields', $content);
        $this->assertEquals(1, $failedRows);
    }

    public function testProcessFailedRowWithUnicodeContent(): void
    {
        $filename = 'invalid_unicode_test.csv';
        $headers = ['name', 'value'];

        $invalidCsvFile = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $fields = ['João', 'Descrição'];
        $failedRows = 0;

        CSVFileHandler::processFailedRow($invalidCsvFile, $fields, 2, 'Erro de validação', $failedRows);

        $invalidCsvFile = null;

        $content = file_get_contents($this->tempDir . '/' . $filename);
        $this->assertStringContainsString('João', $content);
        $this->assertStringContainsString('Descrição', $content);
        $this->assertStringContainsString('Erro de validação', $content);
    }

    // ==================== Error Path Tests ====================

    public function testCreateCSVFileInvalidRowsThrowsExceptionForInvalidDirectory(): void
    {
        $this->expectException(\Exception::class);
        CSVFileHandler::createCSVFileInvalidRows('/nonexistent/path/that/does/not/exist', 'invalid.csv', ['h1', 'h2']);
    }

    public function testProcessFailedRowThrowsExceptionOnWriteFailure(): void
    {
        /** @var \SplFileObject|\PHPUnit\Framework\MockObject\MockObject */
        $fileMock = $this->getMockBuilder(\SplFileObject::class)
            ->setConstructorArgs(['php://memory', 'w'])
            ->onlyMethods(['fputcsv', 'getFilename'])
            ->getMock();
        $fileMock->method('fputcsv')->willReturn(false);
        $fileMock->method('getFilename')->willReturn('test.csv');

        $failedRows = 0;
        $this->expectException(\Exception::class);
        CSVFileHandler::processFailedRow($fileMock, ['field1'], 1, 'reason', $failedRows);
    }

    // ==================== createCSVFileInvalidRows Additional Tests ====================

    public function testCreateCSVFileInvalidRowsWithEmptyHeaders(): void
    {
        $filename = 'invalid_empty_headers.csv';
        $headers = [];

        $result = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);

        $this->assertInstanceOf(\SplFileObject::class, $result);

        $result = null;

        $content = file_get_contents($this->tempDir . '/' . $filename);
        $this->assertStringContainsString('error', $content);
    }

    public function testCreateCSVFileInvalidRowsAppendsToExistingFile(): void
    {
        $filename = 'invalid_append_test.csv';
        $headers = ['col1', 'col2'];

        // Create first time
        $file1 = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $file1 = null;

        // Create second time - should append
        $file2 = CSVFileHandler::createCSVFileInvalidRows($this->tempDir, $filename, $headers);
        $file2 = null;

        $content = file_get_contents($this->tempDir . '/' . $filename);
        // Should have two header lines (since a+ mode appends)
        $this->assertGreaterThan(strlen("col1,col2,error\n"), strlen($content));
    }

    // ==================== createReadableCSVFile with special formats ====================

    public function testCreateReadableCSVFileWithNewlinesInQuotedFields(): void
    {
        $filePath = $this->tempDir . '/newline.csv';
        file_put_contents($filePath, "name,bio\n\"John\",\"Line 1\nLine 2\"");

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $this->assertInstanceOf(\SplFileObject::class, $file);
    }

    public function testCreateReadableCSVFileWithLargeContent(): void
    {
        $filePath = $this->tempDir . '/large.csv';
        $content = "col1,col2\n";
        for ($i = 0; $i < 100; $i++) {
            $content .= "row{$i}col1,row{$i}col2\n";
        }
        file_put_contents($filePath, $content);

        $file = CSVFileHandler::createReadableCSVFile($filePath);

        $rowCount = 0;
        foreach ($file as $row) {
            if (!empty(array_filter($row))) {
                $rowCount++;
            }
        }

        $this->assertEquals(101, $rowCount); // 1 header + 100 data rows
    }
}
