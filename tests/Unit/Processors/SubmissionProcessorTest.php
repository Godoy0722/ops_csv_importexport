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
use APP\publication\Publication;
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

    // ==================== Stage ID Tests ====================

    public function testWorkflowStageIdProduction(): void
    {
        $stageId = WORKFLOW_STAGE_ID_PRODUCTION;

        $this->assertEquals(5, $stageId);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessCreatesAndReturnsSubmission(): void
    {
        $submissionRepoMock = $this->mockSubmissionRepository();

        $returnedSubmission = new Submission();
        $returnedSubmission->setId(42);
        $returnedSubmission->setData('contextId', 1);

        $submissionRepoMock->shouldReceive('add')->once()->andReturn(42);
        $submissionRepoMock->shouldReceive('get')->with(42)->andReturn($returnedSubmission);

        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withAbstract('Test abstract')
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('2024-01-10')
            ->buildObject();

        $publication = new Publication();
        $publication->setId(1);

        $server = $this->createMockServer(['id' => 1]);

        $result = SubmissionProcessor::process($data, $publication, $server);

        $this->assertInstanceOf(Submission::class, $result);
        $this->assertEquals(42, $result->getId());
    }

    public function testProcessSetsCorrectSubmissionData(): void
    {
        $capturedSubmission = null;
        $submissionRepoMock = $this->mockSubmissionRepository();

        $submissionRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($submission, $publication, $server) use (&$capturedSubmission) {
                $capturedSubmission = $submission;
                return 1;
            });

        $returnedSubmission = new Submission();
        $returnedSubmission->setId(1);
        $submissionRepoMock->shouldReceive('get')->with(1)->andReturn($returnedSubmission);

        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withAbstract('My test abstract')
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted('2024-01-10')
            ->buildObject();

        $publication = new Publication();
        $publication->setId(1);
        $server = $this->createMockServer(['id' => 5]);

        SubmissionProcessor::process($data, $publication, $server);

        $this->assertNotNull($capturedSubmission);
        $this->assertEquals(5, $capturedSubmission->getData('contextId'));
        $this->assertEquals(Submission::STATUS_PUBLISHED, $capturedSubmission->getData('status'));
        $this->assertEquals('en', $capturedSubmission->getData('locale'));
        $this->assertEquals(WORKFLOW_STAGE_ID_PRODUCTION, $capturedSubmission->getData('stageId'));
        $this->assertEquals('', $capturedSubmission->getData('submissionProgress'));
        $this->assertEquals('My test abstract', $capturedSubmission->getData('abstract', 'en'));
        $this->assertEquals('2024-01-10', $capturedSubmission->getData('dateSubmitted'));
    }

    public function testProcessFallsBackToDatePostedWhenNoDateSubmitted(): void
    {
        $capturedSubmission = null;
        $submissionRepoMock = $this->mockSubmissionRepository();

        $submissionRepoMock->shouldReceive('add')
            ->andReturnUsing(function ($submission) use (&$capturedSubmission) {
                $capturedSubmission = $submission;
                return 1;
            });

        $returnedSubmission = new Submission();
        $returnedSubmission->setId(1);
        $submissionRepoMock->shouldReceive('get')->with(1)->andReturn($returnedSubmission);

        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withDatePosted('2024-01-15')
            ->withDateSubmitted(null)
            ->buildObject();

        $publication = new Publication();
        $publication->setId(1);
        $server = $this->createMockServer(['id' => 1]);

        SubmissionProcessor::process($data, $publication, $server);

        $this->assertEquals('2024-01-15', $capturedSubmission->getData('dateSubmitted'));
    }

    // ==================== setCurrentPublicationId() Integration Tests ====================

    public function testSetCurrentPublicationIdCallsEdit(): void
    {
        $editParams = null;
        $submissionRepoMock = $this->mockSubmissionRepository();
        $submissionRepoMock->shouldReceive('edit')
            ->andReturnUsing(function ($submission, $params) use (&$editParams) {
                $editParams = $params;
            });

        $submission = new Submission();
        $submission->setId(1);

        SubmissionProcessor::setCurrentPublicationId($submission, 99);

        $this->assertEquals(['currentPublicationId' => 99], $editParams);
    }
}
