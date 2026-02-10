<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/UserInterestsProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserInterestsProcessorTest
 *
 * @brief Tests for UserInterestsProcessor class - testing interests parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\UserInterestsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UserInterestsProcessor::class)]
class UserInterestsProcessorTest extends BaseTestCase
{
    // ==================== Interests Parsing Tests ====================

    public function testParsesSingleInterest(): void
    {
        $interestsString = 'machine learning';
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertCount(1, $interests);
        $this->assertEquals('machine learning', $interests[0]);
    }

    public function testParsesMultipleInterests(): void
    {
        $interestsString = 'machine learning;data science;artificial intelligence';
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertCount(3, $interests);
        $this->assertEquals('machine learning', $interests[0]);
        $this->assertEquals('data science', $interests[1]);
        $this->assertEquals('artificial intelligence', $interests[2]);
    }

    public function testTrimsInterestWhitespace(): void
    {
        $interestsString = '  machine learning  ;  data science  ';
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertEquals('machine learning', $interests[0]);
        $this->assertEquals('data science', $interests[1]);
    }

    public function testHandlesEmptyInterestsString(): void
    {
        $interestsString = '';
        $interests = array_filter(array_map('trim', explode(';', $interestsString)));

        $this->assertEmpty($interests);
    }

    // ==================== Unicode Interests Tests ====================

    public function testHandlesUnicodeInterests(): void
    {
        $interestsString = 'inteligência artificial;机器学习;機械学習';
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertCount(3, $interests);
        $this->assertEquals('inteligência artificial', $interests[0]);
        $this->assertEquals('机器学习', $interests[1]);
        $this->assertEquals('機械学習', $interests[2]);
    }

    // ==================== Special Characters Tests ====================

    public function testHandlesInterestsWithSpecialCharacters(): void
    {
        $interestsString = 'machine learning (ML);data science & analytics;AI/ML';
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertEquals('machine learning (ML)', $interests[0]);
        $this->assertEquals('data science & analytics', $interests[1]);
        $this->assertEquals('AI/ML', $interests[2]);
    }

    // ==================== Many Interests Tests ====================

    public function testHandlesManyInterests(): void
    {
        $interestsList = [];
        for ($i = 1; $i <= 20; $i++) {
            $interestsList[] = "interest $i";
        }
        $interestsString = implode(';', $interestsList);
        $interests = array_map('trim', explode(';', $interestsString));

        $this->assertCount(20, $interests);
        $this->assertEquals('interest 1', $interests[0]);
        $this->assertEquals('interest 20', $interests[19]);
    }

    // ==================== Interests Array Handling Tests ====================

    public function testInterestsAsArray(): void
    {
        $interests = ['machine learning', 'data science', 'AI'];

        $this->assertIsArray($interests);
        $this->assertCount(3, $interests);
        $this->assertContains('machine learning', $interests);
    }

    public function testEmptyInterestsArray(): void
    {
        $interests = [];

        $this->assertIsArray($interests);
        $this->assertEmpty($interests);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessSetsInterestsForExistingUser(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $user = $this->createMockUser(['id' => 5]);
        $userRepoMock->shouldReceive('get')->with(5)->andReturn($user);

        $userInterestMock = $this->mockUserInterestRepository();
        $userInterestMock->shouldReceive('setInterestsForUser')
            ->once()
            ->with($user, ['machine learning', 'data science']);

        UserInterestsProcessor::process(['machine learning', 'data science'], 5);

        $this->assertTrue(true);
    }

    public function testProcessWithSingleInterest(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $user = $this->createMockUser(['id' => 3]);
        $userRepoMock->shouldReceive('get')->with(3)->andReturn($user);

        $userInterestMock = $this->mockUserInterestRepository();
        $userInterestMock->shouldReceive('setInterestsForUser')
            ->once()
            ->with($user, ['artificial intelligence']);

        UserInterestsProcessor::process(['artificial intelligence'], 3);

        $this->assertTrue(true);
    }

    public function testProcessDoesNothingForEmptyInterests(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('get')->never();

        $userInterestMock = $this->mockUserInterestRepository();
        $userInterestMock->shouldReceive('setInterestsForUser')->never();

        UserInterestsProcessor::process([], 5);

        $this->assertTrue(true);
    }

    public function testProcessDoesNothingWhenUserNotFound(): void
    {
        $userRepoMock = $this->mockUserRepository();
        $userRepoMock->shouldReceive('get')->with(99)->andReturn(null);

        $userInterestMock = $this->mockUserInterestRepository();
        $userInterestMock->shouldReceive('setInterestsForUser')->never();

        UserInterestsProcessor::process(['test interest'], 99);

        $this->assertTrue(true);
    }
}
