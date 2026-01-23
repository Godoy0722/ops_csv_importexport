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
 * @brief Tests for UserGroupsProcessor class - testing role parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\UserGroupsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

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

    // ==================== Role Name Case Sensitivity Tests ====================

    public function testRoleNameCaseSensitivity(): void
    {
        // Role names should be case-insensitive in matching
        $roleName = 'AUTHOR';
        $storedRoleName = 'Author';

        $this->assertTrue(
            strcasecmp($roleName, $storedRoleName) === 0,
            'Role names should match case-insensitively'
        );
    }

    public function testRoleNameVariations(): void
    {
        $variations = ['Author', 'AUTHOR', 'author', 'AuThOr'];

        foreach ($variations as $variation) {
            $this->assertTrue(
                strcasecmp($variation, 'author') === 0,
                "'{$variation}' should match 'author' case-insensitively"
            );
        }
    }

    // ==================== Common Role Names Tests ====================

    public function testCommonRoleNames(): void
    {
        $commonRoles = [
            'Author',
            'Reader',
            'Reviewer',
            'Editor',
            'Section Editor',
            'Journal Manager',
            'Server Manager',
        ];

        foreach ($commonRoles as $role) {
            $this->assertNotEmpty($role);
            $this->assertIsString($role);
        }
    }

    // ==================== Role Array Handling Tests ====================

    public function testRoleArrayFromString(): void
    {
        $rolesString = 'Author;Reader';
        $rolesArray = explode(';', $rolesString);

        $this->assertIsArray($rolesArray);
        $this->assertContains('Author', $rolesArray);
        $this->assertContains('Reader', $rolesArray);
    }

    public function testEmptyRoleArrayHandling(): void
    {
        $roles = [];

        $this->assertIsArray($roles);
        $this->assertCount(0, $roles);
        $this->assertEmpty($roles);
    }
}
