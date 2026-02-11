<?php

/**
 * @file plugins/importexport/csv/tests/Unit/CachedAttributes/CachedEntitiesTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CachedEntitiesTest
 *
 * @brief Tests for CachedEntities static caching behavior and method logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\CachedAttributes;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use APP\server\ServerDAO;
use Illuminate\Support\LazyCollection;
use Mockery;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\submission\Genre;
use PKP\userGroup\UserGroup;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CachedEntities::class)]
class CachedEntitiesTest extends BaseTestCase
{
    // ==================== Cache Isolation Tests ====================

    public function testAllCachesAreEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$userGroupIds);
        $this->assertEmpty(CachedEntities::$userGroups);
        $this->assertEmpty(CachedEntities::$genreIds);
        $this->assertEmpty(CachedEntities::$supplementaryGenreIds);
        $this->assertEmpty(CachedEntities::$categories);
        $this->assertEmpty(CachedEntities::$sections);
        $this->assertEmpty(CachedEntities::$users);
        $this->assertEmpty(CachedEntities::$subscriptionTypes);
    }

    public function testCacheModificationDoesNotLeakToNextTest(): void
    {
        CachedEntities::$servers['test'] = MockFactory::server()->build();
        CachedEntities::$userGroupIds['test'] = 1;
        CachedEntities::$categories['test'] = MockFactory::category()->build();

        $this->assertNotEmpty(CachedEntities::$servers);
        $this->assertNotEmpty(CachedEntities::$userGroupIds);
        $this->assertNotEmpty(CachedEntities::$categories);
    }

    public function testCacheIsCleanAfterPreviousTestModification(): void
    {
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$userGroupIds);
        $this->assertEmpty(CachedEntities::$categories);
    }

    // ==================== getCachedServer Tests ====================

    public function testGetCachedServerReturnsCachedValue(): void
    {
        $server = MockFactory::server()->withPath('cached-server')->withId(1)->build();
        CachedEntities::$servers['cached-server'] = $server;

        $result = CachedEntities::getCachedServer('cached-server');

        $this->assertSame($server, $result);
    }

    public function testGetCachedServerQueriesDaoOnMiss(): void
    {
        $server = MockFactory::server()->withPath('new-server')->withId(2)->build();

        $serverDaoMock = Mockery::mock(ServerDAO::class);
        $serverDaoMock->shouldReceive('getByPath')
            ->once()
            ->with('new-server')
            ->andReturn($server);

        DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        $result = CachedEntities::getCachedServer('new-server');

        $this->assertSame($server, $result);
        $this->assertSame($server, CachedEntities::$servers['new-server']);
    }

    public function testGetCachedServerReturnsNullWhenDaoReturnsNull(): void
    {
        $serverDaoMock = Mockery::mock(ServerDAO::class);
        $serverDaoMock->shouldReceive('getByPath')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        $result = CachedEntities::getCachedServer('nonexistent');

        $this->assertNull($result);
    }

    public function testGetCachedServerDoesNotQueryDaoOnSubsequentCalls(): void
    {
        $server = MockFactory::server()->withPath('repeat-server')->build();

        $serverDaoMock = Mockery::mock(ServerDAO::class);
        $serverDaoMock->shouldReceive('getByPath')
            ->once()
            ->with('repeat-server')
            ->andReturn($server);

        DAORegistry::registerDAO('ServerDAO', $serverDaoMock);

        CachedEntities::getCachedServer('repeat-server');
        $result = CachedEntities::getCachedServer('repeat-server');

        $this->assertSame($server, $result);
    }

    // ==================== getCachedAuthorUserGroupId Tests ====================

    public function testGetCachedAuthorUserGroupIdReturnsCachedValue(): void
    {
        CachedEntities::$userGroupIds['testserver'] = 42;

        $result = CachedEntities::getCachedAuthorUserGroupId('testserver', 1);

        $this->assertEquals(42, $result);
    }

    public function testGetCachedAuthorUserGroupIdQueriesRepoOnMiss(): void
    {
        $userGroup = MockFactory::userGroup()
            ->withId(10)
            ->withRoleId(Role::ROLE_ID_AUTHOR)
            ->build();

        $collection = new LazyCollection([$userGroup]);

        $userGroupRepoMock = $this->mockUserGroupRepository();
        $userGroupRepoMock->shouldReceive('getByRoleIds')
            ->once()
            ->with([Role::ROLE_ID_AUTHOR], 1)
            ->andReturn($collection);

        $result = CachedEntities::getCachedAuthorUserGroupId('testserver', 1);

        $this->assertEquals(10, $result);
        $this->assertEquals(10, CachedEntities::$userGroupIds['testserver']);
    }

    public function testGetCachedAuthorUserGroupIdReturnsNullWhenNotFound(): void
    {
        $collection = new LazyCollection([]);

        $userGroupRepoMock = $this->mockUserGroupRepository();
        $userGroupRepoMock->shouldReceive('getByRoleIds')
            ->once()
            ->with([Role::ROLE_ID_AUTHOR], 1)
            ->andReturn($collection);

        $result = CachedEntities::getCachedAuthorUserGroupId('missing-server', 1);

        $this->assertNull($result);
    }

    // ==================== getCachedUserByEmail Tests ====================

    public function testGetCachedUserByEmailReturnsCachedValue(): void
    {
        $user = MockFactory::user()->withEmail('cached@example.com')->build();
        CachedEntities::$users['cached@example.com'] = $user;

        $result = CachedEntities::getCachedUserByEmail('cached@example.com');

        $this->assertSame($user, $result);
    }

    public function testGetCachedUserByEmailQueriesRepoOnMiss(): void
    {
        $user = MockFactory::user()
            ->withEmail('new@example.com')
            ->withUsername('newuser')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')
            ->once()
            ->with('new@example.com')
            ->andReturn($user);

        $result = CachedEntities::getCachedUserByEmail('new@example.com');

        $this->assertSame($user, $result);
    }

    public function testGetCachedUserByEmailDualCachesByEmailAndUsername(): void
    {
        $user = MockFactory::user()
            ->withEmail('dual@example.com')
            ->withUsername('dualuser')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')
            ->once()
            ->with('dual@example.com')
            ->andReturn($user);

        CachedEntities::getCachedUserByEmail('dual@example.com');

        $this->assertSame($user, CachedEntities::$users['dual@example.com']);
        $this->assertSame($user, CachedEntities::$users['dualuser']);
    }

    public function testGetCachedUserByEmailCachesNullWhenNotFound(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')
            ->once()
            ->with('missing@example.com')
            ->andReturn(null);

        $result = CachedEntities::getCachedUserByEmail('missing@example.com');

        $this->assertNull($result);
        $this->assertArrayHasKey('missing@example.com', CachedEntities::$users);
        $this->assertNull(CachedEntities::$users['missing@example.com']);
    }

    public function testGetCachedUserByEmailDoesNotDualCacheWhenNotFound(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByEmail')
            ->with('notfound@example.com')
            ->andReturn(null);

        CachedEntities::getCachedUserByEmail('notfound@example.com');

        // Only the email key should exist, no username key since user was null
        $this->assertArrayHasKey('notfound@example.com', CachedEntities::$users);
        $this->assertCount(1, CachedEntities::$users);
    }

    // ==================== getCachedUserByUsername Tests ====================

    public function testGetCachedUserByUsernameReturnsCachedValue(): void
    {
        $user = MockFactory::user()->withUsername('cacheduser')->build();
        CachedEntities::$users['cacheduser'] = $user;

        $result = CachedEntities::getCachedUserByUsername('cacheduser');

        $this->assertSame($user, $result);
    }

    public function testGetCachedUserByUsernameQueriesRepoOnMiss(): void
    {
        $user = MockFactory::user()
            ->withUsername('lookedupuser')
            ->withEmail('looked@example.com')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByUsername')
            ->once()
            ->with('lookedupuser', false)
            ->andReturn($user);

        $result = CachedEntities::getCachedUserByUsername('lookedupuser');

        $this->assertSame($user, $result);
    }

    public function testGetCachedUserByUsernameDualCachesByUsernameAndEmail(): void
    {
        $user = MockFactory::user()
            ->withUsername('dualuser2')
            ->withEmail('dual2@example.com')
            ->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByUsername')
            ->once()
            ->with('dualuser2', false)
            ->andReturn($user);

        CachedEntities::getCachedUserByUsername('dualuser2');

        $this->assertSame($user, CachedEntities::$users['dualuser2']);
        $this->assertSame($user, CachedEntities::$users['dual2@example.com']);
    }

    public function testGetCachedUserByUsernameCachesNullWhenNotFound(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByUsername')
            ->once()
            ->with('nouser', false)
            ->andReturn(null);

        $result = CachedEntities::getCachedUserByUsername('nouser');

        $this->assertNull($result);
        $this->assertArrayHasKey('nouser', CachedEntities::$users);
    }

    public function testGetCachedUserByUsernamePassesAllowDisabledFlag(): void
    {
        $user = MockFactory::user()->withUsername('disableduser')->build();

        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByUsername')
            ->once()
            ->with('disableduser', true)
            ->andReturn($user);

        $result = CachedEntities::getCachedUserByUsername('disableduser', true);

        $this->assertSame($user, $result);
    }

    public function testGetCachedUserByUsernameDoesNotDualCacheWhenNotFound(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('getByUsername')
            ->with('noone', false)
            ->andReturn(null);

        CachedEntities::getCachedUserByUsername('noone');

        $this->assertArrayHasKey('noone', CachedEntities::$users);
        $this->assertCount(1, CachedEntities::$users);
    }

    // ==================== getCachedUserGroupsByServerId Tests ====================

    public function testGetCachedUserGroupsByServerIdReturnsCachedValue(): void
    {
        $group = MockFactory::userGroup()->withId(1)->withName(['en' => 'Author'])->build();
        CachedEntities::$userGroups[1] = [1 => $group];

        $result = CachedEntities::getCachedUserGroupsByServerId(1);

        $this->assertCount(1, $result);
        $this->assertSame($group, $result[1]);
    }

    public function testGetCachedUserGroupsByServerIdIndexesByGroupId(): void
    {
        $group1 = new UserGroup();
        $group1->id = 10;
        $group1->contextId = 5;
        $group1->name = ['en' => 'Author'];

        $group2 = new UserGroup();
        $group2->id = 11;
        $group2->contextId = 5;
        $group2->name = ['en' => 'Reader'];

        // Pre-populate to test the caching structure
        CachedEntities::$userGroups[5] = [10 => $group1, 11 => $group2];

        $result = CachedEntities::getCachedUserGroupsByServerId(5);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(11, $result);
        $this->assertSame($group1, $result[10]);
        $this->assertSame($group2, $result[11]);
    }

    public function testGetCachedUserGroupsByServerIdCachesEmptyArrayWhenNoneFound(): void
    {
        CachedEntities::$userGroups[99] = [];

        $result = CachedEntities::getCachedUserGroupsByServerId(99);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetCachedUserGroupsByServerIdDoesNotReQueryOnSubsequentCalls(): void
    {
        $group = MockFactory::userGroup()->withId(1)->build();
        CachedEntities::$userGroups[1] = [1 => $group];

        $result1 = CachedEntities::getCachedUserGroupsByServerId(1);
        $result2 = CachedEntities::getCachedUserGroupsByServerId(1);

        $this->assertSame($result1, $result2);
    }

    public function testGetCachedUserGroupsByServerIdQueriesModelOnCacheMiss(): void
    {
        $this->beginDatabaseTransaction();

        try {
            $contextId = 99999;

            \Illuminate\Support\Facades\DB::table('user_groups')->insert([
                ['context_id' => $contextId, 'role_id' => Role::ROLE_ID_AUTHOR, 'is_default' => 0, 'show_title' => 0, 'permit_self_registration' => 0, 'permit_metadata_edit' => 0],
                ['context_id' => $contextId, 'role_id' => Role::ROLE_ID_READER, 'is_default' => 0, 'show_title' => 0, 'permit_self_registration' => 0, 'permit_metadata_edit' => 0],
            ]);

            $result = CachedEntities::getCachedUserGroupsByServerId($contextId);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);

            foreach ($result as $id => $userGroup) {
                $this->assertInstanceOf(UserGroup::class, $userGroup);
                $this->assertEquals($id, $userGroup->id);
            }
        } finally {
            $this->rollbackDatabaseTransaction();
        }
    }

    public function testGetCachedUserGroupsByServerIdCachesQueryResult(): void
    {
        $this->beginDatabaseTransaction();

        try {
            $contextId = 99998;

            \Illuminate\Support\Facades\DB::table('user_groups')->insert([
                'context_id' => $contextId, 'role_id' => Role::ROLE_ID_AUTHOR, 'is_default' => 0, 'show_title' => 0, 'permit_self_registration' => 0, 'permit_metadata_edit' => 0,
            ]);

            $result = CachedEntities::getCachedUserGroupsByServerId($contextId);

            $this->assertArrayHasKey($contextId, CachedEntities::$userGroups);
            $this->assertCount(1, CachedEntities::$userGroups[$contextId]);
            $this->assertSame($result, CachedEntities::$userGroups[$contextId]);
        } finally {
            $this->rollbackDatabaseTransaction();
        }
    }

    public function testGetCachedUserGroupsByServerIdReturnsEmptyArrayFromQuery(): void
    {
        $contextId = 99997;

        $result = CachedEntities::getCachedUserGroupsByServerId($contextId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
        $this->assertArrayHasKey($contextId, CachedEntities::$userGroups);
    }

    // ==================== getCachedUserGroupByName Tests ====================

    public function testGetCachedUserGroupByNameFindsMatchingGroup(): void
    {
        $authorGroup = MockFactory::userGroup()->withId(1)->withName(['en' => 'Author'])->build();
        $readerGroup = MockFactory::userGroup()->withId(2)->withName(['en' => 'Reader'])->build();
        CachedEntities::$userGroups[1] = [1 => $authorGroup, 2 => $readerGroup];

        $result = CachedEntities::getCachedUserGroupByName('Author', 1, 'en');

        $this->assertSame($authorGroup, $result);
    }

    public function testGetCachedUserGroupByNameIsCaseInsensitive(): void
    {
        $authorGroup = MockFactory::userGroup()->withId(1)->withName(['en' => 'Author'])->build();
        CachedEntities::$userGroups[1] = [1 => $authorGroup];

        $result = CachedEntities::getCachedUserGroupByName('author', 1, 'en');

        $this->assertSame($authorGroup, $result);
    }

    public function testGetCachedUserGroupByNameMatchesUpperCase(): void
    {
        $authorGroup = MockFactory::userGroup()->withId(1)->withName(['en' => 'Author'])->build();
        CachedEntities::$userGroups[1] = [1 => $authorGroup];

        $result = CachedEntities::getCachedUserGroupByName('AUTHOR', 1, 'en');

        $this->assertSame($authorGroup, $result);
    }

    public function testGetCachedUserGroupByNameReturnsNullWhenNotFound(): void
    {
        $authorGroup = MockFactory::userGroup()->withId(1)->withName(['en' => 'Author'])->build();
        CachedEntities::$userGroups[1] = [1 => $authorGroup];

        $result = CachedEntities::getCachedUserGroupByName('Nonexistent', 1, 'en');

        $this->assertNull($result);
    }

    public function testGetCachedUserGroupByNameReturnsNullWhenNoGroupsForServer(): void
    {
        CachedEntities::$userGroups[1] = [];

        $result = CachedEntities::getCachedUserGroupByName('Author', 1, 'en');

        $this->assertNull($result);
    }

    // ==================== getCachedGenreId Tests ====================

    public function testGetCachedGenreIdReturnsCachedValue(): void
    {
        CachedEntities::$genreIds['SUBMISSION'] = 5;

        $result = CachedEntities::getCachedGenreId('SUBMISSION', 1);

        $this->assertEquals(5, $result);
    }

    public function testGetCachedGenreIdQueriesDaoOnMiss(): void
    {
        $genreMock = Mockery::mock(Genre::class);
        $genreMock->shouldReceive('getId')->andReturn(7);

        $genreDaoMock = Mockery::mock(\PKP\submission\GenreDAO::class);
        $genreDaoMock->shouldReceive('getByKey')
            ->once()
            ->with('SUBMISSION', 1)
            ->andReturn($genreMock);

        DAORegistry::registerDAO('GenreDAO', $genreDaoMock);

        $result = CachedEntities::getCachedGenreId('SUBMISSION', 1);

        $this->assertEquals(7, $result);
        $this->assertEquals(7, CachedEntities::$genreIds['SUBMISSION']);
    }

    public function testGetCachedGenreIdDoesNotReQueryOnSubsequentCalls(): void
    {
        CachedEntities::$genreIds['CACHED_GENRE'] = 3;

        $result1 = CachedEntities::getCachedGenreId('CACHED_GENRE', 1);
        $result2 = CachedEntities::getCachedGenreId('CACHED_GENRE', 1);

        $this->assertEquals($result1, $result2);
    }

    // ==================== getCachedSupplementaryGenreId Tests ====================

    public function testGetCachedSupplementaryGenreIdReturnsCachedValue(): void
    {
        CachedEntities::$supplementaryGenreIds[1] = 15;

        $result = CachedEntities::getCachedSupplementaryGenreId(1);

        $this->assertEquals(15, $result);
    }

    public function testGetCachedSupplementaryGenreIdReturnsCachedNull(): void
    {
        // This method uses array_key_exists, so cached null prevents re-queries
        CachedEntities::$supplementaryGenreIds[99] = null;

        $result = CachedEntities::getCachedSupplementaryGenreId(99);

        $this->assertNull($result);
        $this->assertArrayHasKey(99, CachedEntities::$supplementaryGenreIds);
    }

    public function testGetCachedSupplementaryGenreIdQueriesDaoOnMiss(): void
    {
        $genreMock = Mockery::mock(Genre::class);
        $genreMock->shouldReceive('getId')->andReturn(20);

        $resultSet = Mockery::mock(\PKP\db\DAOResultFactory::class);
        $resultSet->shouldReceive('toArray')->andReturn([$genreMock]);

        $genreDaoMock = Mockery::mock(\PKP\submission\GenreDAO::class);
        $genreDaoMock->shouldReceive('getBySupplementaryAndContextId')
            ->once()
            ->with(true, 1)
            ->andReturn($resultSet);

        DAORegistry::registerDAO('GenreDAO', $genreDaoMock);

        $result = CachedEntities::getCachedSupplementaryGenreId(1);

        $this->assertEquals(20, $result);
        $this->assertEquals(20, CachedEntities::$supplementaryGenreIds[1]);
    }

    public function testGetCachedSupplementaryGenreIdReturnsNullWhenEmpty(): void
    {
        $resultSet = Mockery::mock(\PKP\db\DAOResultFactory::class);
        $resultSet->shouldReceive('toArray')->andReturn([]);

        $genreDaoMock = Mockery::mock(\PKP\submission\GenreDAO::class);
        $genreDaoMock->shouldReceive('getBySupplementaryAndContextId')
            ->once()
            ->with(true, 2)
            ->andReturn($resultSet);

        DAORegistry::registerDAO('GenreDAO', $genreDaoMock);

        $result = CachedEntities::getCachedSupplementaryGenreId(2);

        $this->assertNull($result);
        $this->assertArrayHasKey(2, CachedEntities::$supplementaryGenreIds);
        $this->assertNull(CachedEntities::$supplementaryGenreIds[2]);
    }

    // ==================== getCachedCategory Tests ====================

    public function testGetCachedCategoryReturnsCachedValue(): void
    {
        $category = MockFactory::category()->withPath('research')->build();
        CachedEntities::$categories['research'] = $category;

        $result = CachedEntities::getCachedCategory('research', 1);

        $this->assertSame($category, $result);
    }

    public function testGetCachedCategoryQueriesRepoOnMiss(): void
    {
        $category = $this->createMockCategory(['path' => 'biology', 'title' => 'Biology']);

        $collectorMock = Mockery::mock(\PKP\category\Collector::class);
        $collectorMock->shouldReceive('filterByContextIds')->with([1])->andReturnSelf();
        $collectorMock->shouldReceive('getMany')->andReturn(new LazyCollection([$category]));

        $categoryRepoMock = $this->mockCategoryRepository();
        $categoryRepoMock->shouldReceive('getCollector')->andReturn($collectorMock);

        $result = CachedEntities::getCachedCategory('biology', 1);

        $this->assertSame($category, $result);
        $this->assertSame($category, CachedEntities::$categories['biology']);
    }

    public function testGetCachedCategoryReturnsNullWhenNotFound(): void
    {
        $otherCategory = $this->createMockCategory(['path' => 'physics', 'title' => 'Physics']);

        $collectorMock = Mockery::mock(\PKP\category\Collector::class);
        $collectorMock->shouldReceive('filterByContextIds')->with([1])->andReturnSelf();
        $collectorMock->shouldReceive('getMany')->andReturn(new LazyCollection([$otherCategory]));

        $categoryRepoMock = $this->mockCategoryRepository();
        $categoryRepoMock->shouldReceive('getCollector')->andReturn($collectorMock);

        $result = CachedEntities::getCachedCategory('chemistry', 1);

        $this->assertNull($result);
    }

    public function testGetCachedCategoryReturnsNullWhenNoCategoriesExist(): void
    {
        $collectorMock = Mockery::mock(\PKP\category\Collector::class);
        $collectorMock->shouldReceive('filterByContextIds')->with([1])->andReturnSelf();
        $collectorMock->shouldReceive('getMany')->andReturn(new LazyCollection([]));

        $categoryRepoMock = $this->mockCategoryRepository();
        $categoryRepoMock->shouldReceive('getCollector')->andReturn($collectorMock);

        $result = CachedEntities::getCachedCategory('anything', 1);

        $this->assertNull($result);
    }

    // ==================== getCachedSection Tests ====================

    public function testGetCachedSectionReturnsCachedValue(): void
    {
        $section = MockFactory::section()->withTitle('Preprints')->withAbbrev('PRE')->build();
        CachedEntities::$sections['Preprints_PRE'] = $section;

        $result = CachedEntities::getCachedSection('Preprints', 'PRE', 'en', 1);

        $this->assertSame($section, $result);
    }

    public function testGetCachedSectionQueriesRepoOnMiss(): void
    {
        $section = $this->createMockSection([
            'title' => 'Articles',
            'abbrev' => 'ART',
        ]);

        $sectionRepoMock = $this->mockSectionRepository();
        $collectorMock = $this->createMockSectionCollector([$section]);
        $sectionRepoMock->shouldReceive('getCollector')->andReturn($collectorMock);

        $result = CachedEntities::getCachedSection('Articles', 'ART', 'en', 1);

        $this->assertSame($section, $result);
        $this->assertSame($section, CachedEntities::$sections['Articles_ART']);
    }

    public function testGetCachedSectionReturnsNullWhenNotFound(): void
    {
        $otherSection = $this->createMockSection([
            'title' => 'Reviews',
            'abbrev' => 'REV',
        ]);

        $sectionRepoMock = $this->mockSectionRepository();
        $collectorMock = $this->createMockSectionCollector([$otherSection]);
        $sectionRepoMock->shouldReceive('getCollector')->andReturn($collectorMock);

        $result = CachedEntities::getCachedSection('NonExistent', 'NE', 'en', 1);

        $this->assertNull($result);
    }

    public function testGetCachedSectionUsesCompositeKeyWithUppercaseAbbrev(): void
    {
        $section = MockFactory::section()->withTitle('Test')->withAbbrev('ts')->build();
        CachedEntities::$sections['Test_TS'] = $section;

        $result = CachedEntities::getCachedSection('Test', 'ts', 'en', 1);

        $this->assertSame($section, $result);
    }

    public function testGetCachedSectionTrimsAbbrevInKey(): void
    {
        $section = MockFactory::section()->withTitle('Test')->withAbbrev('TS')->build();
        CachedEntities::$sections['Test_TS'] = $section;

        $result = CachedEntities::getCachedSection('Test', ' TS ', 'en', 1);

        $this->assertSame($section, $result);
    }

    public function testGetCachedSectionReturnsNullWhenNoSectionsExist(): void
    {
        $sectionRepoMock = $this->mockSectionRepository();
        $collectorMock = $this->createMockSectionCollector([]);
        $sectionRepoMock->shouldReceive('getCollector')->andReturn($collectorMock);

        $result = CachedEntities::getCachedSection('Any', 'ANY', 'en', 1);

        $this->assertNull($result);
    }

    // ==================== getCachedSectionById Tests ====================

    public function testGetCachedSectionByIdReturnsCachedValue(): void
    {
        $section = MockFactory::section()->withId(42)->build();
        CachedEntities::$sections[42] = $section;

        $result = CachedEntities::getCachedSectionById(42, 1, 'en');

        $this->assertSame($section, $result);
    }

    public function testGetCachedSectionByIdQueriesRepoOnMiss(): void
    {
        $section = $this->createMockSection([
            'id' => 55,
            'title' => 'Preprints',
            'abbrev' => 'PRE',
        ]);

        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('get')
            ->once()
            ->with(55, 1)
            ->andReturn($section);

        $result = CachedEntities::getCachedSectionById(55, 1, 'en');

        $this->assertSame($section, $result);
    }

    public function testGetCachedSectionByIdDualCachesByIdAndCompositeKey(): void
    {
        $section = $this->createMockSection([
            'id' => 60,
            'title' => 'Reviews',
            'abbrev' => 'REV',
        ]);

        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('get')
            ->once()
            ->with(60, 1)
            ->andReturn($section);

        CachedEntities::getCachedSectionById(60, 1, 'en');

        $this->assertSame($section, CachedEntities::$sections[60]);
        $this->assertSame($section, CachedEntities::$sections['Reviews_REV']);
    }

    public function testGetCachedSectionByIdReturnsNullWhenNotFound(): void
    {
        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('get')
            ->once()
            ->with(999, 1)
            ->andReturn(null);

        $result = CachedEntities::getCachedSectionById(999, 1, 'en');

        $this->assertNull($result);
    }

    public function testGetCachedSectionByIdDoesNotReQueryOnSubsequentCalls(): void
    {
        $section = $this->createMockSection(['id' => 70, 'title' => 'Test', 'abbrev' => 'T']);

        $sectionRepoMock = $this->mockSectionRepository();
        $sectionRepoMock->shouldReceive('get')
            ->once()
            ->with(70, 1)
            ->andReturn($section);

        CachedEntities::getCachedSectionById(70, 1, 'en');
        $result = CachedEntities::getCachedSectionById(70, 1, 'en');

        $this->assertSame($section, $result);
    }
}
