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
 * @brief Tests for CachedEntities static caching behavior
 */

namespace APP\plugins\importexport\csv\tests\Unit\CachedAttributes;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CachedEntities::class)]
class CachedEntitiesTest extends BaseTestCase
{
    // ==================== Server Cache Tests ====================

    public function testServerCacheHitReturnsStoredValue(): void
    {
        $server = MockFactory::server()->withPath('cached-server')->build();
        CachedEntities::$servers['cached-server'] = $server;

        $result = CachedEntities::$servers['cached-server'];

        $this->assertSame($server, $result);
    }

    public function testServerCacheIsEmptyByDefault(): void
    {
        // After setUp, cache should be cleared
        $this->assertEmpty(CachedEntities::$servers);
    }

    // ==================== User Group Cache Tests ====================

    public function testUserGroupIdsCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$userGroupIds);
    }

    public function testUserGroupIdsCacheStoresValue(): void
    {
        CachedEntities::$userGroupIds['testserver'] = 42;

        $this->assertEquals(42, CachedEntities::$userGroupIds['testserver']);
    }

    public function testUserGroupIdsCacheStoresNull(): void
    {
        CachedEntities::$userGroupIds['missing-server'] = null;

        $this->assertArrayHasKey('missing-server', CachedEntities::$userGroupIds);
        $this->assertNull(CachedEntities::$userGroupIds['missing-server']);
    }

    // ==================== User Groups Cache Tests ====================

    public function testUserGroupsCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$userGroups);
    }

    public function testUserGroupsCacheStoresMultipleGroups(): void
    {
        $group1 = MockFactory::userGroup()->withId(1)->withName(['en' => 'Author'])->build();
        $group2 = MockFactory::userGroup()->withId(2)->withName(['en' => 'Reader'])->build();

        CachedEntities::$userGroups[1] = [1 => $group1, 2 => $group2];

        $this->assertCount(2, CachedEntities::$userGroups[1]);
    }

    // ==================== Genre Cache Tests ====================

    public function testGenreIdsCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$genreIds);
    }

    public function testGenreIdsCacheStoresValue(): void
    {
        CachedEntities::$genreIds['SUBMISSION'] = 1;

        $this->assertEquals(1, CachedEntities::$genreIds['SUBMISSION']);
    }

    // ==================== Supplementary Genre Cache Tests ====================

    public function testSupplementaryGenreIdsCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$supplementaryGenreIds);
    }

    public function testSupplementaryGenreIdsCacheStoresValue(): void
    {
        CachedEntities::$supplementaryGenreIds[1] = 5;

        $this->assertEquals(5, CachedEntities::$supplementaryGenreIds[1]);
    }

    public function testSupplementaryGenreIdsCacheStoresNull(): void
    {
        CachedEntities::$supplementaryGenreIds[99] = null;

        $this->assertArrayHasKey(99, CachedEntities::$supplementaryGenreIds);
        $this->assertNull(CachedEntities::$supplementaryGenreIds[99]);
    }

    // ==================== Category Cache Tests ====================

    public function testCategoriesCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$categories);
    }

    public function testCategoriesCacheStoresCategory(): void
    {
        $category = MockFactory::category()
            ->withPath('research-article')
            ->withTitle('Research Article')
            ->build();

        CachedEntities::$categories['research-article'] = $category;

        $this->assertSame($category, CachedEntities::$categories['research-article']);
    }

    // ==================== Section Cache Tests ====================

    public function testSectionsCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$sections);
    }

    public function testSectionsCacheStoresByCompositeKey(): void
    {
        $section = MockFactory::section()
            ->withTitle('Preprints')
            ->withAbbrev('PRE')
            ->build();

        $key = 'Preprints_PRE';
        CachedEntities::$sections[$key] = $section;

        $this->assertSame($section, CachedEntities::$sections[$key]);
    }

    public function testSectionsCacheStoresById(): void
    {
        $section = MockFactory::section()->withId(42)->build();

        CachedEntities::$sections[42] = $section;

        $this->assertSame($section, CachedEntities::$sections[42]);
    }

    // ==================== Users Cache Tests ====================

    public function testUsersCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$users);
    }

    public function testUsersCacheStoresByEmail(): void
    {
        $user = MockFactory::user()->withEmail('test@example.com')->build();

        CachedEntities::$users['test@example.com'] = $user;

        $this->assertSame($user, CachedEntities::$users['test@example.com']);
    }

    public function testUsersCacheStoresByUsername(): void
    {
        $user = MockFactory::user()->withUsername('jdoe')->build();

        CachedEntities::$users['jdoe'] = $user;

        $this->assertSame($user, CachedEntities::$users['jdoe']);
    }

    public function testUsersCacheStoresNullForMissingUser(): void
    {
        CachedEntities::$users['nonexistent@example.com'] = null;

        $this->assertArrayHasKey('nonexistent@example.com', CachedEntities::$users);
        $this->assertNull(CachedEntities::$users['nonexistent@example.com']);
    }

    // ==================== Subscription Types Cache Tests ====================

    public function testSubscriptionTypesCacheIsEmptyByDefault(): void
    {
        $this->assertEmpty(CachedEntities::$subscriptionTypes);
    }

    // ==================== Cache Isolation Tests ====================

    public function testCachesAreIsolatedBetweenTests(): void
    {
        // This verifies that setUp clears all caches
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
        // Populate caches
        CachedEntities::$servers['test'] = MockFactory::server()->build();
        CachedEntities::$userGroupIds['test'] = 1;
        CachedEntities::$categories['test'] = MockFactory::category()->build();

        // These values should be visible within this test
        $this->assertNotEmpty(CachedEntities::$servers);
        $this->assertNotEmpty(CachedEntities::$userGroupIds);
        $this->assertNotEmpty(CachedEntities::$categories);
    }

    public function testCacheIsCleanAfterPreviousTestModification(): void
    {
        // This test runs after testCacheModificationDoesNotLeakToNextTest
        // Caches should be empty because setUp clears them
        $this->assertEmpty(CachedEntities::$servers);
        $this->assertEmpty(CachedEntities::$userGroupIds);
        $this->assertEmpty(CachedEntities::$categories);
    }
}
