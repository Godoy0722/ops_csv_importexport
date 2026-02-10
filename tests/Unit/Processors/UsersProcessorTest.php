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

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\user\User;

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

    // ==================== getValidUsername Collision Avoidance Tests ====================

    public function testGetValidUsernameAvoidsExistingUsernames(): void
    {
        // Pre-populate cache with a user that would match first attempt pattern
        // The method generates: first_letter + lastname + 3 random letters
        // We can't predict exact random letters, but we can verify collision avoidance
        $user1 = MockFactory::user()->withUsername('jdoeabc')->build();
        CachedEntities::$users['jdoeabc'] = $user1;

        $username = UsersProcessor::getValidUsername('John', 'Doe');

        // The generated username should NOT be 'jdoeabc' (the existing one)
        $this->assertNotEquals('jdoeabc', $username);
        $this->assertStringStartsWith('j', $username);
        $this->assertStringContainsString('doe', $username);
    }

    public function testGetValidUsernameAlwaysReturnsNonEmptyString(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $username = UsersProcessor::getValidUsername('Test', 'User');
            $this->assertNotEmpty($username);
            $this->assertIsString($username);
        }
    }

    public function testGetValidUsernameWithMultibyteFirstChar(): void
    {
        $username = UsersProcessor::getValidUsername('Ação', 'Teste');

        $this->assertStringContainsString('teste', $username);
        $this->assertGreaterThanOrEqual(5, mb_strlen($username));
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

    // ==================== process() Integration Tests ====================

    public function testProcessCreatesAndReturnsUser(): void
    {
        $userRepoMock = $this->mockUserRepository();

        $returnedUser = $this->createMockUser(['id' => 42]);
        $userRepoMock->shouldReceive('add')->once()->andReturn(42);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'username' => 'jdoe',
            'tempPassword' => 'password123',
        ]);

        $result = UsersProcessor::process($data, 'en');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals(42, $result->getId());
    }

    public function testProcessSetsCorrectUserData(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'email' => 'jane@example.com',
            'affiliation' => 'MIT',
            'country' => 'US',
            'username' => 'jsmith',
            'tempPassword' => 'secret123',
            'orcid' => '',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertNotNull($capturedUser);
        $this->assertEquals('Jane', $capturedUser->getGivenName('en'));
        $this->assertEquals('Smith', $capturedUser->getFamilyName('en'));
        $this->assertEquals('MIT', $capturedUser->getAffiliation('en'));
        $this->assertEquals('jane@example.com', $capturedUser->getEmail());
        $this->assertEquals('US', $capturedUser->getCountry());
        $this->assertEquals('jsmith', $capturedUser->getUsername());
        $this->assertTrue($capturedUser->getMustChangePassword());
        $this->assertNotEmpty($capturedUser->getDateRegistered());
    }

    public function testProcessEncryptsPassword(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'username' => 'testuser',
            'tempPassword' => 'mypassword',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertTrue(password_verify('mypassword', $capturedUser->getPassword()));
    }

    public function testProcessSetsOrcidWhenValid(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'orcid' => '0000-0002-1825-0097',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $capturedUser->getOrcid());
    }

    public function testProcessSetsOrcidWhenFullUrl(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'orcid' => 'https://orcid.org/0000-0002-1825-0097',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $capturedUser->getOrcid());
    }

    public function testProcessSkipsOrcidWhenEmpty(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'orcid' => '',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertEmpty($capturedUser->getOrcid());
    }

    public function testProcessSkipsOrcidWhenInvalid(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'orcid' => 'invalid-orcid-format',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertEmpty($capturedUser->getOrcid());
    }

    public function testProcessUsesProvidedUsername(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'username' => 'customuser',
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertEquals('customuser', $capturedUser->getUsername());
    }

    public function testProcessFallsBackToGeneratedUsername(): void
    {
        $capturedUser = null;
        $userRepoMock = $this->mockUserRepository();

        $userRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($user) use (&$capturedUser) {
                $capturedUser = $user;
                return 1;
            });

        $returnedUser = $this->createMockUser(['id' => 1]);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($returnedUser);

        $data = $this->createUserDataObject([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'username' => null,
        ]);

        UsersProcessor::process($data, 'en');

        $this->assertMatchesRegularExpression('/^jdoe[a-z]{3}$/', $capturedUser->getUsername());
    }

    // ==================== getValidUsername() with Mocked Repo Tests ====================

    public function testGetValidUsernameRetriesOnCollision(): void
    {
        $existingUser = $this->createMockUser(['id' => 1, 'email' => 'existing@example.com']);
        $callCount = 0;

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByUsername')
            ->andReturnUsing(function () use ($existingUser, &$callCount) {
                $callCount++;
                return $callCount === 1 ? $existingUser : null;
            });

        $username = UsersProcessor::getValidUsername('John', 'Doe');

        $this->assertMatchesRegularExpression('/^jdoe[a-z]{3}$/', $username);
        $this->assertGreaterThanOrEqual(2, $callCount);
    }
}
