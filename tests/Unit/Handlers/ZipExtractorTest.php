<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Handlers/ZipExtractorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ZipExtractorTest
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Tests for ZipExtractor — extraction, source-dir resolution, and cleanup logic.
 */

namespace APP\plugins\importexport\csv\tests\Unit\Handlers;

use APP\plugins\importexport\csv\classes\exceptions\ZipExtractionException;
use APP\plugins\importexport\csv\classes\handlers\ZipExtractor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ZipArchive;

#[CoversClass(ZipExtractor::class)]
class ZipExtractorTest extends BaseTestCase
{
    private string $tempDir;
    private ZipExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir   = $this->createTempDirectory();
        $this->extractor = new ZipExtractor();
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();
    }

    // ==================== Helpers ====================

    /**
     * Create a ZIP file in the temp directory.
     *
     * @param array<string,string> $files  Map of entry-name => file-content.
     *
     * @return string Absolute path to the created ZIP file.
     */
    private function createZipWithFiles(array $files): string
    {
        $zipPath = $this->tempDir . '/test_' . uniqid() . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $entryName => $content) {
            $zip->addFromString($entryName, $content);
        }

        $zip->close();
        return $zipPath;
    }

    // ==================== extract() — happy paths ====================

    public function testExtractFlatZipReturnsDirWithCsvFiles(): void
    {
        $zipPath = $this->createZipWithFiles([
            'preprints.csv' => "title,author\nTest,Author",
            'users.csv'     => "email,name\ntest@example.com,Test",
        ]);

        $extractDir = $this->extractor->extract($zipPath, $this->tempDir);

        $this->assertDirectoryExists($extractDir);
        $this->assertFileExists($extractDir . '/preprints.csv');
        $this->assertFileExists($extractDir . '/users.csv');

        $this->cleanupTempDirectory($extractDir);
    }

    public function testExtractNestedZipReturnsDirWithCsvFiles(): void
    {
        $zipPath = $this->createZipWithFiles([
            'import/preprints.csv' => "title,author\nTest,Author",
            'import/users.csv'     => "email,name\ntest@example.com,Test",
        ]);

        $extractDir = $this->extractor->extract($zipPath, $this->tempDir);

        $this->assertDirectoryExists($extractDir);
        $this->assertFileExists($extractDir . '/import/preprints.csv');

        $this->cleanupTempDirectory($extractDir);
    }

    // ==================== extract() — error paths ====================

    public function testExtractCorruptZipThrowsException(): void
    {
        $corruptPath = $this->tempDir . '/corrupt.zip';
        file_put_contents($corruptPath, 'not a zip file at all');

        $this->expectException(ZipExtractionException::class);

        $this->extractor->extract($corruptPath, $this->tempDir);
    }

    public function testExtractZipWithPathTraversalThrowsException(): void
    {
        $zipPath = $this->createZipWithFiles([
            '../etc/passwd' => 'root:x:0:0:root:/root:/bin/bash',
        ]);

        $this->expectException(ZipExtractionException::class);

        $this->extractor->extract($zipPath, $this->tempDir);
    }

    public function testExtractZipWithAbsolutePathThrowsException(): void
    {
        $zipPath = $this->createZipWithFiles([
            '/etc/passwd' => 'root:x:0:0:root:/root:/bin/bash',
        ]);

        $this->expectException(ZipExtractionException::class);

        $this->extractor->extract($zipPath, $this->tempDir);
    }

    // ==================== resolveSourceDir() ====================

    public function testResolveSourceDirWithFlatCsvsReturnsExtractDir(): void
    {
        $dir = $this->createTempDirectory();
        file_put_contents($dir . '/preprints.csv', 'title,author');
        file_put_contents($dir . '/users.csv', 'email,name');

        $resolved = ZipExtractor::resolveSourceDir($dir);

        $this->assertEquals($dir, $resolved);
        $this->cleanupTempDirectory($dir);
    }

    public function testResolveSourceDirWithSingleSubdirReturnsSubdir(): void
    {
        $dir    = $this->createTempDirectory();
        $subdir = $dir . '/my_import';
        mkdir($subdir);
        file_put_contents($subdir . '/preprints.csv', 'title,author');

        $resolved = ZipExtractor::resolveSourceDir($dir);

        $this->assertEquals($subdir, $resolved);
        $this->cleanupTempDirectory($dir);
    }

    public function testResolveSourceDirWithMultipleSubdirsReturnsExtractDir(): void
    {
        $dir = $this->createTempDirectory();
        mkdir($dir . '/subdir_a');
        mkdir($dir . '/subdir_b');
        file_put_contents($dir . '/subdir_a/preprints.csv', 'title,author');
        file_put_contents($dir . '/subdir_b/users.csv', 'email,name');

        $resolved = ZipExtractor::resolveSourceDir($dir);

        $this->assertEquals($dir, $resolved);
        $this->cleanupTempDirectory($dir);
    }

    // ==================== cleanupExpired() ====================

    public function testCleanupExpiredRemovesOldDirectories(): void
    {
        $baseDir = $this->createTempDirectory();

        // Create an "old" directory and backdate its mtime
        $oldDir = $baseDir . '/csv_import_old';
        mkdir($oldDir);
        file_put_contents($oldDir . '/data.csv', 'title,author');
        touch($oldDir, time() - 7200); // 2 hours old

        // Create a "fresh" directory (default mtime = now)
        $freshDir = $baseDir . '/csv_import_fresh';
        mkdir($freshDir);
        file_put_contents($freshDir . '/data.csv', 'title,author');

        ZipExtractor::cleanupExpired($baseDir, 3600);

        $this->assertDirectoryDoesNotExist($oldDir);
        $this->assertDirectoryExists($freshDir);

        $this->cleanupTempDirectory($baseDir);
    }

    public function testCleanupExpiredDoesNothingWhenDirDoesNotExist(): void
    {
        $nonExistentDir = $this->tempDir . '/does_not_exist_' . uniqid();

        // Should not throw any exception
        ZipExtractor::cleanupExpired($nonExistentDir);

        $this->addToAssertionCount(1); // reaching here means no exception was thrown
    }
}
