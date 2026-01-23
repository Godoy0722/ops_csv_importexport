<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/InvalidRowValidationsTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvalidRowValidationsTest
 *
 * @brief Tests for InvalidRowValidations class
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(InvalidRowValidations::class)]
class InvalidRowValidationsTest extends BaseTestCase
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

    // ==================== Row Field Validation Tests ====================

    public function testValidateRowContainAllFieldsWithCorrectCount(): void
    {
        $fields = ['field1', 'field2', 'field3'];
        $expectedSize = 3;

        $result = InvalidRowValidations::validateRowContainAllFields($fields, $expectedSize);

        $this->assertNull($result);
    }

    public function testValidateRowContainAllFieldsWithFewerFields(): void
    {
        $fields = ['field1', 'field2'];
        $expectedSize = 5;

        $result = InvalidRowValidations::validateRowContainAllFields($fields, $expectedSize);

        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    public function testValidateRowContainAllFieldsWithMoreFields(): void
    {
        $fields = ['field1', 'field2', 'field3', 'field4', 'field5'];
        $expectedSize = 3;

        // More fields than expected is OK (they will be trimmed)
        $result = InvalidRowValidations::validateRowContainAllFields($fields, $expectedSize);

        $this->assertNull($result);
    }

    // ==================== Required Fields Validation Tests ====================

    public function testValidateRowHasAllRequiredFieldsWithValidData(): void
    {
        $data = (object) ['required1' => 'value1', 'required2' => 'value2'];
        $validator = fn($row) => !empty($row->required1) && !empty($row->required2);

        $result = InvalidRowValidations::validateRowHasAllRequiredFields($data, $validator);

        $this->assertNull($result);
    }

    public function testValidateRowHasAllRequiredFieldsWithMissingData(): void
    {
        $data = (object) ['required1' => 'value1', 'required2' => ''];
        $validator = fn($row) => !empty($row->required1) && !empty($row->required2);

        $result = InvalidRowValidations::validateRowHasAllRequiredFields($data, $validator);

        $this->assertNotNull($result);
    }

    // ==================== Cover Image Validation Tests ====================

    public function testValidateCoverImageIsValidWithValidJpg(): void
    {
        $filename = 'test.jpg';
        $this->createTestFile($this->tempDir, $filename, 'fake image content');

        $result = InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);

        $this->assertNull($result);
    }

    public function testValidateCoverImageIsValidWithValidPng(): void
    {
        $filename = 'test.png';
        $this->createTestFile($this->tempDir, $filename, 'fake image content');

        $result = InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);

        $this->assertNull($result);
    }

    public function testValidateCoverImageIsValidWithValidGif(): void
    {
        $filename = 'test.gif';
        $this->createTestFile($this->tempDir, $filename, 'fake image content');

        $result = InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);

        $this->assertNull($result);
    }

    public function testValidateCoverImageIsValidWithValidWebp(): void
    {
        $filename = 'test.webp';
        $this->createTestFile($this->tempDir, $filename, 'fake image content');

        $result = InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);

        $this->assertNull($result);
    }

    public function testValidateCoverImageIsValidWithInvalidExtension(): void
    {
        $filename = 'test.bmp';
        $this->createTestFile($this->tempDir, $filename, 'fake image content');

        $result = InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);

        $this->assertNotNull($result);
    }

    public function testValidateCoverImageIsValidWithNonExistentFile(): void
    {
        $result = InvalidRowValidations::validateCoverImageIsValid('nonexistent.jpg', $this->tempDir);

        $this->assertNotNull($result);
    }

    // ==================== Galley Validation Tests ====================

    public function testValidatePreprintGalleysWithMatchingCountsAndExistingFiles(): void
    {
        $this->createTestFile($this->tempDir, 'paper.pdf');
        $this->createTestFile($this->tempDir, 'slides.pptx');

        $result = InvalidRowValidations::validatePreprintGalleys(
            'paper.pdf;slides.pptx',
            'PDF;SLIDES',
            $this->tempDir
        );

        $this->assertNull($result);
    }

    public function testValidatePreprintGalleysWithMismatchedCounts(): void
    {
        $result = InvalidRowValidations::validatePreprintGalleys(
            'paper.pdf;slides.pptx',
            'PDF',
            $this->tempDir
        );

        $this->assertNotNull($result);
    }

    public function testValidatePreprintGalleysWithNonExistentFile(): void
    {
        $this->createTestFile($this->tempDir, 'paper.pdf');

        $result = InvalidRowValidations::validatePreprintGalleys(
            'paper.pdf;nonexistent.pptx',
            'PDF;SLIDES',
            $this->tempDir
        );

        $this->assertNotNull($result);
    }

    // ==================== Supplementary Files Validation Tests ====================

    public function testValidateSupplementaryFilesWithMatchingCountsAndExistingFiles(): void
    {
        $this->createTestFile($this->tempDir, 'data.xlsx');
        $this->createTestFile($this->tempDir, 'supplement.pdf');

        $result = InvalidRowValidations::validateSupplementaryFiles(
            'data.xlsx;supplement.pdf',
            'Dataset;Supplement',
            $this->tempDir
        );

        $this->assertNull($result);
    }

    public function testValidateSupplementaryFilesWithMismatchedCounts(): void
    {
        $result = InvalidRowValidations::validateSupplementaryFiles(
            'data.xlsx;supplement.pdf',
            'Dataset',
            $this->tempDir
        );

        $this->assertNotNull($result);
    }

    public function testValidateSupplementaryDescriptionsWithMatchingCounts(): void
    {
        $result = InvalidRowValidations::validateSupplementaryDescriptions(
            'data.xlsx;supplement.pdf',
            'Dataset;Supplement',
            'Data description;Supplement description'
        );

        $this->assertNull($result);
    }

    public function testValidateSupplementaryDescriptionsWithMismatchedCounts(): void
    {
        $result = InvalidRowValidations::validateSupplementaryDescriptions(
            'data.xlsx;supplement.pdf',
            'Dataset;Supplement',
            'Only one description'
        );

        $this->assertNotNull($result);
    }

    public function testValidateSupplementaryDescriptionsWithEmptyDescriptions(): void
    {
        // Empty descriptions are optional
        $result = InvalidRowValidations::validateSupplementaryDescriptions(
            'data.xlsx;supplement.pdf',
            'Dataset;Supplement',
            ''
        );

        $this->assertNull($result);
    }

    // ==================== Server Validation Tests ====================

    public function testValidateServerIsValidWithValidServer(): void
    {
        $server = MockFactory::server()->build();

        $result = InvalidRowValidations::validateServerIsValid($server, 'testserver');

        $this->assertNull($result);
    }

    public function testValidateServerIsValidWithNullServer(): void
    {
        $result = InvalidRowValidations::validateServerIsValid(null, 'unknownserver');

        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    // ==================== Locale Validation Tests ====================

    public function testValidateServerLocaleWithSupportedLocale(): void
    {
        $server = MockFactory::server()
            ->withSupportedLocales(['en', 'pt_BR', 'es'])
            ->build();

        $result = InvalidRowValidations::validateServerLocale($server, 'en');

        $this->assertNull($result);
    }

    public function testValidateServerLocaleWithUnsupportedLocale(): void
    {
        $server = MockFactory::server()
            ->withSupportedLocales(['en', 'pt_BR'])
            ->build();

        $result = InvalidRowValidations::validateServerLocale($server, 'fr');

        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    // ==================== Genre Validation Tests ====================

    public function testValidateGenreIdValidWithValidId(): void
    {
        $result = InvalidRowValidations::validateGenreIdValid(1, 'SUBMISSION');

        $this->assertNull($result);
    }

    public function testValidateGenreIdValidWithNullId(): void
    {
        $result = InvalidRowValidations::validateGenreIdValid(null, 'UNKNOWN');

        $this->assertNotNull($result);
    }

    // ==================== User Group Validation Tests ====================

    public function testValidateUserGroupIdWithValidId(): void
    {
        $result = InvalidRowValidations::validateUserGroupId(1, 'testserver');

        $this->assertNull($result);
    }

    public function testValidateUserGroupIdWithNullId(): void
    {
        $result = InvalidRowValidations::validateUserGroupId(null, 'testserver');

        $this->assertNotNull($result);
    }

    // ==================== Versioning Field Validation Tests ====================

    public function testValidatePreprintVersioningFieldsWithValidData(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => '1'];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNull($result);
    }

    public function testValidatePreprintVersioningFieldsWithIdentifierWithoutVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => ''];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNotNull($result);
    }

    public function testValidatePreprintVersioningFieldsWithNonIntegerVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => 'abc'];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNotNull($result);
    }

    public function testValidatePreprintVersioningFieldsWithNegativeVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => '-1'];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNotNull($result);
    }

    public function testValidatePreprintVersioningFieldsWithZeroVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => '0'];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNotNull($result);
    }

    public function testValidatePreprintVersioningFieldsWithEmptyBoth(): void
    {
        $data = (object) ['versionIdentifier' => '', 'version' => ''];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNull($result);
    }

    // ==================== Duplicate Version Detection Tests ====================

    public function testValidateNoDuplicateVersionWithNewCombination(): void
    {
        $data = (object) [
            'versionIdentifier' => 'TEST-001',
            'version' => '1',
            'locale' => 'en'
        ];
        $processedPreprints = [];

        $result = InvalidRowValidations::validateNoDuplicateVersion($data, $processedPreprints);

        $this->assertNull($result);
    }

    public function testValidateNoDuplicateVersionWithExistingCombination(): void
    {
        $data = (object) [
            'versionIdentifier' => 'TEST-001',
            'version' => '1',
            'locale' => 'en'
        ];
        $processedPreprints = [
            'TEST-001' => [
                1 => [
                    'en' => ['data' => $data]
                ]
            ]
        ];

        $result = InvalidRowValidations::validateNoDuplicateVersion($data, $processedPreprints);

        $this->assertNotNull($result);
    }

    public function testVersionExistsInAnyLocaleWhenExists(): void
    {
        $data = (object) [
            'versionIdentifier' => 'TEST-001',
            'version' => '1',
            'locale' => 'pt_BR'
        ];
        $processedPreprints = [
            'TEST-001' => [
                1 => [
                    'en' => ['data' => (object) []]
                ]
            ]
        ];

        $result = InvalidRowValidations::versionExistsInAnyLocale($data, $processedPreprints);

        $this->assertTrue($result);
    }

    public function testVersionExistsInAnyLocaleWhenNotExists(): void
    {
        $data = (object) [
            'versionIdentifier' => 'TEST-001',
            'version' => '2',
            'locale' => 'en'
        ];
        $processedPreprints = [
            'TEST-001' => [
                1 => [
                    'en' => ['data' => (object) []]
                ]
            ]
        ];

        $result = InvalidRowValidations::versionExistsInAnyLocale($data, $processedPreprints);

        $this->assertFalse($result);
    }

    // ==================== References File Validation Tests ====================

    public function testValidateReferencesFileWithValidTxtFile(): void
    {
        $filename = 'references.txt';
        $this->createTestFile($this->tempDir, $filename, 'Reference 1\nReference 2');

        $result = InvalidRowValidations::validateReferencesFile($filename, $this->tempDir);

        $this->assertNull($result);
    }

    public function testValidateReferencesFileWithInvalidExtension(): void
    {
        $filename = 'references.pdf';
        $this->createTestFile($this->tempDir, $filename);

        $result = InvalidRowValidations::validateReferencesFile($filename, $this->tempDir);

        $this->assertNotNull($result);
    }

    public function testValidateReferencesFileWithNonExistentFile(): void
    {
        $result = InvalidRowValidations::validateReferencesFile('nonexistent.txt', $this->tempDir);

        $this->assertNotNull($result);
    }

    public function testValidateReferencesFileWithEmptyFilename(): void
    {
        $result = InvalidRowValidations::validateReferencesFile('', $this->tempDir);

        $this->assertNull($result);
    }

    // ==================== ORCID Validation Tests ====================

    #[DataProvider('validOrcidProvider')]
    public function testValidateOrcidWithValidFormats(string $orcid): void
    {
        $result = InvalidRowValidations::validateOrcid($orcid);

        $this->assertNull($result);
    }

    public static function validOrcidProvider(): array
    {
        return [
            'Full URL' => ['https://orcid.org/0000-0002-1825-0097'],
            'Sandbox URL' => ['https://sandbox.orcid.org/0000-0002-1825-0097'],
            'Dashed format' => ['0000-0002-1825-0097'],
            'Numeric format' => ['0000000218250097'],
            'With X checksum' => ['0000-0001-5109-3700'],
        ];
    }

    public function testValidateOrcidWithInvalidFormat(): void
    {
        $result = InvalidRowValidations::validateOrcid('invalid-orcid');

        $this->assertNotNull($result);
    }

    public function testValidateOrcidWithInvalidChecksum(): void
    {
        // Invalid checksum - last digit should be different
        $result = InvalidRowValidations::validateOrcid('0000-0002-1825-0098');

        $this->assertNotNull($result);
    }

    public function testValidateOrcidWithEmptyValue(): void
    {
        $result = InvalidRowValidations::validateOrcid('');

        $this->assertNull($result);
    }

    public function testValidateOrcidWithNullValue(): void
    {
        $result = InvalidRowValidations::validateOrcid(null);

        $this->assertNull($result);
    }

    // ==================== ORCID Normalization Tests ====================

    public function testNormalizeOrcidFromDashedFormat(): void
    {
        $result = InvalidRowValidations::normalizeOrcid('0000-0002-1825-0097');

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $result);
    }

    public function testNormalizeOrcidFromNumericFormat(): void
    {
        $result = InvalidRowValidations::normalizeOrcid('0000000218250097');

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $result);
    }

    public function testNormalizeOrcidFromFullUrl(): void
    {
        $result = InvalidRowValidations::normalizeOrcid('https://orcid.org/0000-0002-1825-0097');

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $result);
    }

    public function testNormalizeOrcidWithInvalidFormat(): void
    {
        $result = InvalidRowValidations::normalizeOrcid('invalid');

        $this->assertNull($result);
    }

    // ==================== VOR DOI Validation Tests ====================

    #[DataProvider('validVorDoiProvider')]
    public function testValidateVorDoiWithValidFormats(string $vorDoi): void
    {
        $result = InvalidRowValidations::validateVorDoi($vorDoi);

        $this->assertNull($result);
    }

    public static function validVorDoiProvider(): array
    {
        return [
            'Full HTTPS URL' => ['https://doi.org/10.1234/example'],
            'HTTP URL' => ['http://doi.org/10.1234/example'],
            'DX DOI URL' => ['https://dx.doi.org/10.1234/example'],
            'DOI prefix' => ['doi:10.1234/example'],
            'Bare DOI' => ['10.1234/example'],
            'Complex DOI' => ['10.1016/j.example.2024.123456'],
        ];
    }

    public function testValidateVorDoiWithInvalidFormat(): void
    {
        $result = InvalidRowValidations::validateVorDoi('invalid-doi');

        $this->assertNotNull($result);
    }

    public function testValidateVorDoiWithEmptyValue(): void
    {
        $result = InvalidRowValidations::validateVorDoi('');

        $this->assertNull($result);
    }

    // ==================== VOR DOI Normalization Tests ====================

    public function testNormalizeVorDoiFromBareDoi(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi('10.1234/example');

        $this->assertEquals('https://doi.org/10.1234/example', $result);
    }

    public function testNormalizeVorDoiFromDoiPrefix(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi('doi:10.1234/example');

        $this->assertEquals('https://doi.org/10.1234/example', $result);
    }

    public function testNormalizeVorDoiFromDxUrl(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi('http://dx.doi.org/10.1234/example');

        $this->assertEquals('https://doi.org/10.1234/example', $result);
    }

    // ==================== Funders Validation Tests ====================

    public function testValidateFundersWithValidSingleFunder(): void
    {
        $funders = 'National Science Foundation,http://dx.doi.org/10.13039/100000001,NSF-2024-001';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNull($result);
    }

    public function testValidateFundersWithMultipleFunders(): void
    {
        $funders = 'NSF,http://dx.doi.org/10.13039/100000001,Award1;DOE,http://dx.doi.org/10.13039/100000015,Award2';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNull($result);
    }

    public function testValidateFundersWithFunderWithoutName(): void
    {
        $funders = ',http://dx.doi.org/10.13039/100000001,Award1';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNotNull($result);
    }

    public function testValidateFundersWithEmptyString(): void
    {
        $result = InvalidRowValidations::validateFunders('');

        $this->assertNull($result);
    }

    public function testValidateFundersWithNullValue(): void
    {
        $result = InvalidRowValidations::validateFunders(null);

        $this->assertNull($result);
    }

    public function testValidateFundersWithMultipleAwards(): void
    {
        $funders = 'NSF,http://dx.doi.org/10.13039/100000001,Award1|Award2|Award3';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNull($result);
    }

    public function testValidateFundersWithFunderWithoutAwards(): void
    {
        $funders = 'Wellcome Trust,http://dx.doi.org/10.13039/100010269,';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNull($result);
    }
}
