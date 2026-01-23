<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Validations/RequiredUserHeadersTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RequiredUserHeadersTest
 *
 * @brief Tests for RequiredUserHeaders class
 */

namespace APP\plugins\importexport\csv\tests\Unit\Validations;

use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequiredUserHeaders::class)]
class RequiredUserHeadersTest extends BaseTestCase
{
    // ==================== validateRowHasAllFields Tests ====================

    public function testValidateRowHasAllFieldsWithExactCount(): void
    {
        $row = array_fill(0, count(RequiredUserHeaders::$userHeaders), 'value');

        $result = RequiredUserHeaders::validateRowHasAllFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllFieldsWithFewerHeaders(): void
    {
        $row = array_fill(0, count(RequiredUserHeaders::$userHeaders) - 3, 'value');

        $result = RequiredUserHeaders::validateRowHasAllFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllFieldsWithMoreHeaders(): void
    {
        $row = array_fill(0, count(RequiredUserHeaders::$userHeaders) + 3, 'value');

        $result = RequiredUserHeaders::validateRowHasAllFields($row);

        $this->assertFalse($result);
    }

    // ==================== validateRowHasAllRequiredFields Tests ====================

    public function testValidateRowHasAllRequiredFieldsWithAllRequired(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsMissingServerPath(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsMissingFirstname(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsMissingLastname(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsMissingEmail(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('')
            ->withRoles('Author')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    public function testValidateRowHasAllRequiredFieldsMissingRoles(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertFalse($result);
    }

    // ==================== Optional Fields Tests ====================

    public function testValidateRowHasAllRequiredFieldsWithAllRequiredMissingOptional(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            // Optional fields empty
            ->withAffiliation('')
            ->withCountry('')
            ->withUsername('')
            ->withPassword('')
            ->withReviewInterests('')
            ->withOrcid('')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsWithAllFieldsPopulated(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withAffiliation('MIT')
            ->withCountry('US')
            ->withUsername('jdoe')
            ->withPassword('temppass123')
            ->withRoles('Author;Reader')
            ->withReviewInterests('machine learning;data science')
            ->withOrcid('0000-0002-1825-0097')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    // ==================== Header Array Tests ====================

    public function testUserHeadersArrayIsComplete(): void
    {
        $expectedHeaders = [
            'serverPath',
            'firstname',
            'lastname',
            'email',
            'affiliation',
            'country',
            'username',
            'tempPassword',
            'roles',
            'reviewInterests',
            'orcid',
        ];

        $this->assertEquals($expectedHeaders, RequiredUserHeaders::$userHeaders);
    }

    public function testUserRequiredHeadersArrayIsCorrect(): void
    {
        $expectedRequired = [
            'serverPath',
            'firstname',
            'lastname',
            'email',
            'roles',
        ];

        $this->assertEquals($expectedRequired, RequiredUserHeaders::$userRequiredHeaders);
    }

    // ==================== Multiple Roles Tests ====================

    public function testValidateRowHasAllRequiredFieldsWithMultipleRoles(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author;Reader;Reviewer')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }

    public function testValidateRowHasAllRequiredFieldsWithSingleRole(): void
    {
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Reader')
            ->buildObject();

        $result = RequiredUserHeaders::validateRowHasAllRequiredFields($row);

        $this->assertTrue($result);
    }
}
