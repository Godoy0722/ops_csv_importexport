<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Commands/PreprintCommandDryModeTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintCommandDryModeTest
 *
 * @brief Tests for PreprintCommand dry-mode behavior
 */

namespace APP\plugins\importexport\csv\tests\Unit\Commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\commands\PreprintCommand;
use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\classes\handlers\CsvFileHandler;
use APP\plugins\importexport\csv\classes\handlers\DryModeReporter;
use APP\plugins\importexport\csv\classes\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\StatisticsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionFileProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PKP\submissionFile\SubmissionFile;


#[CoversClass(PreprintCommand::class)]
class PreprintCommandDryModeTest extends BaseTestCase
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
     * Set up all overload mocks needed for PreprintCommand dry-mode run() tests.
     * Mirrors setupAllMocks() from PreprintCommandTest, plus DB facade mock and
     * DryModeReporter overload.
     *
     * @return array<string, mixed>
     */
    private function setupDryModeMocks(): array
    {
        // Overload InvalidRowValidations — bypass all validations by default
        $validationMock = Mockery::mock('overload:' . InvalidRowValidations::class);
        $validationMock->shouldReceive('validateRowContainAllFields')->byDefault();
        $validationMock->shouldReceive('validateRowHasAllRequiredFields')->byDefault();
        $validationMock->shouldReceive('validatePreprintVersioningFields')->byDefault();
        $validationMock->shouldReceive('validateSectionFields')->byDefault();
        $validationMock->shouldReceive('validateNoDuplicateVersion')->byDefault();
        $validationMock->shouldReceive('validatePreprintGalleys')->byDefault();
        $validationMock->shouldReceive('validateSupplementaryFiles')->byDefault();
        $validationMock->shouldReceive('validateSupplementaryDescriptions')->byDefault();
        $validationMock->shouldReceive('validateReferencesFile')->byDefault();
        $validationMock->shouldReceive('validateVorDoi')->byDefault();
        $validationMock->shouldReceive('validateFunders')->byDefault();
        $validationMock->shouldReceive('validateEmail')->byDefault();
        $validationMock->shouldReceive('validateServerIsValid')->byDefault();
        $validationMock->shouldReceive('validateServerLocale')->byDefault();
        $validationMock->shouldReceive('validateGenreIdValid')->byDefault();
        $validationMock->shouldReceive('validateUserGroupId')->byDefault();
        $validationMock->shouldReceive('validateFundingPluginEnabled')->byDefault();
        $validationMock->shouldReceive('validateFundersCrossrefRegistry')->byDefault();
        $validationMock->shouldReceive('validatePublicationWasSuccessfullyCreated')->byDefault();
        $validationMock->shouldReceive('validatePreprintViews')->byDefault();
        $validationMock->shouldReceive('validateGalleyViews')->byDefault();
        $validationMock->shouldReceive('versionExistsInAnyLocale')->andReturn(false)->byDefault();

        // Overload PublicationProcessor
        $pubMock = $this->createMockPublication(['id' => 1, 'submissionId' => 1]);
        $publicationProcessorMock = Mockery::mock('overload:' . PublicationProcessor::class);
        $publicationProcessorMock->shouldReceive('createInitialPublication')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('process')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('updatePrimaryContactId')->byDefault();
        $publicationProcessorMock->shouldReceive('updateCoverage')->byDefault();
        $publicationProcessorMock->shouldReceive('setCoverImage')->byDefault();
        $publicationProcessorMock->shouldReceive('uploadCoverImage')->andReturn('test-cover.jpg')->byDefault();
        $publicationProcessorMock->shouldReceive('updateCoverImage')->byDefault();
        $publicationProcessorMock->shouldReceive('updateSectionId')->byDefault();
        $publicationProcessorMock->shouldReceive('processVersionedPublication')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('createPublicationVersion')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('processMultiLocalePublication')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('updateVorDoi')->byDefault();
        $publicationProcessorMock->shouldReceive('processSupportingAgencies')->byDefault();
        $publicationProcessorMock->shouldReceive('processSupportingAgenciesMultiLocale')->byDefault();

        // Overload SubmissionProcessor
        $subMock = $this->createMockSubmission(['id' => 1]);
        $submissionProcessorMock = Mockery::mock('overload:' . SubmissionProcessor::class);
        $submissionProcessorMock->shouldReceive('process')->andReturn($subMock)->byDefault();
        $submissionProcessorMock->shouldReceive('setCurrentPublicationId')->byDefault();

        // Overload AuthorsProcessor
        $authorsMock = Mockery::mock('overload:' . AuthorsProcessor::class);
        $authorsMock->shouldReceive('process')->byDefault();
        $authorsMock->shouldReceive('processMultiLocale')->byDefault();
        $authorsMock->shouldReceive('addAuthorFromUser')->byDefault();
        $authorsMock->shouldReceive('updateUsernameAuthorLocale')->byDefault();

        // Overload KeywordsProcessor
        $keywordsMock = Mockery::mock('overload:' . KeywordsProcessor::class);
        $keywordsMock->shouldReceive('process')->byDefault();
        $keywordsMock->shouldReceive('processMultiLocale')->byDefault();

        // Overload SubjectsProcessor
        $subjectsMock = Mockery::mock('overload:' . SubjectsProcessor::class);
        $subjectsMock->shouldReceive('process')->byDefault();
        $subjectsMock->shouldReceive('processMultiLocale')->byDefault();

        // Overload FundersProcessor
        $fundersMock = Mockery::mock('overload:' . FundersProcessor::class);
        $fundersMock->shouldReceive('process')->byDefault();
        $fundersMock->shouldReceive('processMultiLocale')->byDefault();

        // Overload CategoriesProcessor
        $categoriesMock = Mockery::mock('overload:' . CategoriesProcessor::class);
        $categoriesMock->shouldReceive('process')->byDefault();
        $categoriesMock->shouldReceive('processForVersion')->byDefault();
        $categoriesMock->shouldReceive('processMultiLocale')->byDefault();

        // Overload SectionsProcessor
        $sectionsMock = Mockery::mock('overload:' . SectionsProcessor::class);
        $sectionsMock->shouldReceive('process')->byDefault();

        // Overload StatisticsProcessor
        $statisticsMock = Mockery::mock('overload:' . StatisticsProcessor::class);
        $statisticsMock->shouldReceive('insertPreprintViews')->byDefault();
        $statisticsMock->shouldReceive('insertGalleyViews')->byDefault();
        $statisticsMock->shouldReceive('resolveFileType')->andReturn(1)->byDefault();

        // Overload file-related processors
        $submissionFileMockObj = new SubmissionFile();
        $submissionFileMockObj->setId(1);
        $submissionFileProcessorMock = Mockery::mock('overload:' . SubmissionFileProcessor::class);
        $submissionFileProcessorMock->shouldReceive('process')->andReturn($submissionFileMockObj)->byDefault();
        $submissionFileProcessorMock->shouldReceive('updateAssocInfo')->byDefault();

        $galleyProcessorMock = Mockery::mock('overload:' . GalleyProcessor::class);
        $galleyProcessorMock->shouldReceive('process')->andReturn(1)->byDefault();

        // Overload FileManager and PublicFileManager for constructor
        $fileManagerMock = Mockery::mock('overload:' . \PKP\file\FileManager::class);
        $fileManagerMock->shouldReceive('parseFileExtension')
            ->andReturnUsing(fn($path) => pathinfo($path, PATHINFO_EXTENSION));

        $publicFileManagerMock = Mockery::mock('overload:' . \APP\file\PublicFileManager::class);
        $publicFileManagerMock->shouldReceive('getContextFilesPath')->andReturn($this->tempDir);

        // Do NOT overload CsvFileHandler — the real static methods are used so that:
        // - createReadableCSVFile opens the real temp CSV file
        // - createCSVFileInvalidRows creates a real invalid_ file (so 'continue 2' is avoided)
        // - processFailedRow actually increments $failedRows by reference (Mockery cannot do this)

        // Overload DryModeReporter
        $dryModeReporterMock = Mockery::mock('overload:' . DryModeReporter::class);
        $dryModeReporterMock->shouldReceive('printFileHeader')->byDefault();
        $dryModeReporterMock->shouldReceive('printTableHeader')->byDefault();
        $dryModeReporterMock->shouldReceive('printFailedRow')->byDefault();
        $dryModeReporterMock->shouldReceive('printFileSummary')->byDefault();
        $dryModeReporterMock->shouldReceive('printGrandTotal')->byDefault();

        // Register file service mock in container
        $fileServiceMock = Mockery::mock(\PKP\services\PKPFileService::class);
        $fileServiceMock->shouldReceive('add')->andReturn(1)->byDefault();
        $fileServiceMock->shouldReceive('delete')->byDefault();
        app()->instance('file', $fileServiceMock);

        // Mock publication repository for direct Repo:: calls in run()
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('get')->andReturn($pubMock)->byDefault();

        // Mock submission repository
        $subRepoMock = $this->mockSubmissionRepository();
        $subRepoMock->shouldReceive('get')->andReturn($subMock)->byDefault();
        $subRepoMock->shouldReceive('delete')->byDefault();

        // Mock stageAssignment repository
        $stageAssignmentMock = Mockery::mock(\PKP\stageAssignment\Repository::class);
        $stageAssignmentMock->shouldReceive('build')->andReturn(new \PKP\stageAssignment\StageAssignment());
        app()->instance(\PKP\stageAssignment\Repository::class, $stageAssignmentMock);

        // Mock DB facade — swap so beginTransaction/rollBack/statement are interceptable
        $realDb = \Illuminate\Support\Facades\DB::getFacadeRoot();
        $dbMock = Mockery::mock($realDb)->makePartial();
        $dbMock->shouldReceive('statement')->withAnyArgs()->byDefault();
        $dbMock->shouldReceive('beginTransaction')->byDefault();
        $dbMock->shouldReceive('rollBack')->byDefault();
        \Illuminate\Support\Facades\DB::swap($dbMock);

        // Build server mock for use in both static pre-population and DAO mock
        $server = $this->createMockServer(['id' => 1, 'path' => 'testserver', 'supportedLocales' => ['en', 'pt_BR']]);

        // Register a ServerDAO mock so getCachedServer() resolves after CachedEntities::reset()
        // clears the static cache between files in multi-file dry-mode tests.
        $serverDaoMock = Mockery::mock(\APP\server\ServerDAO::class);
        $serverDaoMock->shouldReceive('getByPath')->andReturn($server)->byDefault();
        $serverDaoMock->shouldReceive('getById')->andReturn($server)->byDefault();
        \PKP\db\DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        // Register a GenreDAO mock so getCachedGenreId() resolves after reset()
        $genreMock = Mockery::mock(\PKP\submission\Genre::class)->makePartial();
        $genreMock->shouldReceive('getId')->andReturn(1)->byDefault();
        $genreDaoMock = Mockery::mock(\PKP\submission\GenreDAO::class);
        $genreDaoMock->shouldReceive('getByKey')->andReturn($genreMock)->byDefault();
        \PKP\db\DAORegistry::registerDAO('GenreDAO', $genreDaoMock);

        // Mock UserGroup repository so getCachedAuthorUserGroupId() resolves after reset()
        $userGroupRepoMock = $this->mockUserGroupRepository();
        $userGroupRepoMock->shouldReceive('getByRoleIds')
            ->andReturnUsing(function () {
                $ug = new \PKP\userGroup\UserGroup();
                $ug->id = 1;
                return new \Illuminate\Support\LazyCollection([$ug]);
            })
            ->byDefault();

        // Pre-populate static properties (used for assertion checks in single-file tests)
        CachedEntities::$servers['testserver'] = $server;
        CachedEntities::$genreIds['SUBMISSION'] = 1;
        CachedEntities::$userGroupIds['testserver'] = 1;

        return [
            'validationMock' => $validationMock,
            'publicationProcessorMock' => $publicationProcessorMock,
            'submissionProcessorMock' => $submissionProcessorMock,
            'publication' => $pubMock,
            'submission' => $subMock,
            'server' => $server,
            'dbMock' => $dbMock,
            'dryModeReporterMock' => $dryModeReporterMock,
            'subRepoMock' => $subRepoMock,
            'fileServiceMock' => $fileServiceMock,
        ];
    }

    // ==================== Transaction Lifecycle ====================

    /**
     * Dry-mode begins a transaction per file and rolls back — all-valid rows return exit code 0.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeAllValidRowsExitsZeroWithTransactionRollback(): void
    {
        $mocks = $this->setupDryModeMocks();
        $mocks['dbMock']->shouldReceive('beginTransaction')->once();
        $mocks['dbMock']->shouldReceive('rollBack')->once();

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [CsvTestDataBuilder::minimalPreprintRow()]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$genreIds);
        $this->assertEmpty(CachedEntities::$userGroupIds);
    }

    /**
     * Dry-mode with mixed rows (some fail): rolls back, reports failures, returns exit code 1.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeMixedRowsExitsOneAndReportsFailures(): void
    {
        $mocks = $this->setupDryModeMocks();
        $mocks['dbMock']->shouldReceive('beginTransaction')->once();
        $mocks['dbMock']->shouldReceive('rollBack')->once();

        // First call passes, second call throws
        $callCount = 0;
        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 2) {
                    throw new RowValidationException('Missing required field');
                }
            });

        $row1 = CsvTestDataBuilder::minimalPreprintRow();
        $row2 = CsvTestDataBuilder::minimalPreprintRow();

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row1, $row2]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(1, $exitCode);
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFailedRow')->once();
    }

    // ==================== CachedEntities and State Reset ====================

    /**
     * Dry-mode with multiple files calls beginTransaction/rollBack per file and clears all caches.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeMultiFileResetsCachedEntitiesAndProcessedPreprints(): void
    {
        $mocks = $this->setupDryModeMocks();
        $mocks['dbMock']->shouldReceive('beginTransaction')->twice();
        $mocks['dbMock']->shouldReceive('rollBack')->twice();

        // Pre-populate extra cache entry to verify reset()
        CachedEntities::$sections[1] = $this->createMockSection();

        $row = CsvTestDataBuilder::minimalPreprintRow();

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints_a.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row]
        );
        $this->createTestCsvFile(
            $this->tempDir,
            'preprints_b.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        // After dry-mode run(), static properties should be empty due to CachedEntities::reset()
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$sections);
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFileHeader')->twice();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printGrandTotal')->once();
    }

    /**
     * Dry-mode resets $processedPreprints between files — same versionIdentifier in
     * separate files is treated as a new submission (not multi-locale), so
     * SubmissionProcessor::process is called once per file.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeMultiFileSecondFileDoesNotSeeFirstFileIdentifiers(): void
    {
        $mocks = $this->setupDryModeMocks();

        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('test-id-1')
            ->withVersion('1')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,Test University')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();

        $this->createTestCsvFile(
            $this->tempDir,
            'file_a.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row]
        );
        $this->createTestCsvFile(
            $this->tempDir,
            'file_b.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row]
        );

        // If processedPreprints is NOT reset between files, the second file's row would
        // route to multi-locale path instead of calling SubmissionProcessor::process again.
        // Asserting process is called twice proves the reset happened correctly.
        $mocks['submissionProcessorMock']->shouldReceive('process')
            ->twice()
            ->andReturn($mocks['submission']);

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        Mockery::close();
        $this->assertTrue(true);
    }

    // ==================== Reporter Output ====================

    /**
     * DryModeReporter methods are called in the correct sequence for a valid file.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeReporterCalledWithCorrectSequence(): void
    {
        $mocks = $this->setupDryModeMocks();

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [CsvTestDataBuilder::minimalPreprintRow()]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        $mocks['dryModeReporterMock']->shouldHaveReceived('printFileHeader')->once();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFileSummary')->once();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printGrandTotal')->once();
        // No failures → printTableHeader must NOT be called
        $mocks['dryModeReporterMock']->shouldNotHaveReceived('printTableHeader');
        $this->assertTrue(true);
    }

    /**
     * When failures exist, DryModeReporter prints the table header and each failed row.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeReporterShowsTableHeaderOnlyWhenFailuresExist(): void
    {
        $mocks = $this->setupDryModeMocks();

        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->once()
            ->andThrow(new RowValidationException('Row is missing fields'));

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [CsvTestDataBuilder::minimalPreprintRow()]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        $mocks['dryModeReporterMock']->shouldHaveReceived('printTableHeader')->once();
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFailedRow')->once();
        $this->assertTrue(true);
    }

    // ==================== Filesystem Guard ====================

    /**
     * Cover image upload is skipped in dry-mode even when coverImageFilename is set.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeSkipsCoverImageUpload(): void
    {
        $mocks = $this->setupDryModeMocks();

        // Explicitly assert that uploadCoverImage is never called in dry-mode
        $mocks['publicationProcessorMock']->shouldNotReceive('uploadCoverImage');

        // Create a dummy cover image file so path-existence checks would pass in normal mode
        $this->createTestFile($this->tempDir, 'cover.jpg', 'fake image content');

        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Cover Image Test')
            ->withAuthors('John,Doe,john@example.com,,Test University')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCoverImage('cover.jpg')
            ->buildArray();

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        // Mockery expectation verification happens in tearDown via Mockery::close()
        $this->assertTrue(true);
    }

    /**
     * Submission delete is never called in dry-mode — no FileNotSavedException cleanup path.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeSkipsSubmissionDeleteOnFileError(): void
    {
        $mocks = $this->setupDryModeMocks();

        // In dry-mode, galley file processing is skipped entirely, so FileNotSavedException
        // path is never reached — submission delete must never be called
        $mocks['subRepoMock']->shouldNotReceive('delete');

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [CsvTestDataBuilder::minimalPreprintRow()]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        $this->assertTrue(true);
    }

    // ==================== Edge Cases ====================

    /**
     * An empty source directory exits with code 0 and still prints grand total.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeEmptyDirectoryExitsZero(): void
    {
        $mocks = $this->setupDryModeMocks();

        // No CSV files created in tempDir
        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        $mocks['dryModeReporterMock']->shouldHaveReceived('printGrandTotal')->once();
    }

    /**
     * A file where every row fails validation returns exit code 1 and reports each failure.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeFileWithOnlyInvalidRowsExitsOne(): void
    {
        $mocks = $this->setupDryModeMocks();

        // All calls throw RowValidationException
        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->andThrow(new RowValidationException('Row validation failed'));

        $row1 = CsvTestDataBuilder::minimalPreprintRow();
        $row2 = CsvTestDataBuilder::minimalPreprintRow();

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [$row1, $row2]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $exitCode = $command->run();
        ob_get_clean();

        $this->assertSame(1, $exitCode);
        $mocks['dryModeReporterMock']->shouldHaveReceived('printFailedRow')->twice();
    }

    /**
     * Dry-mode with invalid rows produces zero invalid_ files on disk.
     * The DryModeReporter console output is sufficient — no filesystem side effects.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDryModeDoesNotCreateInvalidCsvFiles(): void
    {
        $mocks = $this->setupDryModeMocks();

        // All calls throw RowValidationException
        $mocks['validationMock']->shouldReceive('validateRowContainAllFields')
            ->andThrow(new RowValidationException('Row validation failed'));

        $this->createTestCsvFile(
            $this->tempDir,
            'preprints.csv',
            CsvTestDataBuilder::getPreprintHeaders(),
            [
                CsvTestDataBuilder::minimalPreprintRow(),
                CsvTestDataBuilder::minimalPreprintRow(),
            ]
        );

        $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

        ob_start();
        $command->run();
        ob_get_clean();

        // No invalid_ files should exist in the source directory
        $invalidFiles = glob($this->tempDir . '/invalid_*');
        $this->assertEmpty($invalidFiles, 'Dry-mode must not create invalid_ CSV files on disk');
    }
}
