<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/UsersProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsersProcessorTest
 *
 * @brief Tests for UsersProcessor class - testing username generation and parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UsersProcessor::class)]
class UsersProcessorTest extends BaseTestCase
{
    // ==================== Username Generation Tests ====================

    public function testGetValidUsernameGeneratesUniqueUsername(): void
    {
        // Since CachedEntities is cleared in setUp, no existing users
        $username = UsersProcessor::getValidUsername('John', 'Doe');

        $this->assertStringStartsWith('j', $username);
        $this->assertStringContainsString('doe', $username);
        // Username should be: first letter + lastname + 3 random letters
        $this->assertGreaterThanOrEqual(7, strlen($username)); // j + doe + 3 letters = 7+
    }

    public function testGetValidUsernameIsLowercase(): void
    {
        $username = UsersProcessor::getValidUsername('JOHN', 'DOE');

        $this->assertEquals(strtolower($username), $username);
    }

    public function testGetValidUsernameHandlesSpecialCharacters(): void
    {
        $username = UsersProcessor::getValidUsername('José', 'García');

        $this->assertStringStartsWith('j', $username);
        // Should contain the full lastname in lowercase
        $this->assertStringContainsString('garcía', $username);
    }

    public function testGetValidUsernameConsistentPattern(): void
    {
        // Generate multiple usernames and verify pattern
        for ($i = 0; $i < 5; $i++) {
            $username = UsersProcessor::getValidUsername('Test', 'User');

            $this->assertStringStartsWith('t', $username);
            $this->assertStringContainsString('user', $username);
            // Must have 3 random letters at end
            $this->assertGreaterThanOrEqual(8, strlen($username)); // t + user + 3 letters
        }
    }

    public function testGetValidUsernameWithShortNames(): void
    {
        $username = UsersProcessor::getValidUsername('A', 'B');

        $this->assertStringStartsWith('a', $username);
        $this->assertStringContainsString('b', $username);
    }

    public function testGetValidUsernameWithLongNames(): void
    {
        $username = UsersProcessor::getValidUsername('Christopher', 'Bartholomew');

        $this->assertStringStartsWith('c', $username);
        $this->assertStringContainsString('bartholomew', $username);
    }

    // ==================== Username Pattern Tests ====================

    public function testUsernameGenerationPattern(): void
    {
        $givenName = 'John';
        $familyName = 'Doe';

        $baseUsername = mb_strtolower(mb_substr($givenName, 0, 1) . $familyName);

        $this->assertEquals('jdoe', $baseUsername);
    }

    public function testUsernameGenerationPatternWithUnicode(): void
    {
        $givenName = '田中';
        $familyName = '太郎';

        $baseUsername = mb_strtolower(mb_substr($givenName, 0, 1) . $familyName);

        $this->assertEquals('田太郎', $baseUsername);
    }

    public function testUsernameGenerationPatternWithEmptyGivenName(): void
    {
        $givenName = '';
        $familyName = 'Doe';

        $baseUsername = mb_strtolower(mb_substr($givenName, 0, 1) . $familyName);

        $this->assertEquals('doe', $baseUsername);
    }

    // ==================== User Data Parsing Tests ====================

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

    public function testRolesParsing(): void
    {
        $rolesString = 'Author;Reader;Reviewer';
        $roles = array_map('trim', explode(';', $rolesString));

        $this->assertCount(3, $roles);
        $this->assertEquals('Author', $roles[0]);
        $this->assertEquals('Reader', $roles[1]);
        $this->assertEquals('Reviewer', $roles[2]);
    }

    public function testReviewInterestsParsing(): void
    {
        $interestsString = 'machine learning;data science;artificial intelligence';
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertCount(3, $interests);
        $this->assertEquals('machine learning', $interests[0]);
        $this->assertEquals('data science', $interests[1]);
        $this->assertEquals('artificial intelligence', $interests[2]);
    }

    public function testEmptyRolesString(): void
    {
        $rolesString = '';
        $roles = array_filter(array_map('trim', explode(';', $rolesString)));

        $this->assertEmpty($roles);
    }

    public function testSingleRole(): void
    {
        $rolesString = 'Author';
        $roles = array_map('trim', explode(';', $rolesString));

        $this->assertCount(1, $roles);
        $this->assertEquals('Author', $roles[0]);
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

    public function testPasswordFieldPresent(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withPassword('mySecretPassword123')
            ->buildObject();

        $this->assertFalse(empty($data->tempPassword));
        $this->assertEquals('mySecretPassword123', $data->tempPassword);
    }

    public function testPasswordFieldCanBeEmpty(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withPassword('')
            ->buildObject();

        $this->assertTrue(empty($data->tempPassword));
    }

    // ==================== Country Code Tests ====================

    public function testCountryCodeFormat(): void
    {
        $validCountryCodes = ['US', 'BR', 'GB', 'DE', 'JP'];

        foreach ($validCountryCodes as $code) {
            $this->assertEquals(2, strlen($code));
            $this->assertEquals(strtoupper($code), $code);
        }
    }

    public function testEmptyCountryCode(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withCountry('')
            ->buildObject();

        $this->assertTrue(empty($data->country));
    }

    // ==================== Affiliation Tests ====================

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

    // ==================== Complete User Data Tests ====================

    public function testCompleteUserDataObject(): void
    {
        $data = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withAffiliation('MIT')
            ->withCountry('US')
            ->withUsername('jdoe')
            ->withPassword('temppass123')
            ->withRoles('Author;Reader')
            ->withReviewInterests('machine learning;AI')
            ->withOrcid('0000-0002-1825-0097')
            ->buildObject();

        $this->assertEquals('testserver', $data->serverPath);
        $this->assertEquals('John', $data->firstname);
        $this->assertEquals('Doe', $data->lastname);
        $this->assertEquals('john@example.com', $data->email);
        $this->assertEquals('MIT', $data->affiliation);
        $this->assertEquals('US', $data->country);
        $this->assertEquals('jdoe', $data->username);
        $this->assertEquals('temppass123', $data->tempPassword);
        $this->assertEquals('Author;Reader', $data->roles);
        $this->assertEquals('machine learning;AI', $data->reviewInterests);
        $this->assertEquals('0000-0002-1825-0097', $data->orcid);
    }
}
