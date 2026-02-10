<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/FundersProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FundersProcessorTest
 *
 * @brief Tests for FundersProcessor class - testing funder data parsing and processing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\generic\funding\classes\Funder;
use APP\plugins\generic\funding\classes\FunderAward;
use APP\plugins\generic\funding\classes\FunderAwardDAO;
use APP\plugins\generic\funding\classes\FunderDAO;
use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\publication\Publication;
use APP\submission\Submission;
use Mockery;
use PKP\db\DAOResultFactory;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FundersProcessor::class)]
class FundersProcessorTest extends BaseTestCase
{
    private ?array $pluginRegistryBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupPluginRegistry();
    }

    protected function tearDown(): void
    {
        $this->restorePluginRegistry();
        $this->resetFunderDaoStatics();
        parent::tearDown();
    }

    // ==================== PluginRegistry Helpers ====================

    private function backupPluginRegistry(): void
    {
        $this->pluginRegistryBackup = PluginRegistry::getPlugins();
        // Clear the registry for clean test state
        $allPlugins = &PluginRegistry::getPlugins();
        foreach (array_keys($allPlugins) as $key) {
            unset($allPlugins[$key]);
        }
    }

    private function restorePluginRegistry(): void
    {
        $allPlugins = &PluginRegistry::getPlugins();
        foreach (array_keys($allPlugins) as $key) {
            unset($allPlugins[$key]);
        }
        foreach ($this->pluginRegistryBackup as $key => $value) {
            $allPlugins[$key] = $value;
        }
    }

    private function registerMockFundingPlugin(bool $enabled = true, bool $crossrefValidation = false): void
    {
        $mockPlugin = Mockery::mock(Plugin::class);
        $mockPlugin->shouldReceive('getEnabled')->andReturn($enabled);
        $mockPlugin->shouldReceive('getSetting')
            ->with(Mockery::any(), 'enableGrantIdValidation')
            ->andReturn($crossrefValidation);

        $allPlugins = &PluginRegistry::getPlugins();
        $allPlugins['generic'] ??= [];
        $allPlugins['generic']['FundingPlugin'] = $mockPlugin;
    }

    // ==================== FunderDAO Helpers ====================

    private function setFunderDao(object $dao): void
    {
        $ref = new \ReflectionClass(FundersProcessor::class);
        $prop = $ref->getProperty('funderDao');
        $prop->setAccessible(true);
        $prop->setValue(null, $dao);
    }

    private function setFunderAwardDao(object $dao): void
    {
        $ref = new \ReflectionClass(FundersProcessor::class);
        $prop = $ref->getProperty('funderAwardDao');
        $prop->setAccessible(true);
        $prop->setValue(null, $dao);
    }

    private function resetFunderDaoStatics(): void
    {
        $ref = new \ReflectionClass(FundersProcessor::class);

        $prop1 = $ref->getProperty('funderDao');
        $prop1->setAccessible(true);
        $prop1->setValue(null, null);

        $prop2 = $ref->getProperty('funderAwardDao');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);
    }

    private function createMockFunderDao(): Mockery\MockInterface
    {
        $mock = Mockery::mock(FunderDAO::class);
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Funder());
        $mock->shouldReceive('insertObject')->andReturn(1)->byDefault();
        $mock->shouldReceive('getBySubmissionId')->andReturnUsing(function () {
            return $this->createEmptyDAOResultFactory();
        })->byDefault();

        return $mock;
    }

    private function createMockFunderAwardDao(): Mockery\MockInterface
    {
        $mock = Mockery::mock(FunderAwardDAO::class);
        $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new FunderAward());
        $mock->shouldReceive('insertObject')->andReturn(1)->byDefault();
        $mock->shouldReceive('getByFunderId')->andReturnUsing(function () {
            return $this->createEmptyDAOResultFactory();
        })->byDefault();

        return $mock;
    }

    private function createEmptyDAOResultFactory(): Mockery\MockInterface
    {
        $mock = Mockery::mock(DAOResultFactory::class);
        $mock->shouldReceive('next')->andReturn(null);
        $mock->shouldReceive('toIterator')->andReturn(new \ArrayIterator([]));
        return $mock;
    }

    private function createDAOResultFactoryWithItems(array $items): Mockery\MockInterface
    {
        $index = 0;
        $mock = Mockery::mock(DAOResultFactory::class);
        $mock->shouldReceive('next')->andReturnUsing(function () use (&$index, $items) {
            return $items[$index++] ?? null;
        });
        $mock->shouldReceive('toIterator')->andReturn(new \ArrayIterator($items));
        return $mock;
    }

    private function createMockSubmissionForFunders(int $id = 1): Submission
    {
        $submission = Mockery::mock(Submission::class)->makePartial();
        $submission->shouldReceive('getId')->andReturn($id);
        return $submission;
    }

    // ==================== Funder String Parsing Tests ====================

    public function testParsesSingleFunderWithoutAwards(): void
    {
        $funderString = 'National Science Foundation,http://dx.doi.org/10.13039/100000001,';

        $parts = explode(',', $funderString);

        $this->assertEquals('National Science Foundation', $parts[0]);
        $this->assertEquals('http://dx.doi.org/10.13039/100000001', $parts[1]);
        $this->assertEquals('', $parts[2]);
    }

    public function testParsesSingleFunderWithAward(): void
    {
        $funderString = 'NSF,http://dx.doi.org/10.13039/100000001,NSF-2024-001';

        $parts = explode(',', $funderString);

        $this->assertEquals('NSF', $parts[0]);
        $this->assertEquals('NSF-2024-001', $parts[2]);
    }

    public function testParsesSingleFunderWithMultipleAwards(): void
    {
        $funderString = 'NIH,http://dx.doi.org/10.13039/100000002,R01-AI-123456|R21-AI-789012|R33-AI-555555';

        $parts = explode(',', $funderString);
        $awards = explode('|', $parts[2]);

        $this->assertCount(3, $awards);
        $this->assertEquals('R01-AI-123456', $awards[0]);
    }

    public function testParsesMultipleFunders(): void
    {
        $fundersString = 'NSF,doi1,Award1;DOE,doi2,Award2';

        $funders = explode(';', $fundersString);

        $this->assertCount(2, $funders);
    }

    // ==================== validateFundersFormat Tests ====================

    public function testValidateFundersFormatWithValidSingleFunder(): void
    {
        $result = FundersProcessor::validateFundersFormat('NSF,http://dx.doi.org/10.13039/100000001,Award1');

        $this->assertNull($result);
    }

    public function testValidateFundersFormatWithMultipleFunders(): void
    {
        $result = FundersProcessor::validateFundersFormat(
            'NSF,http://dx.doi.org/10.13039/100000001,Award1;DOE,http://dx.doi.org/10.13039/100000015,Award2'
        );

        $this->assertNull($result);
    }

    public function testValidateFundersFormatWithEmptyString(): void
    {
        $result = FundersProcessor::validateFundersFormat('');

        $this->assertNull($result);
    }

    public function testValidateFundersFormatWithNullValue(): void
    {
        $result = FundersProcessor::validateFundersFormat(null);

        $this->assertNull($result);
    }

    public function testValidateFundersFormatWithMissingFunderName(): void
    {
        $result = FundersProcessor::validateFundersFormat(',http://dx.doi.org/10.13039/100000001,Award1');

        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    public function testValidateFundersFormatWithFunderNameOnly(): void
    {
        $result = FundersProcessor::validateFundersFormat('National Science Foundation');

        $this->assertNull($result);
    }

    public function testValidateFundersFormatWithEmptyFunderBetweenSemicolons(): void
    {
        $result = FundersProcessor::validateFundersFormat('NSF,doi1,Award1;;DOE,doi2,Award2');

        $this->assertNull($result);
    }

    public function testValidateFundersFormatWithWhitespace(): void
    {
        $result = FundersProcessor::validateFundersFormat('  NSF  , doi , Award1 ;  DOE  , doi2 , Award2 ');

        $this->assertNull($result);
    }

    // ==================== isFundingPluginEnabled() Integration Tests ====================

    public function testIsFundingPluginEnabledReturnsTrueWhenEnabled(): void
    {
        $this->registerMockFundingPlugin(true);

        $result = FundersProcessor::isFundingPluginEnabled(1);

        $this->assertTrue($result);
    }

    public function testIsFundingPluginEnabledReturnsFalseWhenDisabled(): void
    {
        $this->registerMockFundingPlugin(false);

        $result = FundersProcessor::isFundingPluginEnabled(1);

        $this->assertFalse($result);
    }

    // ==================== isCrossrefValidationEnabled() Integration Tests ====================

    public function testIsCrossrefValidationEnabledReturnsTrue(): void
    {
        $this->registerMockFundingPlugin(true, true);

        $result = FundersProcessor::isCrossrefValidationEnabled(1);

        $this->assertTrue($result);
    }

    public function testIsCrossrefValidationEnabledReturnsFalseWhenDisabled(): void
    {
        $this->registerMockFundingPlugin(true, false);

        $result = FundersProcessor::isCrossrefValidationEnabled(1);

        $this->assertFalse($result);
    }

    public function testIsCrossrefValidationEnabledReturnsFalseWhenPluginDisabled(): void
    {
        $this->registerMockFundingPlugin(false);

        $result = FundersProcessor::isCrossrefValidationEnabled(1);

        $this->assertFalse($result);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessReturnsEarlyWhenPluginDisabled(): void
    {
        $this->registerMockFundingPlugin(false);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('getBySubmissionId')->never();
        $this->setFunderDao($funderDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi,Award1'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertTrue(true);
    }

    public function testProcessReturnsEarlyWhenSubmissionHasFunders(): void
    {
        $this->registerMockFundingPlugin(true);

        $existingFunder = new Funder();
        $existingFunder->setId(1);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('getBySubmissionId')
            ->with(1)
            ->andReturn($this->createDAOResultFactoryWithItems([$existingFunder]));
        $funderDao->shouldReceive('insertObject')->never();
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi,Award1'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertTrue(true);
    }

    public function testProcessCreatesFunderFromString(): void
    {
        $this->registerMockFundingPlugin(true);

        $capturedFunders = [];
        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($funder) use (&$capturedFunders) {
                $capturedFunders[] = $funder;
                return count($capturedFunders);
            });
        $this->setFunderDao($funderDao);

        $capturedAwards = [];
        $funderAwardDao = $this->createMockFunderAwardDao();
        $funderAwardDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($award) use (&$capturedAwards) {
                $capturedAwards[] = $award;
                return count($capturedAwards);
            });
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,http://dx.doi.org/10.13039/100000001,Award-001|Award-002'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertCount(1, $capturedFunders);
        $this->assertEquals('NSF', $capturedFunders[0]->getFunderName());
        $this->assertEquals('http://dx.doi.org/10.13039/100000001', $capturedFunders[0]->getFunderIdentification());
        $this->assertEquals(1, $capturedFunders[0]->getContextId());
        $this->assertEquals(1, $capturedFunders[0]->getSubmissionId());

        $this->assertCount(2, $capturedAwards);
        $this->assertEquals('Award-001', $capturedAwards[0]->getFunderAwardNumber());
        $this->assertEquals('Award-002', $capturedAwards[1]->getFunderAwardNumber());
    }

    public function testProcessCreatesMultipleFunders(): void
    {
        $this->registerMockFundingPlugin(true);

        $capturedFunders = [];
        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($funder) use (&$capturedFunders) {
                $capturedFunders[] = $funder;
                return count($capturedFunders);
            });
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi1,;DOE,doi2,'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertCount(2, $capturedFunders);
        $this->assertEquals('NSF', $capturedFunders[0]->getFunderName());
        $this->assertEquals('DOE', $capturedFunders[1]->getFunderName());
    }

    public function testProcessSkipsEmptyFunderName(): void
    {
        $this->registerMockFundingPlugin(true);

        $capturedFunders = [];
        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($funder) use (&$capturedFunders) {
                $capturedFunders[] = $funder;
                return count($capturedFunders);
            });
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => ',doi1,Award1;NSF,doi2,Award2'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertCount(1, $capturedFunders);
        $this->assertEquals('NSF', $capturedFunders[0]->getFunderName());
    }

    public function testProcessSkipsEmptyFunderEntries(): void
    {
        $this->registerMockFundingPlugin(true);

        $capturedFunders = [];
        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($funder) use (&$capturedFunders) {
                $capturedFunders[] = $funder;
                return count($capturedFunders);
            });
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi1,Award1;;DOE,doi2,'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertCount(2, $capturedFunders);
    }

    public function testProcessSkipsEmptyAwardNumbers(): void
    {
        $this->registerMockFundingPlugin(true);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')->andReturn(1);
        $this->setFunderDao($funderDao);

        $capturedAwards = [];
        $funderAwardDao = $this->createMockFunderAwardDao();
        $funderAwardDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($award) use (&$capturedAwards) {
                $capturedAwards[] = $award;
                return count($capturedAwards);
            });
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi1,Award1||Award2'];

        FundersProcessor::process($data, $submission, 1);

        $this->assertCount(2, $capturedAwards);
        $this->assertEquals('Award1', $capturedAwards[0]->getFunderAwardNumber());
        $this->assertEquals('Award2', $capturedAwards[1]->getFunderAwardNumber());
    }

    public function testProcessClonesFundersFromBaseSubmission(): void
    {
        $this->registerMockFundingPlugin(true);

        $baseFunder = new Funder();
        $baseFunder->setId(10);
        $baseFunder->setFunderName('Original NSF');
        $baseFunder->setFunderIdentification('doi-original');

        $baseAward = new FunderAward();
        $baseAward->setFunderAwardNumber('BASE-001');

        $capturedFunders = [];
        $funderDao = $this->createMockFunderDao();
        // First call: check new submission has no funders
        $funderDao->shouldReceive('getBySubmissionId')
            ->with(2)
            ->andReturn($this->createEmptyDAOResultFactory());
        // For cloning: get base submission funders
        $funderDao->shouldReceive('getBySubmissionId')
            ->with(1)
            ->andReturn($this->createDAOResultFactoryWithItems([$baseFunder]));
        $funderDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($funder) use (&$capturedFunders) {
                $capturedFunders[] = $funder;
                return count($capturedFunders) + 100;
            });
        $this->setFunderDao($funderDao);

        $capturedAwards = [];
        $funderAwardDao = $this->createMockFunderAwardDao();
        $funderAwardDao->shouldReceive('getByFunderId')
            ->with(10)
            ->andReturn($this->createDAOResultFactoryWithItems([$baseAward]));
        $funderAwardDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($award) use (&$capturedAwards) {
                $capturedAwards[] = $award;
                return count($capturedAwards);
            });
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(2);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('submissionId', 1);

        $data = (object) ['funders' => ''];

        FundersProcessor::process($data, $submission, 1, $basePublication);

        $this->assertCount(1, $capturedFunders);
        $this->assertEquals('Original NSF', $capturedFunders[0]->getFunderName());
        $this->assertEquals('doi-original', $capturedFunders[0]->getFunderIdentification());
        $this->assertEquals(2, $capturedFunders[0]->getSubmissionId());

        $this->assertCount(1, $capturedAwards);
        $this->assertEquals('BASE-001', $capturedAwards[0]->getFunderAwardNumber());
    }

    public function testProcessDoesNotCloneWhenSameSubmission(): void
    {
        $this->registerMockFundingPlugin(true);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')->never();
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('submissionId', 1);

        $data = (object) ['funders' => ''];

        FundersProcessor::process($data, $submission, 1, $basePublication);

        $this->assertTrue(true);
    }

    // ==================== processMultiLocale() Integration Tests ====================

    public function testProcessMultiLocaleReturnsEarlyWhenPluginDisabled(): void
    {
        $this->registerMockFundingPlugin(false);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('getBySubmissionId')->never();
        $this->setFunderDao($funderDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi,Award1'];

        FundersProcessor::processMultiLocale($data, $submission, 1);

        $this->assertTrue(true);
    }

    public function testProcessMultiLocaleReturnsEarlyWhenEmpty(): void
    {
        $this->registerMockFundingPlugin(true);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('getBySubmissionId')->never();
        $this->setFunderDao($funderDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => ''];

        FundersProcessor::processMultiLocale($data, $submission, 1);

        $this->assertTrue(true);
    }

    public function testProcessMultiLocaleReturnsEarlyWhenSubmissionHasFunders(): void
    {
        $this->registerMockFundingPlugin(true);

        $existingFunder = new Funder();
        $existingFunder->setId(1);

        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('getBySubmissionId')
            ->with(1)
            ->andReturn($this->createDAOResultFactoryWithItems([$existingFunder]));
        $funderDao->shouldReceive('insertObject')->never();
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi,Award1'];

        FundersProcessor::processMultiLocale($data, $submission, 1);

        $this->assertTrue(true);
    }

    public function testProcessMultiLocaleCreatesFundersWhenNoneExist(): void
    {
        $this->registerMockFundingPlugin(true);

        $capturedFunders = [];
        $funderDao = $this->createMockFunderDao();
        $funderDao->shouldReceive('insertObject')
            ->andReturnUsing(function ($funder) use (&$capturedFunders) {
                $capturedFunders[] = $funder;
                return count($capturedFunders);
            });
        $this->setFunderDao($funderDao);

        $funderAwardDao = $this->createMockFunderAwardDao();
        $this->setFunderAwardDao($funderAwardDao);

        $submission = $this->createMockSubmissionForFunders(1);
        $data = (object) ['funders' => 'NSF,doi1,Award1'];

        FundersProcessor::processMultiLocale($data, $submission, 1);

        $this->assertCount(1, $capturedFunders);
        $this->assertEquals('NSF', $capturedFunders[0]->getFunderName());
    }
}
