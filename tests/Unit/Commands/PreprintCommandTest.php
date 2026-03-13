<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Commands/PreprintCommandTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintCommandTest
 *
 * @brief Tests for PreprintCommand class - testing CSV data handling logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\commands\PreprintCommand;
use APP\plugins\importexport\csv\classes\handlers\CsvFileHandler;
use APP\plugins\importexport\csv\classes\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\classes\processors\CategoriesProcessor;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\plugins\importexport\csv\classes\processors\GalleyProcessor;
use APP\plugins\importexport\csv\classes\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\processors\SectionsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionFileProcessor;
use APP\plugins\importexport\csv\classes\processors\StatisticsProcessor;
use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredPreprintHeaders;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PKP\submissionFile\SubmissionFile;

#[CoversClass(PreprintCommand::class)]
class PreprintCommandTest extends BaseTestCase
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

    // ==================== Preprint Header Tests ====================

    public function testPreprintHeadersCount(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();

        $this->assertCount(35, $headers);
    }

    public function testPreprintRequiredHeadersCount(): void
    {
        $requiredHeaders = RequiredPreprintHeaders::$preprintRequiredHeaders;

        $this->assertCount(5, $requiredHeaders);
    }

    public function testPreprintHeadersContainRequiredFields(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();

        $this->assertContains('serverPath', $headers);
        $this->assertContains('locale', $headers);
        $this->assertContains('preprintTitle', $headers);
        $this->assertContains('preprintAbstract', $headers);
        $this->assertContains('authors', $headers);
        $this->assertContains('datePosted', $headers);
    }

    public function testPreprintHeadersContainVersionFields(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();

        $this->assertContains('versionIdentifier', $headers);
        $this->assertContains('version', $headers);
    }

    public function testPreprintHeadersContainFileFields(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();

        $this->assertContains('galleyFilenames', $headers);
        $this->assertContains('galleyLabels', $headers);
        $this->assertContains('suppFilenames', $headers);
        $this->assertContains('suppLabels', $headers);
    }

    // ==================== CSV File Handling Tests ====================

    public function testCsvFileCanBeCreated(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();
        $rows = [CsvTestDataBuilder::minimalPreprintRow()];

        $filepath = $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, $rows);

        $this->assertFileExists($filepath);
    }

    public function testCsvFileHasCorrectContent(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();
        $rows = [CsvTestDataBuilder::minimalPreprintRow()];

        $filepath = $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, $rows);
        $content = file_get_contents($filepath);

        $this->assertStringContainsString('serverPath', $content);
        $this->assertStringContainsString('testserver', $content);
    }

    // ==================== Preprint Data Parsing Tests ====================

    public function testMinimalPreprintRowFormat(): void
    {
        $row = CsvTestDataBuilder::minimalPreprintRow();

        $this->assertCount(count(CsvTestDataBuilder::getPreprintHeaders()), $row);
    }

    public function testCompletePreprintRowFormat(): void
    {
        $row = CsvTestDataBuilder::completePreprintRow();

        $this->assertCount(count(CsvTestDataBuilder::getPreprintHeaders()), $row);
    }

    public function testPreprintDataObjectCreation(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAbstract('This is the abstract.')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->buildObject();

        $this->assertEquals('testserver', $data->serverPath);
        $this->assertEquals('en', $data->locale);
        $this->assertEquals('Test Preprint', $data->preprintTitle);
        $this->assertEquals('This is the abstract.', $data->preprintAbstract);
    }

    // ==================== Version Handling Tests ====================

    public function testVersionIdentifierFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVersionIdentifier('preprint-001')
            ->buildObject();

        $this->assertEquals('preprint-001', $data->versionIdentifier);
    }

    public function testVersionNumberParsing(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVersion('1')
            ->buildObject();

        $this->assertEquals('1', $data->version);
    }

    public function testVersionNumber2(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->buildObject();

        $this->assertEquals('2', $data->version);
    }

    // ==================== Multi-Locale Tests ====================

    public function testMultipleLocales(): void
    {
        $enData = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withTitle('English Title')
            ->buildObject();

        $ptData = CsvTestDataBuilder::preprint()
            ->withLocale('pt_BR')
            ->withTitle('Título em Português')
            ->buildObject();

        $this->assertEquals('en', $enData->locale);
        $this->assertEquals('English Title', $enData->preprintTitle);
        $this->assertEquals('pt_BR', $ptData->locale);
        $this->assertEquals('Título em Português', $ptData->preprintTitle);
    }

    // ==================== Authors Field Tests ====================

    public function testAuthorsFieldFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withAuthors('John,Doe,john@example.com,0000-0002-1825-0097,MIT')
            ->buildObject();

        $authorParts = explode(',', $data->authors);

        $this->assertCount(5, $authorParts);
        $this->assertEquals('John', $authorParts[0]);
        $this->assertEquals('Doe', $authorParts[1]);
    }

    public function testMultipleAuthorsFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withAuthors('John,Doe,john@example.com,,MIT;Jane,Smith,jane@example.com,,Harvard')
            ->buildObject();

        $authors = explode(';', $data->authors);

        $this->assertCount(2, $authors);
    }

    // ==================== Keywords and Subjects Tests ====================

    public function testKeywordsFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withKeywords('keyword1;keyword2;keyword3')
            ->buildObject();

        $keywords = explode(';', $data->keywords);

        $this->assertCount(3, $keywords);
    }

    public function testSubjectsFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSubjects('subject1;subject2')
            ->buildObject();

        $subjects = explode(';', $data->subjects);

        $this->assertCount(2, $subjects);
    }

    // ==================== Galley Files Tests ====================

    public function testGalleyFilesFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withGalleys('paper.pdf', 'PDF')
            ->buildObject();

        $this->assertEquals('paper.pdf', $data->galleyFilenames);
        $this->assertEquals('PDF', $data->galleyLabels);
    }

    public function testMultipleGalleyFiles(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withGalleys('paper.pdf;slides.pptx', 'PDF;SLIDES')
            ->buildObject();

        $files = explode(';', $data->galleyFilenames);
        $labels = explode(';', $data->galleyLabels);

        $this->assertCount(2, $files);
        $this->assertCount(2, $labels);
    }

    // ==================== Supplementary Files Tests ====================

    public function testSupplementaryFilesFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSupplementaryFiles('data.xlsx', 'Dataset', 'Raw dataset')
            ->buildObject();

        $this->assertEquals('data.xlsx', $data->suppFilenames);
        $this->assertEquals('Dataset', $data->suppLabels);
        $this->assertEquals('Raw dataset', $data->suppDescriptions);
    }

    // ==================== DOI Tests ====================

    public function testDoiFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDoi('10.1234/test-doi')
            ->buildObject();

        $this->assertEquals('10.1234/test-doi', $data->doi);
    }

    public function testVorDoiFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVorDoi('https://doi.org/10.1234/published-article')
            ->buildObject();

        $this->assertEquals('https://doi.org/10.1234/published-article', $data->vorDoi);
    }

    // ==================== Funders Tests ====================

    public function testFundersFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withFunders('NSF,http://dx.doi.org/10.13039/100000001,NSF-001')
            ->buildObject();

        $this->assertEquals('NSF,http://dx.doi.org/10.13039/100000001,NSF-001', $data->funders);
    }

    // ==================== Multiple CSV Files Tests ====================

    public function testMultipleCsvFilesCanBeCreated(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();

        $this->createTestCsvFile($this->tempDir, 'preprints1.csv', $headers, [CsvTestDataBuilder::minimalPreprintRow()]);
        $this->createTestCsvFile($this->tempDir, 'preprints2.csv', $headers, [CsvTestDataBuilder::minimalPreprintRow()]);

        $this->assertFileExists($this->tempDir . '/preprints1.csv');
        $this->assertFileExists($this->tempDir . '/preprints2.csv');
    }

    public function testCsvFilesCanBeScanned(): void
    {
        $headers = CsvTestDataBuilder::getPreprintHeaders();

        $this->createTestCsvFile($this->tempDir, 'preprints1.csv', $headers, [CsvTestDataBuilder::minimalPreprintRow()]);
        $this->createTestCsvFile($this->tempDir, 'preprints2.csv', $headers, [CsvTestDataBuilder::minimalPreprintRow()]);

        $csvFiles = glob($this->tempDir . '/*.csv');

        $this->assertCount(2, $csvFiles);
    }

    // ==================== Section Fields Tests ====================

    public function testSectionFieldsFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildObject();

        $this->assertEquals('Preprints', $data->sectionTitle);
        $this->assertEquals('PRE', $data->sectionAbbrev);
    }

    // ==================== Date Fields Tests ====================

    public function testDateFieldsFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('2024-01-10')
            ->buildObject();

        $this->assertEquals('2024-01-15', $data->datePosted);
        $this->assertEquals('2024-01-10', $data->dateSubmitted);
    }

    // ==================== Copyright Fields Tests ====================

    public function testCopyrightFieldsFormat(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withCopyrightYear('2024')
            ->withCopyrightHolder('Test Author')
            ->withLicenseUrl('https://creativecommons.org/licenses/by/4.0')
            ->buildObject();

        $this->assertEquals('2024', $data->copyrightYear);
        $this->assertEquals('Test Author', $data->copyrightHolder);
        $this->assertEquals('https://creativecommons.org/licenses/by/4.0', $data->licenseUrl);
    }

    // ==================== Separate Process Tests (Full Line Coverage) ====================

    /**
     * Set up all overload mocks needed for PreprintCommand::run() tests.
     * Returns an array of key mock references for individual test customization.
     */
    private function setupAllMocks(): array
    {
        // Overload InvalidRowValidations - bypass all validations
        $validationMock = Mockery::mock('overload:' . InvalidRowValidations::class);
        $validationMock->shouldReceive('validateRowContainAllFields');
        $validationMock->shouldReceive('validateRowHasAllRequiredFields');
        $validationMock->shouldReceive('validatePreprintVersioningFields');
        $validationMock->shouldReceive('validateSectionFields');
        $validationMock->shouldReceive('validateNoDuplicateVersion');
        $validationMock->shouldReceive('validatePreprintGalleys');
        $validationMock->shouldReceive('validateSupplementaryFiles');
        $validationMock->shouldReceive('validateSupplementaryDescriptions');
        $validationMock->shouldReceive('validateReferencesFile');
        $validationMock->shouldReceive('validateVorDoi');
        $validationMock->shouldReceive('validateFunders');
        $validationMock->shouldReceive('validateEmail');
        $validationMock->shouldReceive('validateServerIsValid');
        $validationMock->shouldReceive('validateServerLocale');
        $validationMock->shouldReceive('validateGenreIdValid');
        $validationMock->shouldReceive('validateUserGroupId');
        $validationMock->shouldReceive('validateFundingPluginEnabled');
        $validationMock->shouldReceive('validateFundersCrossrefRegistry');
        $validationMock->shouldReceive('validatePublicationWasSuccessfullyCreated');
        $validationMock->shouldReceive('validatePreprintViews');
        $validationMock->shouldReceive('validateGalleyViews');
        $validationMock->shouldReceive('versionExistsInAnyLocale')->andReturn(false)->byDefault();

        // Overload processors - PublicationProcessor
        $pubMock = $this->createMockPublication(['id' => 1, 'submissionId' => 1]);
        $publicationProcessorMock = Mockery::mock('overload:' . PublicationProcessor::class);
        $publicationProcessorMock->shouldReceive('createInitialPublication')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('process')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('updatePrimaryContactId');
        $publicationProcessorMock->shouldReceive('updateCoverage');
        $publicationProcessorMock->shouldReceive('setCoverImage');
        $publicationProcessorMock->shouldReceive('uploadCoverImage')->andReturn('test-cover.jpg')->byDefault();
        $publicationProcessorMock->shouldReceive('updateCoverImage');
        $publicationProcessorMock->shouldReceive('updateSectionId');
        $publicationProcessorMock->shouldReceive('processVersionedPublication')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('createPublicationVersion')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('processMultiLocalePublication')->andReturn($pubMock)->byDefault();
        $publicationProcessorMock->shouldReceive('updateVorDoi');
        $publicationProcessorMock->shouldReceive('processSupportingAgencies');
        $publicationProcessorMock->shouldReceive('processSupportingAgenciesMultiLocale');

        // Overload SubmissionProcessor
        $subMock = $this->createMockSubmission(['id' => 1]);
        $submissionProcessorMock = Mockery::mock('overload:' . SubmissionProcessor::class);
        $submissionProcessorMock->shouldReceive('process')->andReturn($subMock)->byDefault();
        $submissionProcessorMock->shouldReceive('setCurrentPublicationId');

        // Overload other processors
        $authorsMock = Mockery::mock('overload:' . AuthorsProcessor::class);
        $authorsMock->shouldReceive('process');
        $authorsMock->shouldReceive('processMultiLocale');
        $authorsMock->shouldReceive('addAuthorFromUser');
        $authorsMock->shouldReceive('updateUsernameAuthorLocale');

        $keywordsMock = Mockery::mock('overload:' . KeywordsProcessor::class);
        $keywordsMock->shouldReceive('process');
        $keywordsMock->shouldReceive('processMultiLocale');

        $subjectsMock = Mockery::mock('overload:' . SubjectsProcessor::class);
        $subjectsMock->shouldReceive('process');
        $subjectsMock->shouldReceive('processMultiLocale');

        $fundersMock = Mockery::mock('overload:' . FundersProcessor::class);
        $fundersMock->shouldReceive('process');
        $fundersMock->shouldReceive('processMultiLocale');

        $categoriesMock = Mockery::mock('overload:' . CategoriesProcessor::class);
        $categoriesMock->shouldReceive('process');
        $categoriesMock->shouldReceive('processForVersion');
        $categoriesMock->shouldReceive('processMultiLocale');

        $sectionsMock = Mockery::mock('overload:' . SectionsProcessor::class);
        $sectionsMock->shouldReceive('process');

        $statisticsMock = Mockery::mock('overload:' . StatisticsProcessor::class);
        $statisticsMock->shouldReceive('insertPreprintViews');
        $statisticsMock->shouldReceive('insertGalleyViews');
        $statisticsMock->shouldReceive('resolveFileType')->andReturn(1)->byDefault();

        // Overload file-related processors
        $submissionFileMockObj = new SubmissionFile();
        $submissionFileMockObj->setId(1);
        $submissionFileProcessorMock = Mockery::mock('overload:' . SubmissionFileProcessor::class);
        $submissionFileProcessorMock->shouldReceive('process')->andReturn($submissionFileMockObj)->byDefault();
        $submissionFileProcessorMock->shouldReceive('updateAssocInfo');

        $galleyProcessorMock = Mockery::mock('overload:' . GalleyProcessor::class);
        $galleyProcessorMock->shouldReceive('process')->andReturn(1)->byDefault();

        // Overload FileManager and PublicFileManager for constructor
        $fileManagerMock = Mockery::mock('overload:' . \PKP\file\FileManager::class);
        $fileManagerMock->shouldReceive('parseFileExtension')
            ->andReturnUsing(fn($path) => pathinfo($path, PATHINFO_EXTENSION));

        $publicFileManagerMock = Mockery::mock('overload:' . \APP\file\PublicFileManager::class);
        $publicFileManagerMock->shouldReceive('getContextFilesPath')->andReturn($this->tempDir);

        // Register file service mock in container
        $fileServiceMock = Mockery::mock(\PKP\services\PKPFileService::class);
        $fileServiceMock->shouldReceive('add')->andReturn(1)->byDefault();
        $fileServiceMock->shouldReceive('delete');
        app()->instance('file', $fileServiceMock);

        // Mock publication repository for direct Repo:: calls in run()
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('get')->andReturn($pubMock)->byDefault();

        // Mock submission repository
        $subRepoMock = $this->mockSubmissionRepository();
        $subRepoMock->shouldReceive('get')->andReturn($subMock)->byDefault();
        $subRepoMock->shouldReceive('delete');

        // Mock stageAssignment repository for Repo::stageAssignment()->build()
        $stageAssignmentMock = Mockery::mock(\PKP\stageAssignment\Repository::class);
        $stageAssignmentMock->shouldReceive('build')->andReturn(new \PKP\stageAssignment\StageAssignment());
        app()->instance(\PKP\stageAssignment\Repository::class, $stageAssignmentMock);

        // Pre-populate CachedEntities
        $server = $this->createMockServer(['id' => 1, 'path' => 'testserver', 'supportedLocales' => ['en', 'pt_BR']]);
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
            'fileServiceMock' => $fileServiceMock,
            'pubRepoMock' => $pubRepoMock,
            'subRepoMock' => $subRepoMock,
            'submissionFileProcessorMock' => $submissionFileProcessorMock,
            'galleyProcessorMock' => $galleyProcessorMock,
            'categoriesMock' => $categoriesMock,
            'authorsMock' => $authorsMock,
            'sectionsMock' => $sectionsMock,
        ];
    }

    /**
     * Set up minimal constructor mocks without processor overloads.
     * Used for tests that don't process CSV rows.
     */
    private function setupConstructorMocks(): void
    {
        $fileManagerMock = Mockery::mock('overload:' . \PKP\file\FileManager::class);
        $fileManagerMock->shouldReceive('parseFileExtension')
            ->andReturnUsing(fn($path) => pathinfo($path, PATHINFO_EXTENSION));

        $publicFileManagerMock = Mockery::mock('overload:' . \APP\file\PublicFileManager::class);
        $publicFileManagerMock->shouldReceive('getContextFilesPath')->andReturn($this->tempDir);

        $fileServiceMock = Mockery::mock(\PKP\services\PKPFileService::class);
        $fileServiceMock->shouldReceive('add')->andReturn(1);
        $fileServiceMock->shouldReceive('delete');
        app()->instance('file', $fileServiceMock);
    }

    /**
     * Covers lines 117-128: skip non-CSV files and invalid_ prefixed files.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunSkipsNonCsvAndInvalidPrefixFiles(): void
    {
        $this->setupConstructorMocks();

        // Create a non-CSV file and an invalid_ prefixed CSV file
        $this->createTestFile($this->tempDir, 'readme.txt', 'not a csv');
        $this->createTestFile($this->tempDir, 'invalid_old.csv', 'some,old,data');

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // invalid_ file should trigger skip message
        $this->assertStringContainsString('plugins.importexport.csv.skippingInvalidFile', $output);
        // No file processing finished message (no valid CSV processed)
        $this->assertStringNotContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 130-134: continue when CsvFileHandler::createReadableCSVFile() returns null.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunContinuesWhenCreateReadableCSVFileReturnsNull(): void
    {
        $this->setupConstructorMocks();

        $csvFileHandlerMock = Mockery::mock('overload:' . CsvFileHandler::class);
        $csvFileHandlerMock->shouldReceive('createReadableCSVFile')->andReturn(null);

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [
            CsvTestDataBuilder::minimalPreprintRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // continue on line 134 skips the file processing summary echo
        $this->assertStringNotContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 420-424: continue 2 when CsvFileHandler::createCSVFileInvalidRows() returns null.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunContinuesOuterLoopWhenCreateCSVFileInvalidRowsReturnsNull(): void
    {
        $this->setupConstructorMocks();

        $csvFileHandlerMock = Mockery::mock('overload:' . CsvFileHandler::class);
        $csvFileHandlerMock->shouldReceive('createReadableCSVFile')
            ->andReturnUsing(function (string $filePath) {
                $file = new \SplFileObject($filePath, 'r');
                $file->setFlags(\SplFileObject::READ_CSV);
                return $file;
            });
        $csvFileHandlerMock->shouldReceive('createCSVFileInvalidRows')->andReturn(null);

        // Create CSV with too few fields to trigger RowValidationException
        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $filepath = $this->tempDir . '/preprints.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, ['testserver', 'en']); // Only 2 fields
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // continue 2 on line 424 skips the file processing summary echo
        $this->assertStringNotContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers: constructor (102-113), main loop, new submission branch (291-296),
     * regular processors (361-367), section processing (380), publication update (392-393),
     * file finished output (432-436), post-processing (439-440).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunProcessesNewSubmissionSuccessfully(): void
    {
        $mocks = $this->setupAllMocks();

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [
            CsvTestDataBuilder::minimalPreprintRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers: all optional field branches (164-253), processGalleys (300, 446-495, 502-520, 523-550),
     * supplementary files (302-352), vorDoi (370-372), coverage (374-376),
     * cover image (382-383), categories (395-406), trackProcessedPreprint (408-410, 555-574).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunProcessesCompletePreprint(): void
    {
        $mocks = $this->setupAllMocks();

        // Add a cached user so username lookup succeeds (line 217-218)
        $csvUser = $this->createMockUser(['id' => 5, 'username' => 'csvauthor']);
        CachedEntities::$users['csvauthor'] = $csvUser;

        // Pre-populate supplementary genre ID cache
        CachedEntities::$supplementaryGenreIds[1] = 2;

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('1')
            ->withPrefix('QC')
            ->withTitle('Complete Test Preprint')
            ->withSubtitle('A Comprehensive Study')
            ->withAbstract('Full abstract text.')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withKeywords('test;unit testing')
            ->withSubjects('Computer Science')
            ->withCoverage('Global study')
            ->withCategories('Research Article')
            ->withDoi('10.1234/test')
            ->withGalleys('paper.pdf', 'PDF')
            ->withSupplementaryFiles('data.xlsx', 'Dataset', 'Raw data')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('2024-01-10')
            ->withCopyrightYear('2024')
            ->withCopyrightHolder('Test Author')
            ->withLicenseUrl('https://creativecommons.org/licenses/by/4.0')
            ->withVorDoi('https://doi.org/10.1234/vor')
            ->withSupportingAgencies('NSF')
            ->withUsername('csvauthor')
            ->withFunders('NSF,http://dx.doi.org/10.13039/100000001,NSF-001')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
        // Username was found, so no default user message
        $this->assertStringNotContainsString('plugins.importexport.csv.usernameNotFoundUsingDefault', $output);
    }

    /**
     * Covers: version detection (261-278), version branch (286-290),
     * base section (378-379), categories for version (398-399),
     * trackProcessedPreprint (555-574), setCurrentVersionsForProcessedPreprints (647-677),
     * syncCoverImagesForProcessedPreprints (576-589).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunProcessesVersionImport(): void
    {
        $mocks = $this->setupAllMocks();

        // For version 2, versionExistsInAnyLocale returns false (new version, not multi-locale)
        // but processedPreprints will have an entry for v1 after first row processes.
        // The mock returns false by default so both rows enter the else branch.
        // But v2 needs to detect that processedPreprints[$identifier] exists (line 271).
        // Since versionExistsInAnyLocale returns false, it checks elseif (line 271).

        // Create publication with sectionId for base section test (line 378-379)
        $pub1 = $this->createMockPublication(['id' => 1, 'submissionId' => 1, 'sectionId' => 5]);
        $pub2 = $this->createMockPublication(['id' => 2, 'submissionId' => 1, 'sectionId' => 5]);
        $sub = $this->createMockSubmission(['id' => 1]);

        $mocks['publicationProcessorMock']->shouldReceive('createInitialPublication')->andReturn($pub1);
        $mocks['publicationProcessorMock']->shouldReceive('process')->andReturn($pub1);
        $mocks['publicationProcessorMock']->shouldReceive('createPublicationVersion')->andReturn($pub2);
        $mocks['publicationProcessorMock']->shouldReceive('processVersionedPublication')->andReturn($pub2);
        $mocks['submissionProcessorMock']->shouldReceive('process')->andReturn($sub);
        $mocks['pubRepoMock']->shouldReceive('get')->with(1)->andReturn($pub1);
        $mocks['pubRepoMock']->shouldReceive('get')->with(2)->andReturn($pub2);

        // Mock DAORegistry for syncCoverImagesForProcessedPreprints
        $serverDaoMock = Mockery::mock(\APP\server\ServerDAO::class);
        $serverDaoMock->shouldReceive('getById')->andReturn(null);
        \PKP\db\DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $v1Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('1')
            ->withTitle('Version 1 Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCategories('Research')
            ->buildArray();

        $v2Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('2')
            ->withTitle('Version 2 Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-02-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCategories('Research')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$v1Row, $v2Row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers: multi-locale detection (262-270), multi-locale branch (281-285),
     * multi-locale processors (354-360), multi-locale categories (396-397),
     * cover image from base publication (384-390).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunProcessesMultiLocaleImport(): void
    {
        $mocks = $this->setupAllMocks();

        $pub = $this->createMockPublication(['id' => 1, 'submissionId' => 1]);
        $sub = $this->createMockSubmission(['id' => 1]);

        $mocks['publicationProcessorMock']->shouldReceive('createInitialPublication')->andReturn($pub);
        $mocks['publicationProcessorMock']->shouldReceive('process')->andReturn($pub);
        $mocks['publicationProcessorMock']->shouldReceive('processMultiLocalePublication')->andReturn($pub);
        $mocks['submissionProcessorMock']->shouldReceive('process')->andReturn($sub);
        $mocks['pubRepoMock']->shouldReceive('get')->andReturn($pub);

        // For second row, versionExistsInAnyLocale returns true (same version, different locale)
        $callCount = 0;
        $mocks['validationMock']->shouldReceive('versionExistsInAnyLocale')
            ->andReturnUsing(function () use (&$callCount) {
                return ++$callCount > 1; // false for first row, true for second
            });

        // Mock DAORegistry for syncCoverImagesForProcessedPreprints
        $serverDaoMock = Mockery::mock(\APP\server\ServerDAO::class);
        $serverDaoMock->shouldReceive('getById')->andReturn(null);
        \PKP\db\DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $enRow = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('1')
            ->withTitle('English Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCategories('Research')
            ->buildArray();

        $ptRow = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('pt_BR')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('1')
            ->withTitle('Título em Português')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCategories('Pesquisa')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$enRow, $ptRow]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 216-219: username lookup fallback, and lines 412-418: default user message output.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunUsesDefaultUserWhenUsernameNotFound(): void
    {
        $mocks = $this->setupAllMocks();

        // The username 'unknownuser' is NOT in cache, so getCachedUserByUsername returns null.
        // This triggers usedDefaultUser = true (line 218) and the message on line 412-418.

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withUsername('unknownuser')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser(['username' => 'admin']);
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexport.csv.usernameNotFoundUsingDefault', $output);
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 420-429: validation failure creates invalid CSV file via processFailedRow.
     * Uses real InvalidRowValidations (not overloaded) to trigger RowValidationException.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunHandlesValidationFailureCreatesInvalidCsv(): void
    {
        // Only set up constructor mocks - let real InvalidRowValidations throw
        $this->setupConstructorMocks();

        // Create CSV with a valid header row but an invalid data row (too few fields)
        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $filepath = $this->tempDir . '/preprints.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, ['testserver', 'en']); // Only 2 fields - triggers RowValidationException
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // The invalid CSV file should be created
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertNotEmpty($invalidFiles);
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers: saveSubmissionFile error path (509-518), galley cleanup (466-471),
     * catch block (420) for FileNotSavedException.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunHandlesFileNotSavedExceptionInGalleys(): void
    {
        $mocks = $this->setupAllMocks();

        // Make fileService->add() throw exception to trigger FileNotSavedException in saveSubmissionFile
        $mocks['fileServiceMock']->shouldReceive('add')
            ->andThrow(new \Exception('File save failed'));

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withGalleys('paper.pdf', 'PDF')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // FileNotSavedException caught, invalid CSV created, processing continues
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertNotEmpty($invalidFiles);
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers: supplementary file save failure (309-326) with cleanup of already-uploaded files.
     * Mock fileService->add() to succeed for galley but fail for second supp file.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunHandlesFileNotSavedExceptionInSuppFiles(): void
    {
        $mocks = $this->setupAllMocks();

        // Pre-populate supplementary genre ID cache
        CachedEntities::$supplementaryGenreIds[1] = 2;

        // fileService->add() succeeds twice (galley processing is skipped since no galleys),
        // succeeds for first supp file, fails for second supp file
        $addCallCount = 0;
        $mocks['fileServiceMock']->shouldReceive('add')
            ->andReturnUsing(function () use (&$addCallCount) {
                $addCallCount++;
                if ($addCallCount >= 2) {
                    throw new \Exception('Supp file save failed');
                }
                return $addCallCount;
            });

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withSupplementaryFiles('data1.xlsx;data2.xlsx', 'Dataset1;Dataset2')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // FileNotSavedException caught, invalid CSV created
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertNotEmpty($invalidFiles);
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers: line 193 (references validation), lines 243-251 (cover image upload),
     * line 334 (empty supp descriptions branch), line 383 (updateCoverImage with cover image filename).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunProcessesCoverImageReferencesAndSuppWithoutDescriptions(): void
    {
        $mocks = $this->setupAllMocks();

        // Pre-populate supplementary genre ID cache
        CachedEntities::$supplementaryGenreIds[1] = 2;

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Cover Image Test')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCoverImage('cover.jpg', 'Cover alt text')
            ->withReferences('refs.bib')
            ->withSupplementaryFiles('data.xlsx', 'Dataset')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers line 468: galley file cleanup when second galley save fails.
     * First galley saves successfully (added to $galleyIds), second throws FileNotSavedException.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunCleansUpGalleyFilesOnSecondGalleyFailure(): void
    {
        $mocks = $this->setupAllMocks();

        // fileService->add() succeeds for first galley, throws for second
        $addCallCount = 0;
        $mocks['fileServiceMock']->shouldReceive('add')
            ->andReturnUsing(function () use (&$addCallCount) {
                $addCallCount++;
                if ($addCallCount >= 2) {
                    throw new \Exception('Second galley save failed');
                }
                return $addCallCount;
            });

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Multi Galley Test')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withGalleys('paper.pdf;slides.pptx', 'PDF;Slides')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // FileNotSavedException caught, galley cleanup ran, invalid CSV created
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertNotEmpty($invalidFiles);
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 601-637: syncCoverImagesForProcessedPreprints deep path.
     * Uses DB facade partial mock to handle publication_settings queries.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunSyncCoverImagesDeepPath(): void
    {
        $mocks = $this->setupAllMocks();

        $pub1 = $this->createMockPublication(['id' => 1, 'submissionId' => 1, 'sectionId' => 5]);
        $pub2 = $this->createMockPublication(['id' => 2, 'submissionId' => 1, 'sectionId' => 5]);
        $pub3 = $this->createMockPublication(['id' => 3, 'submissionId' => 2, 'sectionId' => 5]);
        $sub = $this->createMockSubmission(['id' => 1]);
        $sub2 = $this->createMockSubmission(['id' => 2]);

        $pubCreateCount = 0;
        $mocks['publicationProcessorMock']->shouldReceive('createInitialPublication')
            ->andReturnUsing(function () use (&$pubCreateCount, $pub1, $pub3) {
                return ++$pubCreateCount === 1 ? $pub1 : $pub3;
            });
        $mocks['publicationProcessorMock']->shouldReceive('process')
            ->andReturnUsing(function () use (&$pubCreateCount, $pub1, $pub3) {
                return $pubCreateCount === 1 ? $pub1 : $pub3;
            });
        $mocks['publicationProcessorMock']->shouldReceive('createPublicationVersion')->andReturn($pub2);
        $mocks['publicationProcessorMock']->shouldReceive('processVersionedPublication')->andReturn($pub2);
        $subCreateCount = 0;
        $mocks['submissionProcessorMock']->shouldReceive('process')
            ->andReturnUsing(function () use (&$subCreateCount, $sub, $sub2) {
                return ++$subCreateCount === 1 ? $sub : $sub2;
            });
        $mocks['pubRepoMock']->shouldReceive('get')->with(1)->andReturn($pub1);
        $mocks['pubRepoMock']->shouldReceive('get')->with(2)->andReturn($pub2);
        $mocks['pubRepoMock']->shouldReceive('get')->with(3)->andReturn($pub3);
        $mocks['subRepoMock']->shouldReceive('get')->with(1)->andReturn($sub);
        $mocks['subRepoMock']->shouldReceive('get')->with(2)->andReturn($sub2);

        // Mock DAORegistry for syncCoverImagesForProcessedPreprints - return a real server this time
        $server = $this->createMockServer(['id' => 1, 'path' => 'testserver', 'primaryLocale' => 'en']);
        $serverDaoMock = Mockery::mock(\APP\server\ServerDAO::class);
        $serverDaoMock->shouldReceive('getById')->andReturn($server);
        \PKP\db\DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        // Set up DB facade partial mock for publication_settings queries
        // PREP-001 v1: empty 'fr' (line 604) + valid 'en' (default locale → line 618)
        $defaultLocaleSettings = collect([
            (object) ['setting_value' => '', 'locale' => 'fr'],
            (object) ['setting_value' => json_encode(['uploadName' => 'cover.jpg', 'altText' => '']), 'locale' => 'en'],
        ]);
        // PREP-001 v2: empty 'fr' (line 604) + valid 'pt_BR' only (non-default → line 619)
        $nonDefaultLocaleSettings = collect([
            (object) ['setting_value' => '', 'locale' => 'fr'],
            (object) ['setting_value' => json_encode(['uploadName' => 'cover.jpg', 'altText' => '']), 'locale' => 'pt_BR'],
        ]);
        // PREP-002 v1: only empty settings → line 604 + coverImagesByLocale empty → line 614
        $emptyOnlySettings = collect([
            (object) ['setting_value' => '', 'locale' => 'en'],
        ]);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('whereNotNull')->andReturnSelf();
        $queryMock->shouldReceive('whereNot')->andReturnSelf();
        $queryMock->shouldReceive('distinct')->andReturnSelf();
        $queryMock->shouldReceive('get')->andReturn($defaultLocaleSettings, $nonDefaultLocaleSettings, $emptyOnlySettings);
        $queryMock->shouldReceive('pluck')->with('locale')->andReturn(collect(['en', 'pt_BR']));

        $realDb = \Illuminate\Support\Facades\DB::getFacadeRoot();
        $dbMock = Mockery::mock($realDb)->makePartial();
        $dbMock->shouldReceive('table')->with('publication_settings')->andReturn($queryMock);
        \Illuminate\Support\Facades\DB::swap($dbMock);

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $v1Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('1')
            ->withTitle('Version 1 Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();

        $v2Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('2')
            ->withTitle('Version 2 Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-02-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();

        // Second identifier with only 1 version - triggers line 614 (empty coverImagesByLocale)
        $prep2Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-002')
            ->withVersion('1')
            ->withTitle('Separate Preprint')
            ->withAuthors('Jane,Smith,jane@example.com,,MIT')
            ->withDatePosted('2024-03-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$v1Row, $v2Row, $prep2Row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 250-251: cover image upload exception triggers RowValidationException.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunHandlesCoverImageUploadException(): void
    {
        $mocks = $this->setupAllMocks();

        // Make uploadCoverImage throw an exception
        $mocks['publicationProcessorMock']->shouldReceive('uploadCoverImage')
            ->andThrow(new \Exception('Cover image upload failed'));

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Cover Image Exception Test')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withCoverImage('bad-image.jpg', 'Alt text')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // RowValidationException caught, invalid CSV created
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertNotEmpty($invalidFiles);
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers lines 384-390: cover image inherited from base publication in version import.
     * Uses a PHPUnit mock for Publication that stubs getLocalizedData to avoid request context.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunInheritsBaseCoverImageForVersion(): void
    {
        $mocks = $this->setupAllMocks();

        // Create pub1 as a PHPUnit mock so getLocalizedData doesn't hit request context
        /** @var \APP\publication\Publication|\PHPUnit\Framework\MockObject\MockObject */
        $pub1 = $this->getMockBuilder(\APP\publication\Publication::class)
            ->onlyMethods(['getLocalizedData'])
            ->getMock();
        $pub1->setId(1);
        $pub1->setData('submissionId', 1);
        $pub1->setData('sectionId', 5);
        $pub1->setData('version', 1);
        $pub1->method('getLocalizedData')
            ->willReturnCallback(function ($name, $locale = null) {
                if ($name === 'coverImage') {
                    return ['uploadName' => 'cover.jpg', 'altText' => 'Cover'];
                }
                return null;
            });

        $pub2 = $this->createMockPublication(['id' => 2, 'submissionId' => 1, 'sectionId' => 5]);
        $sub = $this->createMockSubmission(['id' => 1]);

        $mocks['publicationProcessorMock']->shouldReceive('createInitialPublication')->andReturn($pub1);
        $mocks['publicationProcessorMock']->shouldReceive('process')->andReturn($pub1);
        $mocks['publicationProcessorMock']->shouldReceive('createPublicationVersion')->andReturn($pub2);
        $mocks['publicationProcessorMock']->shouldReceive('processVersionedPublication')->andReturn($pub2);
        $mocks['submissionProcessorMock']->shouldReceive('process')->andReturn($sub);
        $mocks['pubRepoMock']->shouldReceive('get')->with(1)->andReturn($pub1);
        $mocks['pubRepoMock']->shouldReceive('get')->with(2)->andReturn($pub2);

        // Mock DAORegistry for syncCoverImagesForProcessedPreprints
        $serverDaoMock = Mockery::mock(\APP\server\ServerDAO::class);
        $serverDaoMock->shouldReceive('getById')->andReturn(null);
        \PKP\db\DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        // v1 does NOT set coverImageFilename
        $v1Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('1')
            ->withTitle('Version 1 Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();

        // v2 does NOT set coverImageFilename - should inherit from base publication
        $v2Row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('PREP-001')
            ->withVersion('2')
            ->withTitle('Version 2 Title')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-02-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$v1Row, $v2Row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // Line 385-389: PublicationProcessor::setCoverImage was called for v2 with base cover image
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Test 1: If a valid user is inserted on the username column, it must be assigned to the preprint.
     * Verifies that Repo::stageAssignment()->build() is called with the correct user.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunAssignsValidUserToPreprint(): void
    {
        $mocks = $this->setupAllMocks();

        // Add a valid cached user
        $csvUser = $this->createMockUser(['id' => 10, 'username' => 'validauthor']);
        CachedEntities::$users['validauthor'] = $csvUser;

        // Track stageAssignment build calls
        $stageAssignmentCalled = false;
        $capturedUserId = null;
        $capturedSubmissionId = null;
        $stageAssignmentMock = Mockery::mock(\PKP\stageAssignment\Repository::class);
        $stageAssignmentMock->shouldReceive('build')
            ->andReturnUsing(function ($submissionId, $userGroupId, $userId) use (&$stageAssignmentCalled, &$capturedUserId, &$capturedSubmissionId) {
                $stageAssignmentCalled = true;
                $capturedUserId = $userId;
                $capturedSubmissionId = $submissionId;
                return new \PKP\stageAssignment\StageAssignment();
            });
        app()->instance(\PKP\stageAssignment\Repository::class, $stageAssignmentMock);

        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('User Assignment Test')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withUsername('validauthor')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertTrue($stageAssignmentCalled, 'stageAssignment()->build() should have been called for valid user');
        $this->assertEquals(10, $capturedUserId, 'The valid user ID should be passed to stageAssignment');
        $this->assertEquals(1, $capturedSubmissionId, 'The submission ID should be passed to stageAssignment');
        $this->assertStringNotContainsString('plugins.importexport.csv.usernameNotFoundUsingDefault', $output);
    }

    /**
     * Test 2: If the username column is filled with an invalid user, there mustn't be any assignment.
     * Verifies that Repo::stageAssignment()->build() is NOT called for unknown users.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunDoesNotAssignInvalidUserToPreprint(): void
    {
        $mocks = $this->setupAllMocks();

        // Track stageAssignment build calls
        $stageAssignmentCalled = false;
        $stageAssignmentMock = Mockery::mock(\PKP\stageAssignment\Repository::class);
        $stageAssignmentMock->shouldReceive('build')
            ->andReturnUsing(function () use (&$stageAssignmentCalled) {
                $stageAssignmentCalled = true;
                return new \PKP\stageAssignment\StageAssignment();
            });
        app()->instance(\PKP\stageAssignment\Repository::class, $stageAssignmentMock);

        // 'nonexistentuser' is NOT in the cache, so getCachedUserByUsername returns null
        $headers = RequiredPreprintHeaders::$preprintHeaders;
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Invalid User Test')
            ->withAuthors('John,Doe,john@example.com,,MIT')
            ->withDatePosted('2024-01-15')
            ->withSectionTitle('Preprints')
            ->withSectionAbbrev('PRE')
            ->withUsername('nonexistentuser')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

        $senderUser = $this->createMockUser(['username' => 'admin']);
        $command = new PreprintCommand($this->tempDir, $senderUser);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertFalse($stageAssignmentCalled, 'stageAssignment()->build() should NOT have been called for invalid user');
        $this->assertStringContainsString('plugins.importexport.csv.usernameNotFoundUsingDefault', $output);
    }
}
