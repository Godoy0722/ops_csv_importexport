<?php

/**
 * @file plugins/importexport/csv/tests/BaseTestCase.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseTestCase
 *
 * @brief Base test case class for CSV Import/Export plugin tests
 */

namespace APP\plugins\importexport\csv\tests;

use APP\author\Author;
use APP\publication\Publication;
use APP\section\Section;
use APP\server\Server;
use APP\submission\Submission;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PKP\affiliation\Affiliation;
use PKP\affiliation\Repository as AffiliationRepository;
use PKP\author\DAO as AuthorDAO;
use PKP\author\Repository as AuthorRepository;
use PKP\category\Category;
use PKP\category\DAO as CategoryDAO;
use PKP\category\Repository as CategoryRepository;
use PKP\controlledVocab\Repository as ControlledVocabRepository;
use PKP\galley\DAO as GalleyDAO;
use PKP\galley\Galley;
use PKP\galley\Repository as GalleyRepository;
use PKP\publication\DAO as PublicationDAO;
use PKP\publication\Repository as PublicationRepository;
use PKP\submission\DAO as SubmissionDAO;
use PKP\submission\Repository as SubmissionRepository;
use PKP\submissionFile\DAO as SubmissionFileDAO;
use PKP\submissionFile\Repository as SubmissionFileRepository;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\PKPTestCase;
use PKP\user\DAO as UserDAO;
use PKP\user\Repository as UserRepository;
use PKP\user\User;
use PKP\userGroup\Repository as UserGroupRepository;
use PKP\userGroup\UserGroup;

abstract class BaseTestCase extends PKPTestCase
{
    /**
     * @var array Backup of CachedEntities static properties
     */
    protected array $cachedEntitiesBackup = [];

    /**
     * @var array Backup of CachedDaos static properties
     */
    protected array $cachedDaosBackup = [];

