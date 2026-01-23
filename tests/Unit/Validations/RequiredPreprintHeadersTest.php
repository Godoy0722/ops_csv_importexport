<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/RequiredPreprintHeadersTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredPreprintHeadersTest
 *
 * @brief Tests for RequiredPreprintHeaders class
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\RequiredPreprintHeaders;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequiredPreprintHeaders::class)]
class RequiredPreprintHeadersTest extends BaseTestCase
{
    // ==================== validateRowHasAllFields Tests ====================

    public function testValidateRowHasAllFieldsWithExactCount(): void
    {
        $row = array_fill(0, count(RequiredPreprintHeaders::$preprintHeaders), 'value');

        $result = RequiredPreprintHeaders::validateRowHasAllFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllFieldsWithFewerHeaders(): void
    {
        $row = array_fill(0, count(RequiredPreprintHeaders::$preprintHeaders) - 5, 'value');

        $result = RequiredPreprintHeaders::validateRowHasAllFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllFieldsWithMoreHeaders(): void
    {
        $row = array_fill(0, count(RequiredPreprintHeaders::$preprintHeaders) + 5, 'value');

        $result = RequiredPreprintHeaders::validateRowHasAllFields($row);

        $this->assertFalse($result);
    }

    // ==================== validateRowHasAllRequiredFields Tests - Version 1 ====================

    public function testValidateRowHasAllRequiredFieldsVersion1WithAllRequired(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion1MissingServerPath(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion1MissingLocale(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion1MissingTitle(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion1MissingAuthors(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion1MissingDatePosted(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    // ==================== validateRowHasAllRequiredFields Tests - Version > 1 ====================

    public function testValidateRowHasAllRequiredFieldsVersion2WithOnlyIdentifierVersionLocale(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withVersionIdentifier('TEST-001')
            ->withVersion('2')
            ->withLocale('en')
            ->buildObject();

        // Version > 1 only requires identifier, version, and locale
        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion2WithAllFields(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withVersionIdentifier('TEST-001')
            ->withVersion('2')
            ->withTitle('Updated Title')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('2024-02-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion3(): void
    {
        $row = CsvTestDataBuilder::preprint()
            ->withVersionIdentifier('TEST-001')
            ->withVersion('3')
            ->withLocale('en')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    // ==================== validateRowHasAllRequiredFields Tests - Multi-Locale ====================

    public function testValidateRowHasAllRequiredFieldsMultiLocaleSameVersion(): void
    {
        // Simulating adding a new locale to an existing version
        $processedPreprints = [
            'TEST-001' => [
                1 => [
                    'en' => ['data' => (object) []]
                ]
            ]
        ];

        $row = CsvTestDataBuilder::preprint()
            ->withVersionIdentifier('TEST-001')
            ->withVersion('1')
            ->withLocale('pt_BR')
            ->buildObject();

        // Multi-locale row (same version, new locale) should only require identifier, version, locale
        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row, $processedPreprints);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsMultiLocaleWithTranslatedTitle(): void
    {
        $processedPreprints = [
            'TEST-001' => [
                1 => [
                    'en' => ['data' => (object) []]
                ]
            ]
        ];

        $row = CsvTestDataBuilder::preprint()
            ->withVersionIdentifier('TEST-001')
            ->withVersion('1')
            ->withLocale('pt_BR')
            ->withTitle('Título em Português')
            ->withAbstract('Resumo em Português')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row, $processedPreprints);

        $this->assertTrue($result);
    }

    // ==================== Edge Cases ====================

    public function testValidateRowHasAllRequiredFieldsWithNoVersioningInfo(): void
    {
        // Standard import without versioning - requires all fields
        $row = CsvTestDataBuilder::preprint()
            ->withServerPath('testserver')
            ->withLocale('en')
            ->withTitle('Test Preprint')
            ->withAuthors('John,Doe,john@example.com,,University')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsVersion1RequiresAllFields(): void
    {
        // Version 1 explicitly set but missing required fields
        $row = CsvTestDataBuilder::preprint()
            ->withVersionIdentifier('TEST-001')
            ->withVersion('1')
            ->withLocale('en')
            ->buildObject();

        // Version 1 requires all fields
        $result = RequiredPreprintHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testPreprintHeadersArrayIsComplete(): void
    {
        // Ensure all expected headers exist
        $expectedHeaders = [
            'serverPath', 'locale', 'versionIdentifier', 'version', 'preprintPrefix',
            'preprintTitle', 'preprintSubtitle', 'preprintAbstract', 'authors', 'keywords',
            'subjects', 'coverage', 'categories', 'doi', 'coverImageFilename',
            'coverImageAltText', 'galleyFilenames', 'galleyLabels', 'suppFilenames',
            'suppLabels', 'suppDescriptions', 'sectionTitle', 'sectionAbbrev', 'datePosted',
            'dateSubmitted', 'copyrightYear', 'copyrightHolder', 'licenseUrl', 'references',
            'vorDoi', 'supportingAgencies', 'username', 'funders'
        ];

        $this->assertEquals($expectedHeaders, RequiredPreprintHeaders::$preprintHeaders);
    }

    public function testPreprintRequiredHeadersArrayIsCorrect(): void
    {
        $expectedRequired = [
            'serverPath',
            'locale',
            'preprintTitle',
            'authors',
            'datePosted',
        ];

        $this->assertEquals($expectedRequired, RequiredPreprintHeaders::$preprintRequiredHeaders);
    }
}
