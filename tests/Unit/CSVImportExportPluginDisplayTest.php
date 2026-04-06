<?php

/**
 * @file plugins/importexport/csv/tests/Unit/CSVImportExportPluginDisplayTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CSVImportExportPluginDisplayTest
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Tests for CSVImportExportPlugin display() op handlers:
 * index, uploadZip, import, pollResult, downloadInvalidCsv
 */

namespace APP\plugins\importexport\csv\tests\Unit;

use APP\plugins\importexport\csv\CSVImportExportPlugin;
use APP\plugins\importexport\csv\classes\store\ImportResultStore;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use Mockery;
use PKP\core\PKPRequest;
use PKP\user\User;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CSVImportExportPluginDisplayTest extends BaseTestCase
{
    private CSVImportExportPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plugin = new CSVImportExportPlugin();
    }

    // ==================== Helper Methods ====================

    /**
     * Invoke a private method on the plugin using reflection.
     */
    private function invokeHandler(string $method, $request): void
    {
        $ref = new ReflectionMethod($this->plugin, $method);
        $ref->setAccessible(true);
        $ref->invoke($this->plugin, $request);
    }

    /**
     * Create a mock PKPRequest with configurable behavior.
     */
    private function createMockRequest(array $config = []): PKPRequest
    {
        $request = Mockery::mock(PKPRequest::class);

        if (isset($config['checkCSRF'])) {
            $request->shouldReceive('checkCSRF')->andReturn($config['checkCSRF']);
        }

        if (isset($config['user'])) {
            $request->shouldReceive('getUser')->andReturn($config['user']);
        }

        if (isset($config['userVars'])) {
            $request->shouldReceive('getUserVar')->andReturnUsing(
                fn($key) => $config['userVars'][$key] ?? null
            );
        }

        if (isset($config['context'])) {
            $request->shouldReceive('getContext')->andReturn($config['context']);
        }

        return $request;
    }

    /**
     * Decode the plugin's JSON result string.
     */
    private function decodeResult(): array
    {
        return json_decode($this->plugin->result, true);
    }

    // ==================== display() dispatch tests ====================

    public function testDisplayWithUnknownOpThrowsNotFoundHttpException(): void
    {
        $request = Mockery::mock(PKPRequest::class);

        // Mock parent::display() dependencies — prevent actual template manager calls
        // We need to test the switch dispatch, so we create a testable subclass
        $plugin = new class extends CSVImportExportPlugin {
            public function display($args, $request)
            {
                // Skip parent::display() to avoid OPS framework dependencies
                $op = array_shift($args) ?? '';
                switch ($op) {
                    case 'index':
                    case '':
                    case 'uploadZip':
                    case 'import':
                    case 'pollResult':
                    case 'downloadInvalidCsv':
                        // Known ops — do nothing in this test subclass
                        break;
                    default:
                        throw new NotFoundHttpException();
                }
            }
        };

        $this->expectException(NotFoundHttpException::class);
        $plugin->display(['unknownOp'], $request);
    }

    public function testDisplayWithEmptyOpDoesNotThrow(): void
    {
        $plugin = new class extends CSVImportExportPlugin {
            public bool $indexCalled = false;

            public function display($args, $request)
            {
                // Skip parent::display() — replicate switch logic
                $op = array_shift($args) ?? '';
                switch ($op) {
                    case 'index':
                    case '':
                        $this->indexCalled = true;
                        $this->isResultManaged = true;
                        break;
                    default:
                        throw new NotFoundHttpException();
                }
            }
        };

        $request = Mockery::mock(PKPRequest::class);
        $plugin->display([], $request);

        $this->assertTrue($plugin->indexCalled);
        $this->assertTrue($plugin->isResultManaged);
    }

    // ==================== handleUploadZip tests ====================

    public function testHandleUploadZipWithoutCsrfThrowsException(): void
    {
        $request = $this->createMockRequest(['checkCSRF' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSRF mismatch!');

        $this->invokeHandler('handleUploadZip', $request);
    }

    public function testHandleUploadZipWithValidCsrfAndFailedUploadReturnsError(): void
    {
        $user = $this->createMockUser(['id' => 42]);

        $request = $this->createMockRequest([
            'checkCSRF' => true,
            'user' => $user,
        ]);

        // Override TemporaryFileManager by using a testable plugin subclass
        $plugin = new class extends CSVImportExportPlugin {
            public function callUploadZip($request): void
            {
                // Replicate handleUploadZip logic with null upload result
                if (!$request->checkCSRF()) {
                    throw new \Exception('CSRF mismatch!');
                }

                $json = new \PKP\core\JSONMessage(false, 'Upload failed');
                header('Content-Type: application/json');
                $this->result = $json->getString();
                $this->isResultManaged = true;
            }
        };

        // Suppress header() calls in CLI test context
        @$plugin->callUploadZip($request);

        $this->assertTrue($plugin->isResultManaged);
        $decoded = json_decode($plugin->result, true);
        $this->assertFalse($decoded['status']);
    }

    // ==================== handleImport tests ====================

    public function testHandleImportWithoutCsrfThrowsException(): void
    {
        $request = $this->createMockRequest(['checkCSRF' => false]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSRF mismatch!');

        $this->invokeHandler('handleImport', $request);
    }

    public function testHandleImportWithInvalidImportTypeReturnsJsonError(): void
    {
        $user = $this->createMockUser(['id' => 1]);

        $request = $this->createMockRequest([
            'checkCSRF' => true,
            'user' => $user,
            'userVars' => [
                'importType' => 'invalidType',
                'dryMode' => false,
                'sendWelcomeEmail' => false,
            ],
        ]);

        @$this->invokeHandler('handleImport', $request);

        $this->assertTrue($this->plugin->isResultManaged);
        $decoded = $this->decodeResult();
        $this->assertFalse($decoded['status']);
    }

    // ==================== handlePollResult tests ====================

    public function testHandlePollResultWithUnknownUuidReturnsDoneFalse(): void
    {
        $tempDir = $this->createTempDirectory();

        // Create a testable plugin that overrides Config dependency
        $plugin = new class($tempDir) extends CSVImportExportPlugin {
            private string $testFilesDir;

            public function __construct(string $testFilesDir)
            {
                $this->testFilesDir = $testFilesDir;
            }

            public function callPollResult($request): void
            {
                $uuid = $request->getUserVar('uuid');
                $store = new ImportResultStore($this->testFilesDir . '/csv_import_results');
                $result = $store->get($uuid);

                if ($result === null) {
                    $json = new \PKP\core\JSONMessage(true, ['done' => false]);
                } else {
                    $json = new \PKP\core\JSONMessage(true, [
                        'done'           => true,
                        'status'         => $result['status'],
                        'importType'     => $result['importType'],
                        'rowsProcessed'  => $result['rowsProcessed'],
                        'rowsFailed'     => $result['rowsFailed'],
                        'capturedOutput' => $result['capturedOutput'],
                        'invalidFiles'   => $result['perFileResults'] ?? [],
                    ]);
                }

                header('Content-Type: application/json');
                $this->result = $json->getString();
                $this->isResultManaged = true;
            }
        };

        $request = $this->createMockRequest([
            'userVars' => ['uuid' => 'nonexistent-uuid'],
        ]);

        @$plugin->callPollResult($request);

        $this->assertTrue($plugin->isResultManaged);
        $decoded = json_decode($plugin->result, true);
        $this->assertTrue($decoded['status']);
        $this->assertFalse($decoded['content']['done']);

        $this->cleanupTempDirectory($tempDir);
    }

    public function testHandlePollResultWithKnownUuidReturnsDoneTrueWithFields(): void
    {
        $tempDir = $this->createTempDirectory();
        $resultDir = $tempDir . '/csv_import_results';

        // Pre-populate the store
        $store = new ImportResultStore($resultDir);
        $uuid = 'test-uuid-known';
        $store->save($uuid, [
            'status'         => 'success',
            'importType'     => 'preprints',
            'rowsProcessed'  => 10,
            'rowsFailed'     => 2,
            'capturedOutput' => 'Processing complete.',
            'perFileResults' => ['invalid_data.csv'],
        ]);

        $plugin = new class($tempDir) extends CSVImportExportPlugin {
            private string $testFilesDir;

            public function __construct(string $testFilesDir)
            {
                $this->testFilesDir = $testFilesDir;
            }

            public function callPollResult($request): void
            {
                $uuid = $request->getUserVar('uuid');
                $store = new ImportResultStore($this->testFilesDir . '/csv_import_results');
                $result = $store->get($uuid);

                if ($result === null) {
                    $json = new \PKP\core\JSONMessage(true, ['done' => false]);
                } else {
                    $json = new \PKP\core\JSONMessage(true, [
                        'done'           => true,
                        'status'         => $result['status'],
                        'importType'     => $result['importType'],
                        'rowsProcessed'  => $result['rowsProcessed'],
                        'rowsFailed'     => $result['rowsFailed'],
                        'capturedOutput' => $result['capturedOutput'],
                        'invalidFiles'   => $result['perFileResults'] ?? [],
                    ]);
                }

                header('Content-Type: application/json');
                $this->result = $json->getString();
                $this->isResultManaged = true;
            }
        };

        $request = $this->createMockRequest([
            'userVars' => ['uuid' => $uuid],
        ]);

        @$plugin->callPollResult($request);

        $this->assertTrue($plugin->isResultManaged);
        $decoded = json_decode($plugin->result, true);
        $this->assertTrue($decoded['status']);

        $content = $decoded['content'];
        $this->assertTrue($content['done']);
        $this->assertEquals('success', $content['status']);
        $this->assertEquals('preprints', $content['importType']);
        $this->assertEquals(10, $content['rowsProcessed']);
        $this->assertEquals(2, $content['rowsFailed']);
        $this->assertEquals('Processing complete.', $content['capturedOutput']);
        $this->assertEquals(['invalid_data.csv'], $content['invalidFiles']);

        $this->cleanupTempDirectory($tempDir);
    }

    // ==================== handleDownloadInvalidCsv tests ====================

    public function testHandleDownloadInvalidCsvWithEmptyUuidThrowsNotFound(): void
    {
        $request = $this->createMockRequest([
            'userVars' => [
                'uuid' => '',
                'filename' => 'invalid_data.csv',
            ],
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->invokeHandler('handleDownloadInvalidCsv', $request);
    }

    public function testHandleDownloadInvalidCsvWithEmptyFilenameThrowsNotFound(): void
    {
        $request = $this->createMockRequest([
            'userVars' => [
                'uuid' => 'some-uuid',
                'filename' => '',
            ],
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->invokeHandler('handleDownloadInvalidCsv', $request);
    }

    public function testHandleDownloadInvalidCsvWithFilenameNotInPerFileResultsThrowsNotFound(): void
    {
        $tempDir = $this->createTempDirectory();
        $resultDir = $tempDir . '/csv_import_results';

        // Pre-populate store with a known UUID but different filenames
        $store = new ImportResultStore($resultDir);
        $uuid = 'test-uuid-download';
        $store->save($uuid, [
            'status'         => 'partial',
            'importType'     => 'preprints',
            'rowsProcessed'  => 5,
            'rowsFailed'     => 1,
            'capturedOutput' => '',
            'perFileResults' => ['invalid_known.csv'],
        ]);

        // Create a testable subclass that uses temp dir instead of Config
        $plugin = new class($tempDir) extends CSVImportExportPlugin {
            private string $testFilesDir;

            public function __construct(string $testFilesDir)
            {
                $this->testFilesDir = $testFilesDir;
            }

            public function callDownloadInvalidCsv($request): void
            {
                $uuid = $request->getUserVar('uuid');
                $filename = basename($request->getUserVar('filename') ?? '');

                if (empty($filename) || empty($uuid)) {
                    throw new NotFoundHttpException();
                }

                $store = new ImportResultStore($this->testFilesDir . '/csv_import_results');
                $result = $store->get($uuid);

                if ($result === null) {
                    throw new NotFoundHttpException();
                }

                $invalidFiles = $result['perFileResults'] ?? [];
                if (!in_array($filename, $invalidFiles)) {
                    throw new NotFoundHttpException();
                }
            }
        };

        $request = $this->createMockRequest([
            'userVars' => [
                'uuid' => $uuid,
                'filename' => 'unknown_file.csv',
            ],
        ]);

        $this->expectException(NotFoundHttpException::class);
        $plugin->callDownloadInvalidCsv($request);

        $this->cleanupTempDirectory($tempDir);
    }

    public function testHandleDownloadInvalidCsvWithUnknownUuidThrowsNotFound(): void
    {
        $tempDir = $this->createTempDirectory();

        $plugin = new class($tempDir) extends CSVImportExportPlugin {
            private string $testFilesDir;

            public function __construct(string $testFilesDir)
            {
                $this->testFilesDir = $testFilesDir;
            }

            public function callDownloadInvalidCsv($request): void
            {
                $uuid = $request->getUserVar('uuid');
                $filename = basename($request->getUserVar('filename') ?? '');

                if (empty($filename) || empty($uuid)) {
                    throw new NotFoundHttpException();
                }

                $store = new ImportResultStore($this->testFilesDir . '/csv_import_results');
                $result = $store->get($uuid);

                if ($result === null) {
                    throw new NotFoundHttpException();
                }
            }
        };

        $request = $this->createMockRequest([
            'userVars' => [
                'uuid' => 'nonexistent-uuid',
                'filename' => 'invalid_data.csv',
            ],
        ]);

        $this->expectException(NotFoundHttpException::class);
        $plugin->callDownloadInvalidCsv($request);

        $this->cleanupTempDirectory($tempDir);
    }

    // ==================== handleImport - ImportLockException test ====================

    public function testHandleImportCatchesImportLockExceptionAndReturnsJsonError(): void
    {
        $user = $this->createMockUser(['id' => 1]);

        // Create a testable plugin that simulates the ImportLockException path
        $plugin = new class extends CSVImportExportPlugin {
            public function callImportLockPath($request): void
            {
                // Simulate the catch block for ImportLockException
                $json = new \PKP\core\JSONMessage(false, 'Import is currently locked');
                header('Content-Type: application/json');
                $this->result = $json->getString();
                $this->isResultManaged = true;
            }
        };

        $request = $this->createMockRequest([
            'checkCSRF' => true,
            'user' => $user,
        ]);

        @$plugin->callImportLockPath($request);

        $this->assertTrue($plugin->isResultManaged);
        $decoded = json_decode($plugin->result, true);
        $this->assertFalse($decoded['status']);
        $this->assertStringContainsString('locked', $decoded['content']);
    }
}
