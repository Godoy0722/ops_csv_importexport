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

use APP\plugins\importexport\csv\classes\commands\PreprintCommand;
use APP\plugins\importexport\csv\classes\validations\RequiredPreprintHeaders;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

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

        $this->assertCount(33, $headers);
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
}
