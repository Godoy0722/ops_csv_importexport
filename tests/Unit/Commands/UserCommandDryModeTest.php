<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Commands/UserCommandDryModeTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommandDryModeTest
 *
 * @brief Tests for UserCommand dry-mode behavior — transaction lifecycle,
 *        CachedEntities reset, reporter output, welcome email guard, exit codes.
 */

namespace APP\plugins\importexport\csv\tests\Unit\Commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\commands\UserCommand;
use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\classes\handlers\DryModeReporter;
use APP\plugins\importexport\csv\classes\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use APP\server\ServerDAO;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PKP\db\DAORegistry;

#[CoversClass(UserCommand::class)]
class UserCommandDryModeTest extends BaseTestCase
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

    /**
     * Consolidates mock setup for all dry-mode tests.
     *
     * Key design notes:
     * - Uses DAORegistry::registerDAO to mock ServerDAO so that getCachedServer()
     *   continues to return the server even after CachedEntities::reset() clears
     *   the static cache between files in multi-file tests.
     * - createCSVFileInvalidRows returns a real writable SplFileObject so that
     *   the catch block reaches processFailedRow (avoids the `continue 2` early exit).
     * - processFailedRow mock increments &$failedRows so that $this->failedRows
     *   is tracked correctly for exit-code calculations.
     *
     * @return array<string, mixed>
     */
    private function setupDryModeMocks(): array
    {
        $mockUser = $this->createMockUser(['id' => 1]);
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();

        // Register a mock ServerDAO so getCachedServer() works after CachedEntities::reset().
        // This avoids needing to overload CachedEntities (which BaseTestCase already loads).
        $serverDaoMock = Mockery::mock(ServerDAO::class);
        $serverDaoMock->shouldReceive('getByPath')
            ->with('testserver')
            ->andReturn($server)
            ->byDefault();
        DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        // Pre-populate cache so the first row is a cache hit (avoids DAO call on first row).
        CachedEntities::$servers['testserver'] = $server;

        // Overload InvalidRowValidations — bypass all validations by default
        $validationMock = Mockery::mock('overload:' . InvalidRowValidations::class);
        $validationMock->shouldReceive('validateRowContainAllFields')->byDefault();
        $validationMock->shouldReceive('validateRowHasAllRequiredFields')->byDefault();
        $validationMock->shouldReceive('validateServerIsValid')->byDefault();
        $validationMock->shouldReceive('validateUserAlreadyExistsWithThisEmail')->byDefault();
        $validationMock->shouldReceive('validateUserAlreadyExistsWithThisUsername')->byDefault();
        $validationMock->shouldReceive('validateAllUserGroupsAreValid')->byDefault();
        $validationMock->shouldReceive('validateOrcid')->byDefault();

        // Overload UsersProcessor
        $usersProcessorMock = Mockery::mock('overload:' . UsersProcessor::class);
        $usersProcessorMock->shouldReceive('process')->andReturn($mockUser)->byDefault();

        // Overload UserInterestsProcessor
        $userInterestsMock = Mockery::mock('overload:' . UserInterestsProcessor::class);
        $userInterestsMock->shouldReceive('process')->byDefault();

        // Overload UserGroupsProcessor
        $userGroupsMock = Mockery::mock('overload:' . UserGroupsProcessor::class);
        $userGroupsMock->shouldReceive('process')->byDefault();

        // Do NOT overload CsvFileHandler — the real static methods work correctly:
        // - createReadableCSVFile creates a real SplFileObject from the CSV path
        // - createCSVFileInvalidRows creates a real writable file in sourceDir
        // - processFailedRow writes to that file AND increments &$failedRows (by-ref)
        // Mocking CsvFileHandler would break the &$failedRows reference that drives
        // the exit-code calculation.

        // Overload DryModeReporter
        $dryModeReporterMock = Mockery::mock('overload:' . DryModeReporter::class);
        $dryModeReporterMock->shouldReceive('printFileHeader')->byDefault();
        $dryModeReporterMock->shouldReceive('printTableHeader')->byDefault();
        $dryModeReporterMock->shouldReceive('printFailedRow')->byDefault();
        $dryModeReporterMock->shouldReceive('printFileSummary')->byDefault();
        $dryModeReporterMock->shouldReceive('printGrandTotal')->byDefault();

        // Mock DB facade — swap so beginTransaction/rollBack/statement are interceptable
        $realDb = DB::getFacadeRoot();
        $dbMock = Mockery::mock($realDb)->makePartial();
        $dbMock->shouldReceive('statement')->withAnyArgs()->byDefault();
        $dbMock->shouldReceive('beginTransaction')->byDefault();
        $dbMock->shouldReceive('rollBack')->byDefault();
        DB::swap($dbMock);

        return [
            'validationMock' => $validationMock,
            'usersProcessorMock' => $usersProcessorMock,
            'dbMock' => $dbMock,
            'server' => $server,
            'dryModeReporterMock' => $dryModeReporterMock,
        ];
    }

    // ==================== Transaction Lifecycle ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeAllValidRowsExitsZeroWithTransactionRollback(): void
    {
        $mocks = $this->setupDryModeMocks();
        $mocks['dbMock']->shouldReceive('beginTransaction')->once();
        $mocks['dbMock']->shouldReceive('rollBack')->once();

        $this->createTestCsvFile(
            $this->tempDir,
            'users.csv',
            RequiredUserHeaders::$userHeaders,
            [CsvTestDataBuilder::minimalUserRow()]
        );

        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        // D-03: CachedEntities must be empty after reset() runs post-rollback
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$userGroupIds);
        $this->assertEmpty(CachedEntities::$userGroups);
        $this->assertEmpty(CachedEntities::$users);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeMixedRowsExitsOneAndReportsFailures(): void
    {
        $mocks = $this->setupDryModeMocks();
        $mocks['dbMock']->shouldReceive('beginTransaction')->once();
        $mocks['dbMock']->shouldReceive('rollBack')->once();

        // First call passes, second throws RowValidationException
        $callCount = 0;
        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount >= 2) {
                    throw new RowValidationException('Missing required field');
                }
            });

        $this->createTestCsvFile(
            $this->tempDir,
            'users.csv',
            RequiredUserHeaders::$userHeaders,
            [
                CsvTestDataBuilder::minimalUserRow(),
                CsvTestDataBuilder::minimalUserRow(),
            ]
        );

        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        // D-06: exit code 1 when any row fails
        $this->assertSame(1, $exitCode);

        // D-07: reporter must have shown the failed row
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFailedRow');
    }

    // ==================== CachedEntities Reset ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeMultiFileResetsCachedEntitiesBetweenFiles(): void
    {
        $mocks = $this->setupDryModeMocks();
        $mocks['dbMock']->shouldReceive('beginTransaction')->twice();
        $mocks['dbMock']->shouldReceive('rollBack')->twice();

        // Create two CSV files — DirectoryIterator order is filesystem-dependent,
        // so both use identical data to keep the test deterministic.
        $this->createTestCsvFile(
            $this->tempDir,
            'users1.csv',
            RequiredUserHeaders::$userHeaders,
            [CsvTestDataBuilder::minimalUserRow()]
        );
        $this->createTestCsvFile(
            $this->tempDir,
            'users2.csv',
            RequiredUserHeaders::$userHeaders,
            [CsvTestDataBuilder::minimalUserRow()]
        );

        // Also populate the users cache to verify it gets cleared by reset()
        CachedEntities::$users['testuser'] = $this->createMockUser();

        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $command->run();
        ob_get_clean();

        // After the second file, reset() must have cleared all caches
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$users);

        // Both files were processed — printFileHeader called twice
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFileHeader')->twice();
    }

    // ==================== Reporter Output ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeReporterCalledWithCorrectSequence(): void
    {
        $mocks = $this->setupDryModeMocks();

        $this->createTestCsvFile(
            $this->tempDir,
            'users.csv',
            RequiredUserHeaders::$userHeaders,
            [CsvTestDataBuilder::minimalUserRow()]
        );

        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $command->run();
        ob_get_clean();

        $mocks['dryModeReporterMock']->shouldHaveReceived('printFileHeader')->once();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFileSummary')->once();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printGrandTotal')->once();
        // No failures means no table header
        $mocks['dryModeReporterMock']->shouldNotHaveReceived('printTableHeader');

        // Explicit assertion to satisfy PHPUnit risky-test detection
        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeReporterShowsTableHeaderOnlyWhenFailuresExist(): void
    {
        $mocks = $this->setupDryModeMocks();

        // Make validateRowContainAllFields throw on every call (all rows fail)
        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->andThrow(new RowValidationException('Row is invalid'));

        $this->createTestCsvFile(
            $this->tempDir,
            'users.csv',
            RequiredUserHeaders::$userHeaders,
            [CsvTestDataBuilder::minimalUserRow()]
        );

        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $command->run();
        ob_get_clean();

        // Failures present — table header must appear
        $mocks['dryModeReporterMock']->shouldHaveReceived('printTableHeader')->once();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFailedRow')->once();

        $this->assertTrue(true);
    }

    // ==================== Welcome Email Guard ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeBlocksWelcomeEmailEvenWhenFlagIsSet(): void
    {
        $this->setupDryModeMocks();

        // Verify WelcomeEmailHandler is never invoked
        $welcomeMock = Mockery::mock('overload:' . WelcomeEmailHandler::class);
        $welcomeMock->shouldNotReceive('sendWelcomeEmail');

        $this->createTestCsvFile(
            $this->tempDir,
            'users.csv',
            RequiredUserHeaders::$userHeaders,
            [CsvTestDataBuilder::minimalUserRow()]
        );

        // sendWelcomeEmail: true — dry-mode must suppress it
        $command = new UserCommand($this->tempDir, $this->createMockUser(), true, dryMode: true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        // Mockery expectations automatically verify sendWelcomeEmail was not called on tearDown
    }

    // ==================== Edge Cases ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeEmptyDirectoryExitsZero(): void
    {
        $mocks = $this->setupDryModeMocks();

        // No CSV files in temp dir
        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        // Grand total still called even with no files
        $mocks['dryModeReporterMock']->shouldHaveReceived('printGrandTotal')->once();

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeFileWithOnlyInvalidRowsExitsOne(): void
    {
        $mocks = $this->setupDryModeMocks();

        // All rows throw RowValidationException
        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->andThrow(new RowValidationException('Invalid row'));

        $this->createTestCsvFile(
            $this->tempDir,
            'users.csv',
            RequiredUserHeaders::$userHeaders,
            [
                CsvTestDataBuilder::minimalUserRow(),
                CsvTestDataBuilder::minimalUserRow(),
            ]
        );

        $command = new UserCommand($this->tempDir, $this->createMockUser(), false, dryMode: true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        // All rows failed — exit code must be 1
        $this->assertSame(1, $exitCode);
    }
}
