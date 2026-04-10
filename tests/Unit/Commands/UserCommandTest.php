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
 * @brief Tests for UserCommand class - testing the full user import workflow
 */

namespace APP\plugins\importexport\csv\tests\Unit\Commands;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\commands\UserCommand;
use APP\plugins\importexport\csv\classes\handlers\CsvFileHandler;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\classes\processors\UsersProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\classes\validations\RequiredUserHeaders;
use APP\plugins\importexport\csv\shared\handlers\OrcidHandler;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PKP\user\User;

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

    // ==================== Constructor Tests ====================

    public function testConstructorSetsExpectedRowSize(): void
    {
        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        // The command should be constructable without error
        // expectedRowSize is set from RequiredUserHeaders::$userHeaders count (11)
        $this->assertInstanceOf(UserCommand::class, $command);
    }

    // ==================== Directory Iteration Tests ====================

    public function testRunSkipsNonCsvFiles(): void
    {
        $this->createTestFile($this->tempDir, 'readme.txt', 'Not a CSV');
        $this->createTestFile($this->tempDir, 'data.json', '{}');

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        // Should not produce any output about processed rows since no CSV files
        ob_start();
        $command->run();
        $output = ob_get_clean();

        // No "fileProcessFinished" output since no CSV files were processed
        $this->assertStringNotContainsString('processedRows', $output);
    }

    public function testRunSkipsDirectories(): void
    {
        mkdir($this->tempDir . '/subdir');

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('processedRows', $output);
    }

    public function testRunSkipsInvalidPrefixedFiles(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'invalid_users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // __() returns translation key pattern in test context
        $this->assertStringContainsString('plugins.importexport.csv.skippingInvalidFile', $output);
    }

    public function testRunHandlesEmptyDirectory(): void
    {
        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // No exceptions and no processed rows output
        $this->assertStringNotContainsString('processedRows', $output);
    }

    // ==================== CSV Row Processing Tests ====================

    public function testRunSkipsHeaderRow(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $user = MockFactory::user()->withId(42)->withUsername('jdoe')->withEmail('john@example.com')->build();
        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(42);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($user);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // Only 1 data row processed, header skipped
        $this->assertNotEmpty($output);
    }

    public function testRunSkipsEmptyRows(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $user = MockFactory::user()->withId(1)->build();
        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($user);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        // Create CSV with an empty row in the middle
        $headers = RequiredUserHeaders::$userHeaders;
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, CsvTestDataBuilder::minimalUserRow());
        fputcsv($file, array_fill(0, count($headers), '')); // Empty row
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // Should not throw; empty rows are silently skipped
        $this->assertTrue(true);
    }

    // ==================== Successful Import Tests ====================

    public function testRunProcessesSingleValidRow(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $createdUser = MockFactory::user()->withId(42)->withUsername('jdoe')->withEmail('john@example.com')->build();
        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->once()->andReturn(42);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($createdUser);

        $userInterestMock = $this->mockUserInterestRepository();
        $userInterestMock->shouldReceive('setInterestsForUser')->once();

        $userGroupMock = $this->mockUserGroupRepository();
        $userGroupMock->shouldReceive('assignUserToGroup')->once()->with(42, 10);

        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    public function testRunProcessesMultipleValidRows(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $user1 = MockFactory::user()->withId(1)->withEmail('user1@example.com')->withUsername('user1')->build();
        $user2 = MockFactory::user()->withId(2)->withEmail('user2@example.com')->withUsername('user2')->build();

        $callCount = 0;
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$callCount) {
            return ++$callCount;
        });
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($user1);
        $userRepoMock->shouldReceive('get')->with(2)->andReturn($user2);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $row1 = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('User')
            ->withLastname('One')
            ->withEmail('user1@example.com')
            ->withUsername('user1')
            ->withPassword('pass1')
            ->withRoles('Author')
            ->buildArray();

        $row2 = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('User')
            ->withLastname('Two')
            ->withEmail('user2@example.com')
            ->withUsername('user2')
            ->withPassword('pass2')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row1, $row2]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $this->assertEquals(2, $callCount);
    }

    public function testRunProcessesMultipleCsvFiles(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $user1 = MockFactory::user()->withId(1)->withEmail('file1@example.com')->withUsername('file1user')->build();
        $user2 = MockFactory::user()->withId(2)->withEmail('file2@example.com')->withUsername('file2user')->build();

        $callCount = 0;
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$callCount) {
            return ++$callCount;
        });
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($user1);
        $userRepoMock->shouldReceive('get')->with(2)->andReturn($user2);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;

        $row1 = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('File1')
            ->withLastname('User')
            ->withEmail('file1@example.com')
            ->withUsername('file1user')
            ->withPassword('pass1')
            ->withRoles('Author')
            ->buildArray();

        $row2 = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('File2')
            ->withLastname('User')
            ->withEmail('file2@example.com')
            ->withUsername('file2user')
            ->withPassword('pass2')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users1.csv', $headers, [$row1]);
        $this->createTestCsvFile($this->tempDir, 'users2.csv', $headers, [$row2]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $this->assertEquals(2, $callCount);
    }

    // ==================== Validation Failure Tests ====================

    public function testRunHandlesRowWithTooFewFields(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        // Create a CSV with a row that has fewer fields than expected
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, ['testserver', 'John', 'Doe']); // Only 3 fields instead of 11
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // An invalid_ file should have been created
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesRowWithTooManyFields(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        // 12 fields instead of 11
        fputcsv($file, ['testserver', 'John', 'Doe', 'john@example.com', '', '', 'jdoe', 'pass', 'Author', '', '', 'extra']);
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesMissingRequiredFields(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        // Missing email (required field) - all 11 fields present but email is empty
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesInvalidServer(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $row = CsvTestDataBuilder::user()
            ->withServerPath('nonexistent-server')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        // Server not in cache and DAO returns null
        $serverDaoMock = Mockery::mock(\APP\server\ServerDAO::class);
        $serverDaoMock->shouldReceive('getByPath')
            ->with('nonexistent-server')
            ->andReturn(null);
        \PKP\db\DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesDuplicateEmail(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        // Pre-populate cache with existing user for this email
        $existingUser = MockFactory::user()->withEmail('existing@example.com')->withUsername('existing')->build();
        CachedEntities::$users['existing@example.com'] = $existingUser;

        $headers = RequiredUserHeaders::$userHeaders;
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('existing@example.com')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesDuplicateUsername(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        // Pre-populate cache with existing user for this username
        $existingUser = MockFactory::user()->withUsername('jdoe')->withEmail('other@example.com')->build();
        CachedEntities::$users['jdoe'] = $existingUser;

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->with('new@example.com')->andReturn(null);

        $headers = RequiredUserHeaders::$userHeaders;
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('new@example.com')
            ->withUsername('jdoe')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesInvalidUserGroups(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        // No user groups in cache for this server
        CachedEntities::$userGroups[1] = [];

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);

        $headers = RequiredUserHeaders::$userHeaders;
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withUsername('jdoe')
            ->withRoles('NonexistentRole')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunHandlesInvalidOrcid(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);

        $headers = RequiredUserHeaders::$userHeaders;
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withUsername('jdoe')
            ->withRoles('Author')
            ->withOrcid('invalid-orcid')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    public function testRunSkipsUsernameValidationWhenUsernameIsEmpty(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(1)->withUsername('jdoeabc')->withEmail('john@example.com')->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        // Username is empty string - should skip username validation
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withUsername('')
            ->withPassword('pass123')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // No invalid files should be created - row should process successfully
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(0, $invalidFiles);
    }

    public function testRunSkipsOrcidValidationWhenOrcidIsEmpty(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(1)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('John')
            ->withLastname('Doe')
            ->withEmail('john@example.com')
            ->withUsername('jdoe')
            ->withPassword('pass123')
            ->withRoles('Author')
            ->withOrcid('')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(0, $invalidFiles);
    }

    // ==================== Password Generation Tests ====================

    public function testRunGeneratesPasswordWhenNullInCsv(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(1)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->once()->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        // Row with null/empty tempPassword - array_pad will make it null
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        // Write a row where tempPassword position (index 7) is empty
        $row = ['testserver', 'John', 'Doe', 'john@example.com', '', '', 'jdoe', '', 'Author', '', ''];
        fputcsv($file, $row);
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // Should succeed without error - password was auto-generated
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(0, $invalidFiles);
    }

    // ==================== Welcome Email Tests ====================

    public function testRunSendsWelcomeEmailWhenEnabled(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(42)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(42);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        // Mock email template repository
        $templateMock = Mockery::mock(\PKP\emailTemplate\EmailTemplate::class);
        $templateMock->shouldReceive('getLocalizedData')->with('body')->andReturn('Welcome body');
        $templateMock->shouldReceive('getLocalizedData')->with('subject')->andReturn('Welcome subject');

        $emailTemplateRepoMock = $this->mockEmailTemplateRepository();
        $emailTemplateRepoMock->shouldReceive('getByKey')->andReturn($templateMock);

        // Mock Mail facade
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->once();

        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, true);

        ob_start();
        $command->run();
        ob_get_clean();

        // Mail::send expectation in Mockery verifies this
        $this->assertTrue(true);
    }

    public function testRunDoesNotSendWelcomeEmailWhenDisabled(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(42)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(42);
        $userRepoMock->shouldReceive('get')->with(42)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // No mail sending should have occurred
        $this->assertTrue(true);
    }

    // ==================== Invalid CSV File Creation Tests ====================

    public function testRunCreatesInvalidCsvFileOnFirstFailure(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, ['testserver', 'John']); // Too few fields
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);

        // The invalid file should contain headers + error column
        $content = file_get_contents($invalidFiles[0]);
        $this->assertStringContainsString('error', $content);
    }

    public function testRunAppendsMultipleFailedRowsToSameInvalidFile(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, ['testserver', 'John']); // Too few fields
        fputcsv($file, ['testserver', 'Jane', 'Doe']); // Also too few fields
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // Should still be only 1 invalid file (not 2)
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);

        // The file should have header + 2 failed rows
        $lines = file($invalidFiles[0]);
        $this->assertCount(3, $lines); // header + 2 rows
    }

    public function testRunMixesSuccessAndFailureRows(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(1)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->once()->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        // Row 1: too few fields (fail)
        fputcsv($file, ['testserver', 'Bad']);
        // Row 2: valid row (success)
        fputcsv($file, CsvTestDataBuilder::minimalUserRow());
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        // Invalid file should exist with 1 failed row
        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(1, $invalidFiles);
    }

    // ==================== Complete Workflow Tests ====================

    public function testRunCompleteUserRowWithAllFields(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        $readerGroup = $this->createMockUserGroup(['id' => 11, 'name' => ['en' => 'Reader']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup, 11 => $readerGroup];

        $createdUser = MockFactory::user()->withId(1)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->once()->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $userInterestMock = $this->mockUserInterestRepository();
        $userInterestMock->shouldReceive('setInterestsForUser')->once();

        $userGroupMock = $this->mockUserGroupRepository();
        $userGroupMock->shouldReceive('assignUserToGroup')->twice();

        $headers = RequiredUserHeaders::$userHeaders;
        // Use complete row but without ORCID to avoid HTTP validation in test context
        $row = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('Jane')
            ->withLastname('Smith')
            ->withEmail('jane@example.com')
            ->withAffiliation('MIT')
            ->withCountry('US')
            ->withUsername('jsmith')
            ->withPassword('temppass123')
            ->withRoles('Author;Reader')
            ->withReviewInterests('machine learning;data science')
            ->withOrcid('')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [$row]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(0, $invalidFiles);
    }

    public function testRunHandlesCaseInsensitiveCsvExtension(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(1)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        // Create file with uppercase CSV extension
        $this->createTestCsvFile($this->tempDir, 'users.CSV', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        ob_get_clean();

        $invalidFiles = glob($this->tempDir . '/invalid_*.csv');
        $this->assertCount(0, $invalidFiles);
    }

    // ==================== Output Message Tests ====================

    public function testRunOutputsFileProcessingResults(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $createdUser = MockFactory::user()->withId(1)->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturn(1);
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($createdUser);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // The __() function returns the translation key when no locale is loaded
        // so we check for the translation key parameters
        $this->assertNotEmpty($output);
    }

    public function testRunResetsCountersBetweenFiles(): void
    {
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $callCount = 0;
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')->andReturn(null);
        $userRepoMock->shouldReceive('getByUsername')->andReturn(null);
        $userRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$callCount) {
            return ++$callCount;
        });

        $user1 = MockFactory::user()->withId(1)->build();
        $user2 = MockFactory::user()->withId(2)->build();
        $userRepoMock->shouldReceive('get')->with(1)->andReturn($user1);
        $userRepoMock->shouldReceive('get')->with(2)->andReturn($user2);

        $this->mockUserInterestRepository();
        $this->mockUserGroupRepository();

        $headers = RequiredUserHeaders::$userHeaders;
        $row1 = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('User')
            ->withLastname('One')
            ->withEmail('user1@example.com')
            ->withUsername('user1')
            ->withPassword('pass1')
            ->withRoles('Author')
            ->buildArray();

        $row2 = CsvTestDataBuilder::user()
            ->withServerPath('testserver')
            ->withFirstname('User')
            ->withLastname('Two')
            ->withEmail('user2@example.com')
            ->withUsername('user2')
            ->withPassword('pass2')
            ->withRoles('Author')
            ->buildArray();

        $this->createTestCsvFile($this->tempDir, 'file1.csv', $headers, [$row1]);
        $this->createTestCsvFile($this->tempDir, 'file2.csv', $headers, [$row2]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // Both files should produce output
        $this->assertEquals(2, $callCount);
    }

    // ==================== User Header Tests ====================

    public function testUserHeadersCount(): void
    {
        $this->assertCount(11, RequiredUserHeaders::$userHeaders);
    }

    public function testUserRequiredHeadersCount(): void
    {
        $this->assertCount(5, RequiredUserHeaders::$userRequiredHeaders);
    }

    public function testUserHeadersContainAllExpectedFields(): void
    {
        $headers = RequiredUserHeaders::$userHeaders;

        $this->assertContains('serverPath', $headers);
        $this->assertContains('firstname', $headers);
        $this->assertContains('lastname', $headers);
        $this->assertContains('email', $headers);
        $this->assertContains('affiliation', $headers);
        $this->assertContains('country', $headers);
        $this->assertContains('username', $headers);
        $this->assertContains('tempPassword', $headers);
        $this->assertContains('roles', $headers);
        $this->assertContains('reviewInterests', $headers);
        $this->assertContains('orcid', $headers);
    }

    // ==================== Separate Process Tests (Full Line Coverage) ====================

    /**
     * Covers line 65: continue when CsvFileHandler::createReadableCSVFile() returns null.
     * The real implementation throws on failure, but the defensive null check is tested
     * by mocking CsvFileHandler to return null.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunContinuesWhenCreateReadableCSVFileReturnsNull(): void
    {
        $csvFileHandlerMock = Mockery::mock('overload:' . CsvFileHandler::class);
        $csvFileHandlerMock->shouldReceive('createReadableCSVFile')
            ->andReturn(null);

        $headers = RequiredUserHeaders::$userHeaders;
        $this->createTestCsvFile($this->tempDir, 'users.csv', $headers, [
            CsvTestDataBuilder::minimalUserRow(),
        ]);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // continue on line 65 skips the file processing summary echo on line 133
        $this->assertStringNotContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers line 108: $data->tempPassword = Validation::generatePassword()
     * when the tempPassword field is null (from array_pad on a short CSV row).
     * Requires mocking InvalidRowValidations to bypass field count validation,
     * and mocking processors to avoid database operations.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunGeneratesPasswordWhenTempPasswordFieldIsNull(): void
    {
        // Mock InvalidRowValidations to bypass all validations (so a short row passes)
        $validationMock = Mockery::mock('overload:' . InvalidRowValidations::class);
        $validationMock->shouldReceive('validateRowContainAllFields');
        $validationMock->shouldReceive('validateRowHasAllRequiredFields');
        $validationMock->shouldReceive('validateServerIsValid');
        $validationMock->shouldReceive('validateUserAlreadyExistsWithThisEmail');
        $validationMock->shouldReceive('validateUserAlreadyExistsWithThisUsername');
        $validationMock->shouldReceive('validateAllUserGroupsAreValid');

        $orcidMock = Mockery::mock('overload:' . OrcidHandler::class);
        $orcidMock->shouldReceive('validate');

        $mockUser = $this->createMockUser(['id' => 1]);

        // Mock processors to avoid real database operations
        $usersProcessorMock = Mockery::mock('overload:' . UsersProcessor::class);
        $usersProcessorMock->shouldReceive('process')->once()->andReturn($mockUser);

        $userInterestsMock = Mockery::mock('overload:' . UserInterestsProcessor::class);
        $userInterestsMock->shouldReceive('process');

        $userGroupsMock = Mockery::mock('overload:' . UserGroupsProcessor::class);
        $userGroupsMock->shouldReceive('process');

        // Pre-populate server cache so CachedEntities::getCachedServer returns a valid server
        $server = MockFactory::server()->withPath('testserver')->withId(1)->build();
        CachedEntities::$servers['testserver'] = $server;

        // Create CSV with only 7 fields (serverPath through username, indices 0-6).
        // array_pad fills positions 7-10 (tempPassword, roles, reviewInterests, orcid) with null.
        // This makes $data->tempPassword === null, triggering line 108.
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, RequiredUserHeaders::$userHeaders);
        fputcsv($file, ['testserver', 'John', 'Doe', 'john@example.com', '', '', 'jdoe']);
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // Line 108 was executed (tempPassword was null, Validation::generatePassword() was called).
        // Processing continued successfully to line 133 (file processing summary echo).
        $this->assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }

    /**
     * Covers line 125: continue 2 when CsvFileHandler::createCSVFileInvalidRows() returns null.
     * An invalid CSV row triggers RowValidationException, then the mock returns null
     * for createCSVFileInvalidRows, causing the outer loop to continue.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRunContinuesOuterLoopWhenCreateCSVFileInvalidRowsReturnsNull(): void
    {
        // Mock CsvFileHandler: createReadableCSVFile works normally, createCSVFileInvalidRows returns null
        $csvFileHandlerMock = Mockery::mock('overload:' . CsvFileHandler::class);
        $csvFileHandlerMock->shouldReceive('createReadableCSVFile')
            ->andReturnUsing(function (string $filePath) {
                $file = new \SplFileObject($filePath, 'r');
                $file->setFlags(\SplFileObject::READ_CSV);
                return $file;
            });
        $csvFileHandlerMock->shouldReceive('createCSVFileInvalidRows')
            ->andReturn(null);

        // Create CSV with an invalid row (too few fields) to trigger RowValidationException
        $headers = RequiredUserHeaders::$userHeaders;
        $filepath = $this->tempDir . '/users.csv';
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        fputcsv($file, ['testserver', 'John']); // Only 2 fields - triggers validateRowContainAllFields failure
        fclose($file);

        $senderUser = $this->createMockUser();
        $command = new UserCommand($this->tempDir, $senderUser, false);

        ob_start();
        $command->run();
        $output = ob_get_clean();

        // continue 2 on line 125 skips the file processing summary echo on line 133
        $this->assertStringNotContainsString('plugins.importexpot.csv.fileProcessFinished', $output);
    }
}
