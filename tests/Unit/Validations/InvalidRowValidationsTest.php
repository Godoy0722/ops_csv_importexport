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

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\exceptions\RowValidationException;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use APP\server\Server;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateRowContainAllFields($fields, $expectedSize);
    }

    public function testValidateRowContainAllFieldsWithMoreFields(): void
    {
        $fields = ['field1', 'field2', 'field3', 'field4', 'field5'];
        $expectedSize = 3;

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateRowContainAllFields($fields, $expectedSize);
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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateRowHasAllRequiredFields($data, $validator);
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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);
    }

    public function testValidateCoverImageIsValidWithNonExistentFile(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateCoverImageIsValid('nonexistent.jpg', $this->tempDir);
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
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintGalleys(
            'paper.pdf;slides.pptx',
            'PDF',
            $this->tempDir
        );
    }

    public function testValidatePreprintGalleysWithNonExistentFile(): void
    {
        $this->createTestFile($this->tempDir, 'paper.pdf');

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintGalleys(
            'paper.pdf;nonexistent.pptx',
            'PDF;SLIDES',
            $this->tempDir
        );
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
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateSupplementaryFiles(
            'data.xlsx;supplement.pdf',
            'Dataset',
            $this->tempDir
        );
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
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateSupplementaryDescriptions(
            'data.xlsx;supplement.pdf',
            'Dataset;Supplement',
            'Only one description'
        );
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
        $server = MockFactory::server()->build(); /** @var Server $server */

        $result = InvalidRowValidations::validateServerIsValid($server, 'testserver');

        $this->assertNull($result);
    }

    public function testValidateServerIsValidWithNullServer(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateServerIsValid(null, 'unknownserver');
    }

    // ==================== Locale Validation Tests ====================

    public function testValidateServerLocaleWithSupportedLocale(): void
    {
        /** @var Server */
        $server = MockFactory::server()
            ->withSupportedLocales(['en', 'pt_BR', 'es'])
            ->build();

        $result = InvalidRowValidations::validateServerLocale($server, 'en');

        $this->assertNull($result);
    }

    public function testValidateServerLocaleWithUnsupportedLocale(): void
    {
        /** @var Server */
        $server = MockFactory::server()
            ->withSupportedLocales(['en', 'pt_BR'])
            ->build();

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateServerLocale($server, 'fr');
    }

    // ==================== Genre Validation Tests ====================

    public function testValidateGenreIdValidWithValidId(): void
    {
        $result = InvalidRowValidations::validateGenreIdValid(1, 'SUBMISSION');

        $this->assertNull($result);
    }

    public function testValidateGenreIdValidWithNullId(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateGenreIdValid(null, 'UNKNOWN');
    }

    // ==================== User Group Validation Tests ====================

    public function testValidateUserGroupIdWithValidId(): void
    {
        $result = InvalidRowValidations::validateUserGroupId(1, 'testserver');

        $this->assertNull($result);
    }

    public function testValidateUserGroupIdWithNullId(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateUserGroupId(null, 'testserver');
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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintVersioningFields($data);
    }

    public function testValidatePreprintVersioningFieldsWithNonIntegerVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => 'abc'];

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintVersioningFields($data);
    }

    public function testValidatePreprintVersioningFieldsWithNegativeVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => '-1'];

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintVersioningFields($data);
    }

    public function testValidatePreprintVersioningFieldsWithZeroVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => '0'];

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintVersioningFields($data);
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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateNoDuplicateVersion($data, $processedPreprints);
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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateReferencesFile($filename, $this->tempDir);
    }

    public function testValidateReferencesFileWithNonExistentFile(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateReferencesFile('nonexistent.txt', $this->tempDir);
    }

    public function testValidateReferencesFileWithEmptyFilename(): void
    {
        $result = InvalidRowValidations::validateReferencesFile('', $this->tempDir);

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
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateVorDoi('invalid-doi');
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

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateFunders($funders);
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

    // ==================== Email Validation Tests ====================

    public function testValidateEmailWithValidEmail(): void
    {
        $result = InvalidRowValidations::validateEmail('user@example.com');

        $this->assertNull($result);
    }

    public function testValidateEmailWithInvalidEmail(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateEmail('not-an-email');
    }

    public function testValidateEmailWithEmptyValue(): void
    {
        $result = InvalidRowValidations::validateEmail('');

        $this->assertNull($result);
    }

    public function testValidateEmailWithComplexValidEmail(): void
    {
        $result = InvalidRowValidations::validateEmail('user.name+tag@sub.domain.org');

        $this->assertNull($result);
    }

    public function testValidateEmailWithMissingDomain(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateEmail('user@');
    }

    public function testValidateEmailWithMissingAtSign(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateEmail('userdomain.com');
    }

    // ==================== Section Fields Validation Tests ====================

    public function testValidateSectionFieldsWithBothFilled(): void
    {
        $data = (object) ['sectionTitle' => 'Preprints', 'sectionAbbrev' => 'PRE'];

        $result = InvalidRowValidations::validateSectionFields($data);

        $this->assertNull($result);
    }

    public function testValidateSectionFieldsWithBothEmpty(): void
    {
        $data = (object) ['sectionTitle' => '', 'sectionAbbrev' => ''];

        $result = InvalidRowValidations::validateSectionFields($data);

        $this->assertNull($result);
    }

    public function testValidateSectionFieldsWithOnlyTitle(): void
    {
        $data = (object) ['sectionTitle' => 'Preprints', 'sectionAbbrev' => ''];

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateSectionFields($data);
    }

    public function testValidateSectionFieldsWithOnlyAbbrev(): void
    {
        $data = (object) ['sectionTitle' => '', 'sectionAbbrev' => 'PRE'];

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateSectionFields($data);
    }

    // ==================== Publication Created Validation Tests ====================

    public function testValidatePublicationWasSuccessfullyCreatedWithValidPublication(): void
    {
        $publication = MockFactory::publication()->build();

        $result = InvalidRowValidations::validatePublicationWasSuccessfullyCreated($publication);

        $this->assertNull($result);
    }

    public function testValidatePublicationWasSuccessfullyCreatedWithNull(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePublicationWasSuccessfullyCreated(null);
    }

    // ==================== Preprint File Validation Tests ====================

    public function testValidatePreprintFileIsValidWithExistingFile(): void
    {
        $filename = 'paper.pdf';
        $this->createTestFile($this->tempDir, $filename, 'PDF content');

        $result = InvalidRowValidations::validatePreprintFileIsValid($filename, $this->tempDir);

        $this->assertNull($result);
    }

    public function testValidatePreprintFileIsValidWithNonExistentFile(): void
    {
        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validatePreprintFileIsValid('missing.pdf', $this->tempDir);
    }

    // ==================== Funder Crossref Registry Validation Tests ====================

    public function testValidateFundersCrossrefRegistryWithEmptyString(): void
    {
        $result = InvalidRowValidations::validateFundersCrossrefRegistry('', 1);

        $this->assertNull($result);
    }

    public function testValidateFundersCrossrefRegistryWithNullValue(): void
    {
        $result = InvalidRowValidations::validateFundersCrossrefRegistry(null, 1);

        $this->assertNull($result);
    }

    // ==================== VOR DOI Normalization Edge Cases ====================

    public function testNormalizeVorDoiWithNullValue(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi(null);

        $this->assertNull($result);
    }

    public function testNormalizeVorDoiWithEmptyString(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi('');

        $this->assertNull($result);
    }

    public function testNormalizeVorDoiWithWhitespace(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi('  10.1234/example  ');

        $this->assertEquals('https://doi.org/10.1234/example', $result);
    }

    public function testNormalizeVorDoiWithInvalidFormat(): void
    {
        $result = InvalidRowValidations::normalizeVorDoi('not-a-doi');

        $this->assertNull($result);
    }

    // ==================== Cover Image Allowed Types Tests ====================

    public function testCoverImageAllowedTypesIsComplete(): void
    {
        $expected = ['gif', 'jpg', 'png', 'webp'];
        $this->assertEquals($expected, InvalidRowValidations::$coverImageAllowedTypes);
    }

    // ==================== Multiple Funders Edge Cases ====================

    public function testValidateFundersWithEmptyFunderBetweenSemicolons(): void
    {
        $funders = 'NSF,http://dx.doi.org/10.13039/100000001,Award1;;DOE,http://dx.doi.org/10.13039/100000015,Award2';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNull($result);
    }

    public function testValidateFundersWithFunderNameOnly(): void
    {
        $funders = 'National Science Foundation';

        $result = InvalidRowValidations::validateFunders($funders);

        $this->assertNull($result);
    }

    // ==================== validateAllUserGroupsAreValid Tests ====================

    public function testValidateAllUserGroupsAreValidWithMatchingRoles(): void
    {
        $userGroup1 = $this->createMockUserGroup(['id' => 1, 'name' => ['en' => 'Author']]);
        $userGroup2 = $this->createMockUserGroup(['id' => 2, 'name' => ['en' => 'Reader']]);
        CachedEntities::$userGroups[1] = [1 => $userGroup1, 2 => $userGroup2];

        $result = InvalidRowValidations::validateAllUserGroupsAreValid(['Author', 'Reader'], 1, 'en');

        $this->assertNull($result);
    }

    public function testValidateAllUserGroupsAreValidThrowsForMissingRole(): void
    {
        $userGroup1 = $this->createMockUserGroup(['id' => 1, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [1 => $userGroup1];

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateAllUserGroupsAreValid(['Author', 'NonExistentRole'], 1, 'en');
    }

    public function testValidateAllUserGroupsAreValidCaseInsensitive(): void
    {
        $userGroup = $this->createMockUserGroup(['id' => 1, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [1 => $userGroup];

        $result = InvalidRowValidations::validateAllUserGroupsAreValid(['author'], 1, 'en');

        $this->assertNull($result);
    }

    public function testValidateAllUserGroupsAreValidWithEmptyRoles(): void
    {
        CachedEntities::$userGroups[1] = [];

        $result = InvalidRowValidations::validateAllUserGroupsAreValid([], 1, 'en');

        $this->assertNull($result);
    }

    // ==================== validateUserAlreadyExistsWithThisUsername Tests ====================

    public function testValidateUserAlreadyExistsWithThisUsernamePassesForNewUsername(): void
    {
        // No users in cache = no existing users
        $result = InvalidRowValidations::validateUserAlreadyExistsWithThisUsername('newuser');

        $this->assertNull($result);
    }

    public function testValidateUserAlreadyExistsWithThisUsernameThrowsForExistingUsername(): void
    {
        $existingUser = MockFactory::user()->withUsername('existinguser')->build();
        CachedEntities::$users['existinguser'] = $existingUser;

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateUserAlreadyExistsWithThisUsername('existinguser');
    }

    // ==================== validateUserAlreadyExistsWithThisEmail Tests ====================

    public function testValidateUserAlreadyExistsWithThisEmailPassesForNewEmail(): void
    {
        $result = InvalidRowValidations::validateUserAlreadyExistsWithThisEmail('new@example.com');

        $this->assertNull($result);
    }

    public function testValidateUserAlreadyExistsWithThisEmailThrowsForExistingEmail(): void
    {
        $existingUser = MockFactory::user()->withEmail('existing@example.com')->build();
        CachedEntities::$users['existing@example.com'] = $existingUser;

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateUserAlreadyExistsWithThisEmail('existing@example.com');
    }

    // ==================== validateSupplementaryFiles Additional Tests ====================

    public function testValidateSupplementaryFilesWithNonExistentFile(): void
    {
        $this->createTestFile($this->tempDir, 'data.xlsx');

        $this->expectException(RowValidationException::class);
        InvalidRowValidations::validateSupplementaryFiles(
            'data.xlsx;nonexistent.pdf',
            'Dataset;Supplement',
            $this->tempDir
        );
    }

    // ==================== validateServerLocale Additional Tests ====================

    public function testValidateServerLocaleWithEmptyLocalesArray(): void
    {
        /** @var Server */
        $server = MockFactory::server()
            ->withSupportedLocales([])
            ->withPrimaryLocale('en')
            ->build();

        $result = InvalidRowValidations::validateServerLocale($server, 'en');

        $this->assertNull($result);
    }

    // ==================== validateCoverImageIsValid with uppercase extension ====================

    public function testValidateCoverImageIsValidWithUppercaseExtension(): void
    {
        $filename = 'test.JPG';
        $this->createTestFile($this->tempDir, $filename, 'fake image content');

        $result = InvalidRowValidations::validateCoverImageIsValid($filename, $this->tempDir);

        $this->assertNull($result);
    }

    // ==================== validatePreprintVersioningFields Additional Tests ====================

    public function testValidatePreprintVersioningFieldsWithVersionOnly(): void
    {
        $data = (object) ['versionIdentifier' => '', 'version' => '2'];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNull($result);
    }

    public function testValidatePreprintVersioningFieldsWithHighVersion(): void
    {
        $data = (object) ['versionIdentifier' => 'TEST-001', 'version' => '100'];

        $result = InvalidRowValidations::validatePreprintVersioningFields($data);

        $this->assertNull($result);
    }

    // ==================== validateNoDuplicateVersion Additional Tests ====================

    public function testValidateNoDuplicateVersionDifferentLocalesOk(): void
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

        $result = InvalidRowValidations::validateNoDuplicateVersion($data, $processedPreprints);

        $this->assertNull($result);
    }

    public function testValidateNoDuplicateVersionDifferentVersionsOk(): void
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

        $result = InvalidRowValidations::validateNoDuplicateVersion($data, $processedPreprints);

        $this->assertNull($result);
    }

    // ==================== validateReferencesFile Additional Tests ====================

    public function testValidateReferencesFileWithNullFilename(): void
    {
        $result = InvalidRowValidations::validateReferencesFile(null, $this->tempDir);

        $this->assertNull($result);
    }

    // ==================== validatePreprintGalleys Additional Tests ====================

    public function testValidatePreprintGalleysWithSingleGalley(): void
    {
        $this->createTestFile($this->tempDir, 'paper.pdf');

        $result = InvalidRowValidations::validatePreprintGalleys('paper.pdf', 'PDF', $this->tempDir);

        $this->assertNull($result);
    }

    // ==================== versionExistsInAnyLocale Additional Tests ====================

    public function testVersionExistsInAnyLocaleWithEmptyProcessedPreprints(): void
    {
        $data = (object) [
            'versionIdentifier' => 'TEST-001',
            'version' => '1',
            'locale' => 'en'
        ];

        $result = InvalidRowValidations::versionExistsInAnyLocale($data, []);

        $this->assertFalse($result);
    }

    public function testVersionExistsInAnyLocaleWithDifferentIdentifier(): void
    {
        $data = (object) [
            'versionIdentifier' => 'TEST-002',
            'version' => '1',
            'locale' => 'en'
        ];
        $processedPreprints = [
            'TEST-001' => [
                1 => ['en' => ['data' => (object) []]]
            ]
        ];

        $result = InvalidRowValidations::versionExistsInAnyLocale($data, $processedPreprints);

        $this->assertFalse($result);
    }

    // ==================== Funding Plugin Enabled Validation Tests ====================

    public function testValidateFundingPluginEnabledWithEmptyStringReturnsEarly(): void
    {
        InvalidRowValidations::validateFundingPluginEnabled('', 1);
        $this->assertTrue(true);
    }

    public function testValidateFundingPluginEnabledWithNullReturnsEarly(): void
    {
        InvalidRowValidations::validateFundingPluginEnabled(null, 1);
        $this->assertTrue(true);
    }

    // ==================== Funding Plugin and Crossref Registry Validation (RunInSeparateProcess) ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFundingPluginAndCrossrefRegistryValidation(): void
    {
        $fundersMock = Mockery::mock('overload:' . FundersProcessor::class);
        $fundersMock->shouldReceive('isFundingPluginEnabled')
            ->with(1)->andReturn(true);
        $fundersMock->shouldReceive('isFundingPluginEnabled')
            ->with(2)->andReturn(false);
        $fundersMock->shouldReceive('isCrossrefValidationEnabled')
            ->with(1)->andReturn(false);
        $fundersMock->shouldReceive('isCrossrefValidationEnabled')
            ->with(2)->andReturn(true);

        // Plugin enabled → no exception
        InvalidRowValidations::validateFundingPluginEnabled('NSF,http://dx.doi.org/10.13039/100000001,Award1', 1);

        // Plugin disabled → throws
        try {
            InvalidRowValidations::validateFundingPluginEnabled('NSF,http://dx.doi.org/10.13039/100000001,Award1', 2);
            $this->fail('Expected RowValidationException for disabled funding plugin');
        } catch (RowValidationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // Crossref validation disabled → early return (no exception)
        InvalidRowValidations::validateFundersCrossrefRegistry('NSF,http://dx.doi.org/10.13039/100000001,Award1', 1);

        // Crossref validation enabled + valid DOIs + empty funder (skip) + empty name (skip)
        InvalidRowValidations::validateFundersCrossrefRegistry(
            'NSF,http://dx.doi.org/10.13039/100000001,Award1;;,http://doi.org/10.13039/100000002,Award2',
            2
        );

        // Invalid crossref DOI → throws
        try {
            InvalidRowValidations::validateFundersCrossrefRegistry('NSF,https://example.com/not-crossref,Award1', 2);
            $this->fail('Expected RowValidationException for invalid crossref DOI');
        } catch (RowValidationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // Missing funder identification → throws
        try {
            InvalidRowValidations::validateFundersCrossrefRegistry('NSF,,Award1', 2);
            $this->fail('Expected RowValidationException for missing funder identification');
        } catch (RowValidationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertTrue(true);
    }
}
