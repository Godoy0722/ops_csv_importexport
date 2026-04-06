<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Store/ImportResultStoreTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportResultStoreTest
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Test for ImportResultStore
 */

namespace APP\plugins\importexport\csv\tests\Unit\Store;

use APP\plugins\importexport\csv\classes\store\ImportResultStore;
use APP\plugins\importexport\csv\tests\BaseTestCase;

class ImportResultStoreTest extends BaseTestCase
{
    private ImportResultStore $store;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = $this->createTempDirectory();
        $this->store = new ImportResultStore($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testSaveAndGetReturnsStoredData(): void
    {
        $uuid = 'test-uuid-123';
        $data = [
            'status' => 'success',
            'importType' => 'preprints',
            'rowsProcessed' => 10,
            'rowsFailed' => 2,
            'capturedOutput' => 'Done.',
            'perFileResults' => ['invalid_data.csv'],
        ];

        $this->store->save($uuid, $data);
        $retrieved = $this->store->get($uuid);

        $this->assertNotNull($retrieved);
        $this->assertEquals('success', $retrieved['status']);
        $this->assertEquals('preprints', $retrieved['importType']);
        $this->assertEquals(10, $retrieved['rowsProcessed']);
        $this->assertEquals(2, $retrieved['rowsFailed']);
        $this->assertEquals('Done.', $retrieved['capturedOutput']);
        $this->assertEquals(['invalid_data.csv'], $retrieved['perFileResults']);
    }

    public function testGetWithUnknownUuidReturnsNull(): void
    {
        $result = $this->store->get('nonexistent-uuid');
        $this->assertNull($result);
    }

    public function testSaveCreatesDirectoryIfNotExists(): void
    {
        $nestedDir = $this->tempDir . '/nested/deep/store';
        $store = new ImportResultStore($nestedDir);

        $this->assertFalse(is_dir($nestedDir));

        $uuid = 'test-uuid-456';
        $data = [
            'status' => 'success',
            'importType' => 'preprints',
            'rowsProcessed' => 5,
            'rowsFailed' => 0,
            'capturedOutput' => 'Success.',
            'perFileResults' => [],
        ];

        $store->save($uuid, $data);

        $this->assertTrue(is_dir($nestedDir));
        $retrieved = $store->get($uuid);
        $this->assertNotNull($retrieved);
        $this->assertEquals('success', $retrieved['status']);

        // Cleanup nested directory
        $this->cleanupTempDirectory($nestedDir);
    }

    public function testSaveOverwritesPreviousData(): void
    {
        $uuid = 'test-uuid-789';

        // Save first version
        $data1 = [
            'status' => 'pending',
            'importType' => 'preprints',
            'rowsProcessed' => 0,
            'rowsFailed' => 0,
            'capturedOutput' => 'Starting...',
            'perFileResults' => [],
        ];
        $this->store->save($uuid, $data1);

        // Verify first version
        $retrieved1 = $this->store->get($uuid);
        $this->assertEquals('pending', $retrieved1['status']);
        $this->assertEquals('Starting...', $retrieved1['capturedOutput']);

        // Save second version with same UUID
        $data2 = [
            'status' => 'success',
            'importType' => 'preprints',
            'rowsProcessed' => 10,
            'rowsFailed' => 0,
            'capturedOutput' => 'Complete.',
            'perFileResults' => ['invalid_file.csv'],
        ];
        $this->store->save($uuid, $data2);

        // Verify second version overwrote first
        $retrieved2 = $this->store->get($uuid);
        $this->assertEquals('success', $retrieved2['status']);
        $this->assertEquals('Complete.', $retrieved2['capturedOutput']);
        $this->assertEquals(10, $retrieved2['rowsProcessed']);
    }
}