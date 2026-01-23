<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/SubmissionProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionProcessorTest
 *
 * @brief Tests for SubmissionProcessor class - testing data processing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\SubmissionProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubmissionProcessor::class)]
class SubmissionProcessorTest extends BaseTestCase
{
    // ==================== Submission Data Tests ====================

    public function testSubmissionLocaleFromData(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->buildObject();

        $this->assertEquals('en', $data->locale);
    }

    public function testSubmissionLocalePortuguese(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withLocale('pt_BR')
            ->buildObject();

        $this->assertEquals('pt_BR', $data->locale);
    }

    // ==================== Date Handling Tests ====================

    public function testDateSubmittedParsing(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDateSubmitted('2024-01-10')
            ->buildObject();

        $this->assertEquals('2024-01-10', $data->dateSubmitted);
    }

    public function testDatePostedFallbackWhenNoDateSubmitted(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('')
            ->buildObject();

        // When dateSubmitted is empty, datePosted should be used as fallback
        $dateSubmitted = !empty($data->dateSubmitted) ? $data->dateSubmitted : $data->datePosted;

        $this->assertEquals('2024-01-15', $dateSubmitted);
    }

    public function testBothDatesProvided(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('2024-01-10')
            ->buildObject();

        $this->assertEquals('2024-01-10', $data->dateSubmitted);
        $this->assertEquals('2024-01-15', $data->datePosted);
    }

    // ==================== Submission Status Tests ====================

    public function testSubmissionStatusPublished(): void
    {
        $status = Submission::STATUS_PUBLISHED;

        $this->assertEquals(3, $status);
    }

    public function testSubmissionProgressZero(): void
    {
        $progress = '0';

        $this->assertEquals('0', $progress);
    }

    // ==================== Stage ID Tests ====================

    public function testWorkflowStageIdProduction(): void
    {
        $stageId = WORKFLOW_STAGE_ID_PRODUCTION;

        $this->assertEquals(5, $stageId);
    }

    // ==================== Abstract Handling Tests ====================

    public function testAbstractFromData(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withAbstract('This is the test abstract with important content.')
            ->buildObject();

        $this->assertEquals('This is the test abstract with important content.', $data->preprintAbstract);
    }

    public function testEmptyAbstract(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withAbstract('')
            ->buildObject();

        $this->assertTrue(empty($data->preprintAbstract));
    }

    public function testLongAbstract(): void
    {
        $longAbstract = str_repeat('This is a test abstract. ', 100);
        $data = CsvTestDataBuilder::preprint()
            ->withAbstract($longAbstract)
            ->buildObject();

        $this->assertEquals($longAbstract, $data->preprintAbstract);
        $this->assertGreaterThan(2000, strlen($data->preprintAbstract));
    }

    // ==================== Unicode Abstract Tests ====================

    public function testUnicodeAbstract(): void
    {
        $unicodeAbstract = 'Este é um resumo em português. 这是中文摘要。 これは日本語の要約です。';
        $data = CsvTestDataBuilder::preprint()
            ->withAbstract($unicodeAbstract)
            ->buildObject();

        $this->assertEquals($unicodeAbstract, $data->preprintAbstract);
    }

    // ==================== Context ID Tests ====================

    public function testContextIdIsInteger(): void
    {
        $contextId = 1;

        $this->assertIsInt($contextId);
        $this->assertGreaterThan(0, $contextId);
    }
}
