<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/UserGroupsProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupsProcessorTest
 *
 * @brief Tests for UserGroupsProcessor class - testing role parsing and assignment logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\cachedAttributes\CachedEntities;
use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\userGroup\UserGroup;

#[CoversClass(UserGroupsProcessor::class)]
class UserGroupsProcessorTest extends BaseTestCase
{
    // ==================== Role Parsing Logic Tests ====================

    public function testParsesSingleRole(): void
    {
        $rolesString = 'Author';
        $roles = array_map('trim', explode(';', $rolesString));

        $this->assertCount(1, $roles);
        $this->assertEquals('Author', $roles[0]);
    }

    public function testParsesMultipleRoles(): void
    {
        $rolesString = 'Author;Reader;Reviewer';
        $roles = array_map('trim', explode(';', $rolesString));

        $this->assertCount(3, $roles);
        $this->assertEquals('Author', $roles[0]);
        $this->assertEquals('Reader', $roles[1]);
        $this->assertEquals('Reviewer', $roles[2]);
    }

    public function testTrimsRoleWhitespace(): void
    {
        $rolesString = '  Author  ;  Reader  ;  Reviewer  ';
        $roles = array_map('trim', explode(';', $rolesString));

        $this->assertEquals('Author', $roles[0]);
        $this->assertEquals('Reader', $roles[1]);
        $this->assertEquals('Reviewer', $roles[2]);
    }

    public function testHandlesEmptyRolesString(): void
    {
        $rolesString = '';
        $roles = array_filter(array_map('trim', explode(';', $rolesString)));

        $this->assertEmpty($roles);
    }

    public function testHandlesRolesWithOnlySeparators(): void
    {
        $rolesString = ';;;';
        $roles = array_filter(array_map('trim', explode(';', $rolesString)));

        $this->assertEmpty($roles);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessAssignsSingleMatchingRole(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 10);

        UserGroupsProcessor::process(['Author'], 5, 1, 'en');

        $this->assertTrue(true);
    }

    public function testProcessAssignsMultipleMatchingRoles(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        $readerGroup = $this->createMockUserGroup(['id' => 11, 'name' => ['en' => 'Reader']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup, 11 => $readerGroup];

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 10);
        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 11);

        UserGroupsProcessor::process(['Author', 'Reader'], 5, 1, 'en');

        $this->assertTrue(true);
    }

    public function testProcessSkipsNonMatchingRoles(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never();

        UserGroupsProcessor::process(['NonExistentRole'], 5, 1, 'en');

        $this->assertTrue(true);
    }

    public function testProcessWithEmptyRolesArray(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->never();

        UserGroupsProcessor::process([], 5, 1, 'en');

        $this->assertTrue(true);
    }

    public function testProcessAssignsOnlyMatchingRolesFromMixedList(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 10);

        UserGroupsProcessor::process(['Author', 'NonExistent', 'AlsoNonExistent'], 5, 1, 'en');

        $this->assertTrue(true);
    }

    public function testProcessMatchesRolesCaseInsensitively(): void
    {
        $userGroupMock = $this->mockUserGroupRepository();

        $authorGroup = $this->createMockUserGroup(['id' => 10, 'name' => ['en' => 'Author']]);
        CachedEntities::$userGroups[1] = [10 => $authorGroup];

        $userGroupMock->shouldReceive('assignUserToGroup')
            ->once()
            ->with(5, 10);

        UserGroupsProcessor::process(['author'], 5, 1, 'en');

        $this->assertTrue(true);
    }
}