    /**
     * @var array Store container instances to restore later
     */
    protected array $containerBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupCachedStatics();
    }

    protected function tearDown(): void
    {
        $this->restoreCachedStatics();
        $this->restoreContainerInstances();
        parent::tearDown();
        Mockery::close();
    }

    /**
     * Backup container instances before mocking
     */
    protected function backupContainerInstance(string $abstract): void
    {
        if (!isset($this->containerBackup[$abstract])) {
            try {
                $this->containerBackup[$abstract] = app($abstract);
            } catch (\Exception $e) {
                $this->containerBackup[$abstract] = null;
            }
        }
    }

    /**
     * Restore container instances after test
     */
    protected function restoreContainerInstances(): void
    {
        foreach ($this->containerBackup as $abstract => $instance) {
            if ($instance !== null) {
                app()->instance($abstract, $instance);
            }
        }
        $this->containerBackup = [];
    }

    /**
     * Backup static properties from CachedEntities and CachedDaos
     */
    protected function backupCachedStatics(): void
    {
        $cachedEntitiesClass = \APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities::class;
        $cachedDaosClass = \APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos::class;

        // Backup CachedEntities
        $this->cachedEntitiesBackup = [
            'servers' => $cachedEntitiesClass::$servers,
            'userGroupIds' => $cachedEntitiesClass::$userGroupIds,
            'userGroups' => $cachedEntitiesClass::$userGroups,
            'genreIds' => $cachedEntitiesClass::$genreIds,
            'categories' => $cachedEntitiesClass::$categories,
            'sections' => $cachedEntitiesClass::$sections,
            'users' => $cachedEntitiesClass::$users,
        ];

        // Backup CachedDaos
        $this->cachedDaosBackup = [
            'cachedDaos' => $cachedDaosClass::$cachedDaos,
        ];

        // Clear the caches for clean tests
        $cachedEntitiesClass::$servers = [];
        $cachedEntitiesClass::$userGroupIds = [];
        $cachedEntitiesClass::$userGroups = [];
        $cachedEntitiesClass::$genreIds = [];
        $cachedEntitiesClass::$categories = [];
        $cachedEntitiesClass::$sections = [];
        $cachedEntitiesClass::$users = [];
        $cachedDaosClass::$cachedDaos = [];
    }

    /**
     * Restore static properties to CachedEntities and CachedDaos
     */
    protected function restoreCachedStatics(): void
    {
        $cachedEntitiesClass = \APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities::class;
        $cachedDaosClass = \APP\plugins\importexport\csv\classes\cachedAttributes\CachedDaos::class;

        // Restore CachedEntities
        $cachedEntitiesClass::$servers = $this->cachedEntitiesBackup['servers'] ?? [];
        $cachedEntitiesClass::$userGroupIds = $this->cachedEntitiesBackup['userGroupIds'] ?? [];
        $cachedEntitiesClass::$userGroups = $this->cachedEntitiesBackup['userGroups'] ?? [];
        $cachedEntitiesClass::$genreIds = $this->cachedEntitiesBackup['genreIds'] ?? [];
        $cachedEntitiesClass::$categories = $this->cachedEntitiesBackup['categories'] ?? [];
        $cachedEntitiesClass::$sections = $this->cachedEntitiesBackup['sections'] ?? [];
        $cachedEntitiesClass::$users = $this->cachedEntitiesBackup['users'] ?? [];

        // Restore CachedDaos
        $cachedDaosClass::$cachedDaos = $this->cachedDaosBackup['cachedDaos'] ?? [];
    }

    // ==================== Repository Mock Helpers ====================

    /**
     * Create and register a mock Author Repository
     */
    protected function mockAuthorRepository(): MockInterface
    {
        $this->backupContainerInstance(AuthorRepository::class);

        $authorDaoMock = Mockery::mock(AuthorDAO::class)->makePartial();
        $authorDaoMock->shouldReceive('update')->andReturn(true);

        $mock = Mockery::mock(AuthorRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Author());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->shouldReceive('edit')->andReturn(true);
        $mock->dao = $authorDaoMock;

        app()->instance(AuthorRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock Affiliation Repository
     */
    protected function mockAffiliationRepository(): MockInterface
    {
        $this->backupContainerInstance(AffiliationRepository::class);

        $mock = Mockery::mock(AffiliationRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Affiliation());

        app()->instance(AffiliationRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock User Repository
     */
    protected function mockUserRepository(): MockInterface
    {
        $this->backupContainerInstance(UserRepository::class);

        $userDaoMock = Mockery::mock(UserDAO::class)->makePartial();
        $userDaoMock->shouldReceive('getByEmail')->andReturn(null);
        $userDaoMock->shouldReceive('getByUsername')->andReturn(null);
        $userDaoMock->shouldReceive('insertObject')->andReturn(1);
        $userDaoMock->shouldReceive('updateObject')->andReturn(true);

        $mock = Mockery::mock(UserRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new User());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->shouldReceive('edit')->andReturn(true);
        $mock->dao = $userDaoMock;

        app()->instance(UserRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock Publication Repository
     */
    protected function mockPublicationRepository(): MockInterface
    {
        $this->backupContainerInstance(PublicationRepository::class);

        $publicationDaoMock = Mockery::mock(PublicationDAO::class)->makePartial();
        $publicationDaoMock->shouldReceive('update')->andReturn(true);

        $mock = Mockery::mock(PublicationRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Publication());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->shouldReceive('edit')->andReturn(true);
        $mock->dao = $publicationDaoMock;

        app()->instance(PublicationRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock Submission Repository
     */
    protected function mockSubmissionRepository(): MockInterface
    {
        $this->backupContainerInstance(SubmissionRepository::class);

        $submissionDaoMock = Mockery::mock(SubmissionDAO::class)->makePartial();
        $submissionDaoMock->shouldReceive('update')->andReturn(true);

        $mock = Mockery::mock(SubmissionRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Submission());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->shouldReceive('edit')->andReturn(true);
        $mock->dao = $submissionDaoMock;

        app()->instance(SubmissionRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock Category Repository
     */
    protected function mockCategoryRepository(): MockInterface
    {
        $this->backupContainerInstance(CategoryRepository::class);

        $categoryDaoMock = Mockery::mock(CategoryDAO::class)->makePartial();
        $categoryDaoMock->shouldReceive('insertObject')->andReturn(1);

        $mock = Mockery::mock(CategoryRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Category());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->dao = $categoryDaoMock;

        app()->instance(CategoryRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock Galley Repository
     */
    protected function mockGalleyRepository(): MockInterface
    {
        $this->backupContainerInstance(GalleyRepository::class);

        $galleyDaoMock = Mockery::mock(GalleyDAO::class)->makePartial();
        $galleyDaoMock->shouldReceive('insert')->andReturn(1);

        $mock = Mockery::mock(GalleyRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Galley());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->dao = $galleyDaoMock;

        app()->instance(GalleyRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock SubmissionFile Repository
     */
    protected function mockSubmissionFileRepository(): MockInterface
    {
        $this->backupContainerInstance(SubmissionFileRepository::class);

        $submissionFileDaoMock = Mockery::mock(SubmissionFileDAO::class)->makePartial();
        $submissionFileDaoMock->shouldReceive('insert')->andReturn(1);

        $mock = Mockery::mock(SubmissionFileRepository::class)->makePartial();
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new SubmissionFile());
        $mock->shouldReceive('add')->andReturn(1);
        $mock->shouldReceive('edit')->andReturn(true);
        $mock->dao = $submissionFileDaoMock;

        app()->instance(SubmissionFileRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock UserGroup Repository
     */
    protected function mockUserGroupRepository(): MockInterface
    {
        $this->backupContainerInstance(UserGroupRepository::class);

        $mock = Mockery::mock(UserGroupRepository::class)->makePartial();
        $mock->shouldReceive('assignUserToGroup')->andReturn(null);
        $mock->shouldReceive('userInGroup')->andReturn(false);

        app()->instance(UserGroupRepository::class, $mock);
        return $mock;
    }

    /**
     * Create and register a mock ControlledVocab Repository
     */
    protected function mockControlledVocabRepository(): MockInterface
    {
        $this->backupContainerInstance(ControlledVocabRepository::class);

        $mock = Mockery::mock(ControlledVocabRepository::class)->makePartial();
        $mock->shouldReceive('insertBySymbolic')->andReturn(true);
        $mock->shouldReceive('getBySymbolic')->andReturn([]);

        app()->instance(ControlledVocabRepository::class, $mock);
        return $mock;
    }

    // ==================== Entity Creation Helpers ====================

    /**
     * Create a mock User object
     */
    protected function createMockUser(array $data = []): User
    {
        $user = new User();
        $user->setId($data['id'] ?? 1);
        $user->setUsername($data['username'] ?? 'testuser');
        $user->setEmail($data['email'] ?? 'test@example.com');
        $user->setGivenName($data['givenName'] ?? 'Test', $data['locale'] ?? 'en');
        $user->setFamilyName($data['familyName'] ?? 'User', $data['locale'] ?? 'en');

        if (isset($data['affiliation'])) {
            $user->setAffiliation($data['affiliation'], $data['locale'] ?? 'en');
        }
        if (isset($data['country'])) {
            $user->setCountry($data['country']);
        }
        if (isset($data['orcid'])) {
            $user->setOrcid($data['orcid']);
        }

        return $user;
    }

    /**
     * Create a mock Server object
     */
    protected function createMockServer(array $data = []): Server|MockObject
    {
        /** @var Server|MockObject */
        $server = $this->getMockBuilder(Server::class)
            ->onlyMethods(['getSupportedSubmissionLocales', 'getPrimaryLocale', 'getContactEmail'])
            ->getMock();

        $server->setId($data['id'] ?? 1);
        $server->setPath($data['path'] ?? 'testserver');
        $server->setName($data['name'] ?? 'Test Server', $data['locale'] ?? 'en');

        $supportedLocales = $data['supportedLocales'] ?? ['en'];
        $primaryLocale = $data['primaryLocale'] ?? 'en';
        $contactEmail = $data['contactEmail'] ?? 'contact@example.com';

        $server->method('getSupportedSubmissionLocales')->willReturn($supportedLocales);
        $server->method('getPrimaryLocale')->willReturn($primaryLocale);
        $server->method('getContactEmail')->willReturn($contactEmail);

        return $server;
    }

    /**
     * Create a mock Publication object
     */
    protected function createMockPublication(array $data = []): Publication
    {
        $publication = new Publication();
        $publication->setId($data['id'] ?? 1);
        $publication->setData('submissionId', $data['submissionId'] ?? 1);
        $publication->setData('status', $data['status'] ?? Submission::STATUS_PUBLISHED);
        $publication->setData('version', $data['version'] ?? 1);

        if (isset($data['title'])) {
            $publication->setData('title', $data['title'], $data['locale'] ?? 'en');
        }
        if (isset($data['abstract'])) {
            $publication->setData('abstract', $data['abstract'], $data['locale'] ?? 'en');
        }
        if (isset($data['datePublished'])) {
            $publication->setData('datePublished', $data['datePublished']);
        }
        if (isset($data['sectionId'])) {
            $publication->setData('sectionId', $data['sectionId']);
        }
        if (isset($data['authors'])) {
            $publication->setData('authors', $data['authors']);
        }
        if (isset($data['keywords'])) {
            $publication->setData('keywords', $data['keywords']);
        }
        if (isset($data['subjects'])) {
            $publication->setData('subjects', $data['subjects']);
        }

        return $publication;
    }

    /**
     * Create a mock Submission object
     */
    protected function createMockSubmission(array $data = []): Submission|MockObject
    {
        /** @var Submission|MockObject */
        $submission = $this->getMockBuilder(Submission::class)
            ->onlyMethods(['getCurrentPublication'])
            ->getMock();

        $submission->setId($data['id'] ?? 1);
        $submission->setData('contextId', $data['contextId'] ?? 1);
        $submission->setData('locale', $data['locale'] ?? 'en');
        $submission->setData('status', $data['status'] ?? Submission::STATUS_PUBLISHED);

        if (isset($data['currentPublication'])) {
            $submission->method('getCurrentPublication')->willReturn($data['currentPublication']);
        }

        return $submission;
    }

    /**
     * Create a mock Author object
     */
    protected function createMockAuthor(array $data = []): Author
    {
        $author = new Author();
        $author->setId($data['id'] ?? 1);
        $author->setData('publicationId', $data['publicationId'] ?? 1);
        $author->setSubmissionId($data['submissionId'] ?? 1);
        $author->setGivenName($data['givenName'] ?? 'John', $data['locale'] ?? 'en');
        $author->setFamilyName($data['familyName'] ?? 'Doe', $data['locale'] ?? 'en');
        $author->setEmail($data['email'] ?? 'author@example.com');

        if (isset($data['orcid'])) {
            $author->setOrcid($data['orcid']);
        }

        return $author;
    }

    /**
     * Create a mock Section object
     */
    protected function createMockSection(array $data = []): Section
    {
        $section = new Section();
        $section->setId($data['id'] ?? 1);
        $section->setContextId($data['contextId'] ?? 1);
        $section->setTitle($data['title'] ?? 'Test Section', $data['locale'] ?? 'en');
        $section->setAbbrev($data['abbrev'] ?? 'TS', $data['locale'] ?? 'en');

        return $section;
    }

    /**
     * Create a mock Category object
     */
    protected function createMockCategory(array $data = []): Category
    {
        $category = new Category();
        $category->setId($data['id'] ?? 1);
        $category->setContextId($data['contextId'] ?? 1);
        $category->setTitle($data['title'] ?? 'Test Category', $data['locale'] ?? 'en');
        $category->setPath($data['path'] ?? 'test-category');

        return $category;
    }

    /**
     * Create a mock UserGroup object
     */
    protected function createMockUserGroup(array $data = []): UserGroup
    {
        $userGroup = new UserGroup();
        $userGroup->id = $data['id'] ?? 1;
        $userGroup->contextId = $data['contextId'] ?? 1;
        $userGroup->roleId = $data['roleId'] ?? \PKP\security\Role::ROLE_ID_AUTHOR;
        $userGroup->name = $data['name'] ?? ['en' => 'Author'];

        return $userGroup;
    }

    /**
     * Create a mock Galley object
     */
    protected function createMockGalley(array $data = []): Galley
    {
        $galley = new Galley();
        $galley->setId($data['id'] ?? 1);
        $galley->setData('publicationId', $data['publicationId'] ?? 1);
        $galley->setLabel($data['label'] ?? 'PDF');
        $galley->setLocale($data['locale'] ?? 'en');

        if (isset($data['submissionFileId'])) {
            $galley->setData('submissionFileId', $data['submissionFileId']);
        }

        return $galley;
    }

    // ==================== File System Helpers ====================

    /**
     * Create a temporary directory for test files
     */
    protected function createTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . '/csv_plugin_test_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        return $tempDir;
    }

    /**
     * Clean up a temporary directory
     */
    protected function cleanupTempDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanupTempDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Create a test CSV file
     */
    protected function createTestCsvFile(string $dir, string $filename, array $headers, array $rows): string
    {
        $filepath = $dir . '/' . $filename;
        $file = fopen($filepath, 'w');
        fputcsv($file, $headers);
        foreach ($rows as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        return $filepath;
    }

    /**
     * Create a test file with given content
     */
    protected function createTestFile(string $dir, string $filename, string $content = ''): string
    {
        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, $content);
        return $filepath;
    }

    // ==================== Data Object Helpers ====================

    /**
     * Helper to create a standard preprint data object from array
     */
    protected function createPreprintDataObject(array $data): object
    {
        return (object) array_merge([
            'serverPath' => 'testserver',
            'locale' => 'en',
            'versionIdentifier' => '',
            'version' => '',
            'preprintPrefix' => '',
            'preprintTitle' => 'Test Preprint',
            'preprintSubtitle' => '',
            'preprintAbstract' => 'Test abstract',
            'authors' => 'John,Doe,john@example.com,,Test University',
            'keywords' => '',
            'subjects' => '',
            'coverage' => '',
            'categories' => '',
            'doi' => '',
            'coverImageFilename' => '',
            'coverImageAltText' => '',
            'galleyFilenames' => '',
            'galleyLabels' => '',
            'suppFilenames' => '',
            'suppLabels' => '',
            'suppDescriptions' => '',
            'sectionTitle' => 'Preprints',
            'sectionAbbrev' => 'PRE',
            'datePosted' => '2024-01-15',
            'dateSubmitted' => '',
            'copyrightYear' => '',
            'copyrightHolder' => '',
            'licenseUrl' => '',
            'references' => '',
            'vorDoi' => '',
            'supportingAgencies' => '',
            'username' => '',
            'funders' => '',
        ], $data);
    }

    /**
     * Helper to create a standard user data object from array
     */
    protected function createUserDataObject(array $data): object
    {
        return (object) array_merge([
            'serverPath' => 'testserver',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'affiliation' => '',
            'country' => '',
            'username' => 'jdoe',
            'tempPassword' => 'password123',
            'roles' => 'Author',
            'reviewInterests' => '',
            'orcid' => '',
        ], $data);
    }
}
