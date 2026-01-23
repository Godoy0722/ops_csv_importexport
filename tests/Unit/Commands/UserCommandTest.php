<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Commands/UserCommandTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommandTest
 *
 * @brief Tests for UserCommand class - testing CSV data handling logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Commands;

use APP\plugins\importexport\csv\classes\commands\UserCommand;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UserCommand::class)]
class UserCommandTest extends BaseTestCase
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

    // ==================== User Header Tests ====================

    public function testUserHeadersCount(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->assertCount(11, $headers);
    }

    public function testUserRequiredHeadersCount(): void
    {
        $requiredHeaders = RequiredUserHeaders::$userRequiredHeaders;

        $this->assertCount(5, $requiredHeaders);
    }

    public function testUserHeadersContainRequiredFields(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->assertContains('serverPath', $headers);
        $this->assertContains('firstname', $headers);
        $this->assertContains('lastname', $headers);
        $this->assertContains('email', $headers);
        $this->assertContains('roles', $headers);
    }

    public function testUserHeadersContainOptionalFields(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->assertContains('affiliation', $headers);
        $this->assertContains('country', $headers);
        $this->assertContains('username', $headers);
        $this->assertContains('tempPassword', $headers);
        $this->assertContains('reviewInterests', $headers);
        $this->assertContains('orcid', $headers);
    }

    // ==================== CSV File Handling Tests ====================

    public function testCsvFileCanBeCreated(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $rows = [CsvTestDataBuilder::minimalUserRow()];

        $filepath = $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, $rows);

        $this->assertFileExists($filepath);
    }

    public function testCsvFileHasCorrectContent(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $rows = [CsvTestDataBuilder::minimalUserRow()];

        $filepath = $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, $rows);
        $content = file_get_contents($filepath);

        $this->assertStringContainsString('serverPath', $content);
        $this->assertStringContainsString('testserver', $content);
    }

    // ==================== User Data Parsing Tests ====================

    public function testMinimalUserRowFormat(): void
    {
        $row = CsvTestDataBuilder::minimalUserRow();

        // Should have values for all header positions
        $this->assertCount(count(RequiredUserHeaders::$userHeaders), $row);
    }

    public function testCompleteUserRowFormat(): void
    {
        $row = CsvTestDataBuilder::completeUserRow();

        $this->assertCount(count(RequiredUserHeaders::$userHeaders), $row);
    }

    public function testUserDataObjectCreation(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            ->buildObject();

        $this->assertEquals('testserver', $data->serverPath);
        $this->assertEquals('John', $data->firstname);
        $this->assertEquals('Doe', $data->lastname);
        $this->assertEquals('john@example.com', $data->email);
        $this->assertEquals('Author', $data->roles);
    }

    // ==================== Role Validation Tests ====================

    public function testSingleRoleParsing(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withRoles('Author')
            ->buildObject();

        $roles = array_map('trim', explode(';', $data->roles));

        $this->assertCount(1, $roles);
        $this->assertEquals('Author', $roles[0]);
    }

    public function testMultipleRolesParsing(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withRoles('Author;Reader;Reviewer')
            ->buildObject();

        $roles = array_map('trim', explode(';', $data->roles));

        $this->assertCount(3, $roles);
    }

    // ==================== ORCID Field Tests ====================

    public function testOrcidFieldCanBeEmpty(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withOrcid('')
            ->buildObject();

        $this->assertTrue(empty($data->orcid));
    }

    public function testOrcidFieldCanHaveValue(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withOrcid('0000-0002-1825-0097')
            ->buildObject();

        $this->assertEquals('0000-0002-1825-0097', $data->orcid);
    }

    // ==================== Password Field Tests ====================

    public function testPasswordFieldCanBeEmpty(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withPassword('')
            ->buildObject();

        $this->assertTrue(empty($data->tempPassword));
    }

    public function testPasswordFieldCanHaveValue(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withPassword('myPassword123')
            ->buildObject();

        $this->assertEquals('myPassword123', $data->tempPassword);
    }

    // ==================== Affiliation and Country Tests ====================

    public function testAffiliationFieldCanBeEmpty(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withAffiliation('')
            ->buildObject();

        $this->assertTrue(empty($data->affiliation));
    }

    public function testAffiliationWithUnicode(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withAffiliation('Universidade de São Paulo')
            ->buildObject();

        $this->assertEquals('Universidade de São Paulo', $data->affiliation);
    }

    public function testCountryCodeFormat(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withCountry('US')
            ->buildObject();

        $this->assertEquals('US', $data->country);
        $this->assertEquals(2, strlen($data->country));
    }

    // ==================== Multiple CSV Files Tests ====================

    public function testMultipleCsvFilesCanBeCreated(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->createTestCsvFile($this->tempDir, 'users1.csv', $headers, [CsvTestDataBuilder::minimalUserRow()]);
        $this->createTestCsvFile($this->tempDir, 'users2.csv', $headers, [CsvTestDataBuilder::minimalUserRow()]);

        $this->assertFileExists($this->tempDir . '/users1.csv');
        $this->assertFileExists($this->tempDir . '/users2.csv');
    }

    public function testCsvFilesCanBeScanned(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->createTestCsvFile($this->tempDir, 'users1.csv', $headers, [CsvTestDataBuilder::minimalUserRow()]);
        $this->createTestCsvFile($this->tempDir, 'users2.csv', $headers, [CsvTestDataBuilder::minimalUserRow()]);

        $csvFiles = glob($this->tempDir . '/*.csv');

        $this->assertCount(2, $csvFiles);
    }

    // ==================== Non-CSV Files Filtering Tests ====================

    public function testNonCsvFilesAreNotIncluded(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [CsvTestDataBuilder::minimalUserRow()]);
        $this->createTestFile($this->tempDir, 'readme.txt', 'This is a readme');

        $csvFiles = glob($this->tempDir . '/*.csv');
        $txtFiles = glob($this->tempDir . '/*.txt');

        $this->assertCount(1, $csvFiles);
        $this->assertCount(1, $txtFiles);
    }
}
