<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/PublicationProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationProcessorTest
 *
 * @brief Tests for PublicationProcessor class
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\classes\validations\InvalidRowValidations;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\file\PublicFileManager;
use Mockery;
use PKP\file\FileManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

#[CoversClass(PublicationProcessor::class)]
class PublicationProcessorTest extends BaseTestCase
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

    // ==================== createInitialPublication Tests ====================

    public function testCreateInitialPublicationWithAllFields(): void
    {
        $this->mockPublicationRepository();

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Test Preprint Title')
            ->withDatePosted('2024-01-15')
            ->withVersion('1')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertInstanceOf(Publication::class, $result);
        $this->assertEquals(1, $result->getData('version'));
        $this->assertEquals(Submission::STATUS_PUBLISHED, $result->getData('status'));
        $this->assertEquals('2024-01-15', $result->getData('datePublished'));
        $this->assertEquals('Test Preprint Title', $result->getData('title', 'en'));
    }

    public function testCreateInitialPublicationDefaultsVersionToOne(): void
    {
        $this->mockPublicationRepository();

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Test Preprint')
            ->withDatePosted('2024-01-15')
            ->withVersion('')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertEquals(1, $result->getData('version'));
    }

    public function testCreateInitialPublicationWithVersion2(): void
    {
        $this->mockPublicationRepository();

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Test Preprint v2')
            ->withDatePosted('2024-02-15')
            ->withVersion('2')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertEquals(2, $result->getData('version'));
    }

    public function testCreateInitialPublicationWithUnicodeTitle(): void
    {
        $this->mockPublicationRepository();

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Título em Português: Análise de Dados')
            ->withDatePosted('2024-01-15')
            ->withVersion('1')
            ->withLocale('pt_BR')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertEquals('Título em Português: Análise de Dados', $result->getData('title', 'pt_BR'));
    }

    public function testCreateInitialPublicationWithHighVersion(): void
    {
        $this->mockPublicationRepository();

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Test')
            ->withDatePosted('2024-01-15')
            ->withVersion('10')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertEquals(10, $result->getData('version'));
    }

    public function testCreateInitialPublicationPreservesLocale(): void
    {
        $this->mockPublicationRepository();

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Título Teste')
            ->withDatePosted('2024-01-15')
            ->withVersion('1')
            ->withLocale('pt_BR')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertEquals('Título Teste', $result->getData('title', 'pt_BR'));
        $this->assertNull($result->getData('title', 'en'));
    }

    // ==================== getReferencesContent Tests ====================

    public function testGetReferencesContentReadsFile(): void
    {
        $content = "Reference 1\nReference 2\nReference 3";
        $this->createTestFile($this->tempDir, 'references.txt', $content);

        $result = PublicationProcessor::getReferencesContent('references.txt', $this->tempDir);

        $this->assertEquals($content, $result);
    }

    public function testGetReferencesContentWithNonExistentFile(): void
    {
        $result = @PublicationProcessor::getReferencesContent('nonexistent.txt', $this->tempDir);

        $this->assertFalse($result);
    }

    public function testGetReferencesContentWithEmptyFile(): void
    {
        $this->createTestFile($this->tempDir, 'empty.txt', '');

        $result = PublicationProcessor::getReferencesContent('empty.txt', $this->tempDir);

        $this->assertEquals('', $result);
    }

    // ==================== updateCoverage Tests ====================

    public function testUpdateCoverageSetsDataOnPublication(): void
    {
        $publication = new Publication();
        $publication->setId(1);

        PublicationProcessor::updateCoverage($publication, 'Global study', 'en');

        $this->assertEquals('Global study', $publication->getData('coverage', 'en'));
    }

    public function testUpdateCoverageWithDifferentLocale(): void
    {
        $publication = new Publication();
        $publication->setId(1);

        PublicationProcessor::updateCoverage($publication, 'Estudo global', 'pt_BR');

        $this->assertEquals('Estudo global', $publication->getData('coverage', 'pt_BR'));
    }

    // ==================== setCoverImage Tests ====================

    public function testSetCoverImageSetsDataOnPublication(): void
    {
        $publication = new Publication();
        $publication->setId(1);

        $coverImageData = [
            'dateUploaded' => '2024-01-15 10:30:00',
            'uploadName' => 'abc123-cover.png',
            'altText' => 'Cover image description',
        ];

        PublicationProcessor::setCoverImage($publication, $coverImageData, 'en');

        $result = $publication->getData('coverImage', 'en');
        $this->assertEquals('abc123-cover.png', $result['uploadName']);
        $this->assertEquals('Cover image description', $result['altText']);
    }

    // ==================== updateSectionId Tests ====================

    public function testUpdateSectionIdSetsDataOnPublication(): void
    {
        $publication = new Publication();
        $publication->setId(1);

        PublicationProcessor::updateSectionId($publication, 42);

        $this->assertEquals(42, $publication->getData('sectionId'));
    }

    // ==================== updateCoverImage Tests ====================

    public function testUpdateCoverImageSetsCorrectStructure(): void
    {
        $publication = new Publication();
        $publication->setId(1);
        $data = (object) [
            'coverImageAltText' => 'Test alt text',
            'locale' => 'en',
        ];

        PublicationProcessor::updateCoverImage($publication, $data, 'random-cover.png');

        $result = $publication->getData('coverImage', 'en');
        $this->assertEquals('random-cover.png', $result['uploadName']);
        $this->assertEquals('Test alt text', $result['altText']);
        $this->assertArrayHasKey('dateUploaded', $result);
    }

    public function testUpdateCoverImageWithNullAltText(): void
    {
        $publication = new Publication();
        $publication->setId(1);
        $data = (object) [
            'coverImageAltText' => null,
            'locale' => 'en',
        ];

        PublicationProcessor::updateCoverImage($publication, $data, 'cover.jpg');

        $result = $publication->getData('coverImage', 'en');
        $this->assertEquals('', $result['altText']);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessUpdatesPublicationWithAllData(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $currentPub = new Publication();
        $currentPub->setId(5);

        $returnedPub = new Publication();
        $returnedPub->setId(5);
        $returnedPub->setData('title', 'Updated', 'en');

        $pubRepoMock->shouldReceive('get')->with(5)->andReturn($returnedPub);

        $submission = $this->createMockSubmission([
            'id' => 1,
            'contextId' => 1,
            'currentPublication' => $currentPub,
        ]);

        $server = $this->createMockServer(['id' => 1]);

        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withTitle('Test Title')
            ->withSubtitle('Test Subtitle')
            ->withAbstract('Test Abstract')
            ->withPrefix('QC')
            ->withCopyrightHolder('Test Holder')
            ->withCopyrightYear('2024')
            ->withLicenseUrl('https://creativecommons.org/licenses/by/4.0')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = PublicationProcessor::process($submission, $data, $server, $this->tempDir);

        $this->assertInstanceOf(Publication::class, $result);
        $this->assertEquals(5, $result->getId());
    }

    public function testProcessSetsSubtitleWhenProvided(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $currentPub = new Publication();
        $currentPub->setId(1);

        $pubRepoMock->shouldReceive('get')->with(1)->andReturn($currentPub);

        $submission = $this->createMockSubmission([
            'id' => 1,
            'currentPublication' => $currentPub,
        ]);

        $server = $this->createMockServer(['id' => 1]);

        $data = (object) [
            'locale' => 'en',
            'preprintSubtitle' => 'My Subtitle',
            'preprintAbstract' => '',
            'preprintPrefix' => '',
            'references' => '',
            'copyrightHolder' => 'Holder',
            'copyrightYear' => '2024',
            'licenseUrl' => '',
        ];

        PublicationProcessor::process($submission, $data, $server, $this->tempDir);

        $this->assertEquals('My Subtitle', $currentPub->getData('subtitle', 'en'));
    }

    public function testProcessSetsReferencesFromFile(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $currentPub = new Publication();
        $currentPub->setId(1);

        $pubRepoMock->shouldReceive('get')->with(1)->andReturn($currentPub);

        $submission = $this->createMockSubmission([
            'id' => 1,
            'currentPublication' => $currentPub,
        ]);

        $server = $this->createMockServer(['id' => 1]);

        $this->createTestFile($this->tempDir, 'refs.txt', "Ref 1\nRef 2");

        $data = (object) [
            'locale' => 'en',
            'preprintSubtitle' => '',
            'preprintAbstract' => '',
            'preprintPrefix' => '',
            'references' => 'refs.txt',
            'copyrightHolder' => '',
            'copyrightYear' => '',
            'licenseUrl' => '',
        ];

        PublicationProcessor::process($submission, $data, $server, $this->tempDir);

        $this->assertEquals("Ref 1\nRef 2", $currentPub->getData('citationsRaw'));
    }

    public function testProcessUsesContextLicenseWhenCopyrightNull(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $currentPub = new Publication();
        $currentPub->setId(1);

        $pubRepoMock->shouldReceive('get')->with(1)->andReturn($currentPub);

        $submission = $this->getMockBuilder(Submission::class)
            ->onlyMethods(['getCurrentPublication', '_getContextLicenseFieldValue'])
            ->getMock();
        $submission->setId(1);
        $submission->method('getCurrentPublication')->willReturn($currentPub);
        $submission->method('_getContextLicenseFieldValue')
            ->willReturnCallback(function ($request, $field, $pub) {
                return match($field) {
                    Submission::PERMISSIONS_FIELD_COPYRIGHT_HOLDER => 'Context Holder',
                    Submission::PERMISSIONS_FIELD_COPYRIGHT_YEAR => '2025',
                    Submission::PERMISSIONS_FIELD_LICENSE_URL => 'https://example.com/license',
                    default => '',
                };
            });

        $server = $this->createMockServer(['id' => 1]);

        $data = (object) [
            'locale' => 'en',
            'preprintSubtitle' => '',
            'preprintAbstract' => '',
            'preprintPrefix' => '',
            'references' => '',
            'copyrightHolder' => null,
            'copyrightYear' => null,
            'licenseUrl' => null,
        ];

        PublicationProcessor::process($submission, $data, $server, $this->tempDir);

        $this->assertEquals('Context Holder', $currentPub->getData('copyrightHolder', 'en'));
        $this->assertEquals('2025', $currentPub->getData('copyrightYear'));
        $this->assertEquals('https://example.com/license', $currentPub->getData('licenseUrl'));
    }

    // ==================== updatePrimaryContactId() Integration Tests ====================

    public function testUpdatePrimaryContactIdCallsDaoUpdate(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(1);

        PublicationProcessor::updatePrimaryContactId($publication, 42);

        $this->assertEquals(42, $publication->getData('primaryContactId'));
    }

    // ==================== processVersionedPublication() Integration Tests ====================

    public function testProcessVersionedPublicationUpdatesFields(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('title', 'Base Title', 'en');
        $basePublication->setData('datePublished', '2024-01-01');

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('Updated Title')
            ->withDatePosted('2024-06-15')
            ->withCopyrightYear('2025')
            ->withLicenseUrl('https://example.com/new-license')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertEquals(2, $result->getData('version'));
        $this->assertEquals(Submission::STATUS_PUBLISHED, $result->getData('status'));
        $this->assertEquals('2024-06-15', $result->getData('datePublished'));
        $this->assertEquals('Updated Title', $result->getData('title', 'en'));
        $this->assertEquals('2025', $result->getData('copyrightYear'));
        $this->assertEquals('https://example.com/new-license', $result->getData('licenseUrl'));
    }

    public function testProcessVersionedPublicationFallsBackToBaseDate(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('datePublished', '2024-01-01');

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('Title')
            ->withDatePosted('')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertEquals('2024-01-01', $result->getData('datePublished'));
    }

    public function testProcessVersionedPublicationCopiesLocalizedFieldsFromBase(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);

        /** @var Publication|\PHPUnit\Framework\MockObject\MockObject $basePublication */
        $basePublication = $this->getMockBuilder(Publication::class)
            ->onlyMethods(['getLocalizedData'])
            ->getMock();
        $basePublication->setId(1);
        $basePublication->setData('title', 'Base Title', 'en');
        $basePublication->setData('abstract', 'Base Abstract', 'en');
        $basePublication->setData('copyrightYear', '2024');
        $basePublication->setData('licenseUrl', 'https://example.com/license');
        $basePublication->method('getLocalizedData')->willReturnCallback(
            function (string $field, ?string $locale = null) use ($basePublication) {
                return $basePublication->getData($field, $locale);
            }
        );

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('')
            ->withAbstract('')
            ->withDatePosted('2024-06-15')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertEquals('Base Title', $result->getData('title', 'en'));
        $this->assertEquals('Base Abstract', $result->getData('abstract', 'en'));
        $this->assertEquals('2024', $result->getData('copyrightYear'));
        $this->assertEquals('https://example.com/license', $result->getData('licenseUrl'));
    }

    public function testProcessVersionedPublicationSetsDoiWhenProvided(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);
        $publication->setData('submissionId', 1);

        $basePublication = new Publication();
        $basePublication->setId(1);

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $subRepoMock = $this->mockSubmissionRepository();
        $submission = new \APP\submission\Submission();
        $submission->setData('contextId', 1);
        $subRepoMock->shouldReceive('get')->with(1)->andReturn($submission);

        // Mock DOI repository for setStoredPubId
        $capturedDoi = null;
        $doiRepoMock = Mockery::mock(\APP\doi\Repository::class)->makePartial();
        $doiRepoMock->shouldReceive('newDataObject')->andReturnUsing(function ($params) use (&$capturedDoi) {
            $capturedDoi = $params['doi'];
            $doiObj = new \PKP\doi\Doi();
            $doiObj->setData('doi', $params['doi']);
            $doiObj->setData('contextId', $params['contextId']);
            return $doiObj;
        });
        $doiRepoMock->shouldReceive('add')->andReturn(99);
        app()->instance(\APP\doi\Repository::class, $doiRepoMock);

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('Title')
            ->withDoi('10.1234/test-v2')
            ->withDatePosted('2024-06-15')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertEquals('10.1234/test-v2', $capturedDoi);
        $this->assertEquals(99, $result->getData('doiId'));
    }

    public function testProcessVersionedPublicationSetsReferencesFromFile(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $this->createTestFile($this->tempDir, 'refs.txt', "Ref A\nRef B");

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('Title')
            ->withReferences('refs.txt')
            ->withDatePosted('2024-06-15')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertEquals("Ref A\nRef B", $result->getData('citationsRaw'));
    }

    public function testProcessVersionedPublicationCopiesReferencesFromBase(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('citationsRaw', "Base Ref 1\nBase Ref 2");

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('Title')
            ->withReferences('')
            ->withDatePosted('2024-06-15')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertEquals("Base Ref 1\nBase Ref 2", $result->getData('citationsRaw'));
    }

    // ==================== createPublicationVersion() Integration Tests ====================

    public function testCreatePublicationVersionCreatesNewPublication(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $returnedPub = new Publication();
        $returnedPub->setId(50);

        $pubRepoMock->dao->shouldReceive('insert')->andReturn(50);
        $pubRepoMock->shouldReceive('get')->with(50)->andReturn($returnedPub);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('submissionId', 10);
        $basePublication->setData('title', 'Base Title', 'en');
        $basePublication->setData('copyrightYear', '2024');

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withLocale('en')
            ->buildObject();

        $server = $this->createMockServer(['id' => 1]);

        $result = PublicationProcessor::createPublicationVersion($basePublication, $data, $server);

        $this->assertInstanceOf(Publication::class, $result);
        $this->assertEquals(50, $result->getId());
    }

    public function testCreatePublicationVersionCopiesBaseFields(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $capturedPub = null;
        $pubRepoMock->dao->shouldReceive('insert')
            ->andReturnUsing(function ($pub) use (&$capturedPub) {
                $capturedPub = $pub;
                return 50;
            });

        $returnedPub = new Publication();
        $returnedPub->setId(50);
        $pubRepoMock->shouldReceive('get')->with(50)->andReturn($returnedPub);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('submissionId', 10);
        $basePublication->setData('title', 'Base Title', 'en');
        $basePublication->setData('subtitle', 'Base Subtitle', 'en');
        $basePublication->setData('abstract', 'Base Abstract', 'en');
        $basePublication->setData('copyrightYear', '2024');
        $basePublication->setData('licenseUrl', 'https://example.com/license');
        $basePublication->setData('citationsRaw', "Ref 1\nRef 2");

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withLocale('en')
            ->buildObject();

        $server = $this->createMockServer(['id' => 1]);

        PublicationProcessor::createPublicationVersion($basePublication, $data, $server);

        $this->assertNotNull($capturedPub);
        $this->assertEquals(10, $capturedPub->getData('submissionId'));
        $this->assertEquals(2, $capturedPub->getData('version'));
        $this->assertEquals(Submission::STATUS_PUBLISHED, $capturedPub->getData('status'));
        $this->assertEquals('Base Title', $capturedPub->getData('title', 'en'));
        $this->assertEquals('Base Subtitle', $capturedPub->getData('subtitle', 'en'));
        $this->assertEquals('2024', $capturedPub->getData('copyrightYear'));
        $this->assertEquals("Ref 1\nRef 2", $capturedPub->getData('citationsRaw'));
    }

    // ==================== processMultiLocalePublication() Integration Tests ====================

    public function testProcessMultiLocalePublicationSetsLocalizedFields(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(1);

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Título em Português')
            ->withSubtitle('Subtítulo')
            ->withAbstract('Resumo do artigo')
            ->withLocale('pt_BR')
            ->buildObject();

        $server = $this->createMockServer(['id' => 1]);

        $result = PublicationProcessor::processMultiLocalePublication($publication, $data, $server);

        $this->assertEquals('Título em Português', $result->getData('title', 'pt_BR'));
        $this->assertEquals('Subtítulo', $result->getData('subtitle', 'pt_BR'));
        $this->assertEquals('<p>Resumo do artigo</p>', $result->getData('abstract', 'pt_BR'));
    }

    public function testProcessMultiLocalePublicationSkipsEmptyFields(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(1);
        $publication->setData('title', 'Original Title', 'en');

        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Título')
            ->withSubtitle('')
            ->withAbstract('')
            ->withLocale('pt_BR')
            ->buildObject();

        $server = $this->createMockServer(['id' => 1]);

        $result = PublicationProcessor::processMultiLocalePublication($publication, $data, $server);

        $this->assertEquals('Título', $result->getData('title', 'pt_BR'));
        $this->assertNull($result->getData('subtitle', 'pt_BR'));
        $this->assertEquals('Original Title', $result->getData('title', 'en'));
    }

    // ==================== uploadCoverImage() Integration Tests ====================

    public function testUploadCoverImageReturnsUploadName(): void
    {
        $this->createTestFile($this->tempDir, 'cover.png', 'fake png content');

        $publicFileManager = Mockery::mock(PublicFileManager::class);
        $publicFileManager->shouldReceive('getContextFilesPath')->with(1)->andReturn($this->tempDir);

        $fileManager = Mockery::mock(FileManager::class);
        $fileManager->shouldReceive('copyFile')->andReturn(true);

        $data = (object) [
            'coverImageFilename' => 'cover.png',
        ];

        $result = PublicationProcessor::uploadCoverImage($data, 1, $this->tempDir, $publicFileManager, $fileManager);

        $this->assertIsString($result);
        $this->assertStringEndsWith('cover.png', $result);
        // Should have a random prefix
        $this->assertGreaterThan(strlen('cover.png'), strlen($result));
    }

    public function testUploadCoverImageThrowsOnCopyFailure(): void
    {
        $this->createTestFile($this->tempDir, 'cover.png', 'fake png content');

        $publicFileManager = Mockery::mock(PublicFileManager::class);
        $publicFileManager->shouldReceive('getContextFilesPath')->with(1)->andReturn($this->tempDir);

        $fileManager = Mockery::mock(FileManager::class);
        $fileManager->shouldReceive('copyFile')->andReturn(false);

        $data = (object) [
            'coverImageFilename' => 'cover.png',
        ];

        $this->expectException(\Exception::class);

        PublicationProcessor::uploadCoverImage($data, 1, $this->tempDir, $publicFileManager, $fileManager);
    }

    public function testUploadCoverImageSanitizesFilename(): void
    {
        $this->createTestFile($this->tempDir, 'My Cover Image.png', 'fake png content');

        $publicFileManager = Mockery::mock(PublicFileManager::class);
        $publicFileManager->shouldReceive('getContextFilesPath')->with(1)->andReturn($this->tempDir);

        $fileManager = Mockery::mock(FileManager::class);
        $fileManager->shouldReceive('copyFile')->andReturn(true);

        $data = (object) [
            'coverImageFilename' => 'My Cover Image.png',
        ];

        $result = PublicationProcessor::uploadCoverImage($data, 1, $this->tempDir, $publicFileManager, $fileManager);

        // Spaces/underscores should be replaced with hyphens and lowercased
        $this->assertStringNotContainsString(' ', $result);
        $this->assertStringContainsString('.png', $result);
    }

    // ==================== updateVorDoi() Integration Tests ====================

    public function testUpdateVorDoiSetsNormalizedDoiAndRelationStatus(): void
    {
        $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(1);

        PublicationProcessor::updateVorDoi($publication, '10.1234/example');

        $this->assertEquals('https://doi.org/10.1234/example', $publication->getData('vorDoi'));
        $this->assertEquals(Publication::PUBLICATION_RELATION_PUBLISHED, $publication->getData('relationStatus'));
    }

    public function testUpdateVorDoiNormalizesFullUrl(): void
    {
        $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(1);

        PublicationProcessor::updateVorDoi($publication, 'https://doi.org/10.1234/example');

        $this->assertEquals('https://doi.org/10.1234/example', $publication->getData('vorDoi'));
    }

    // ==================== processSupportingAgencies() Integration Tests ====================

    public function testProcessSupportingAgenciesSetsAgencies(): void
    {
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editParams) {
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'supportingAgencies' => 'NSF;DOE;NIH',
            'locale' => 'en',
        ];

        PublicationProcessor::processSupportingAgencies($data, $publication);

        $this->assertEquals(['en' => ['NSF', 'DOE', 'NIH']], $editParams['supportingAgencies']);
    }

    public function testProcessSupportingAgenciesReturnsEarlyWhenEmpty(): void
    {
        $editCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub) use (&$editCallCount) {
            $editCallCount++;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'supportingAgencies' => '',
            'locale' => 'en',
        ];

        PublicationProcessor::processSupportingAgencies($data, $publication);

        $this->assertEquals(0, $editCallCount);
    }

    public function testProcessSupportingAgenciesCopiesFromBaseWhenDataEmpty(): void
    {
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editParams) {
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('supportingAgencies', ['en' => ['NSF', 'DOE']]);

        $data = (object) [
            'supportingAgencies' => '',
            'locale' => 'en',
        ];

        PublicationProcessor::processSupportingAgencies($data, $publication, $basePublication);

        $this->assertEquals(['en' => ['NSF', 'DOE']], $editParams['supportingAgencies']);
    }

    public function testProcessSupportingAgenciesReturnsEarlyWhenBaseEmpty(): void
    {
        $editCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub) use (&$editCallCount) {
            $editCallCount++;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);
        $basePublication->setData('supportingAgencies', []);

        $data = (object) [
            'supportingAgencies' => '',
            'locale' => 'en',
        ];

        PublicationProcessor::processSupportingAgencies($data, $publication, $basePublication);

        $this->assertEquals(0, $editCallCount);
    }

    // ==================== processSupportingAgenciesMultiLocale() Integration Tests ====================

    public function testProcessSupportingAgenciesMultiLocaleMergesLocales(): void
    {
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editParams) {
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);
        $publication->setData('supportingAgencies', ['en' => ['NSF', 'DOE']]);

        $data = (object) [
            'supportingAgencies' => 'CNPq;FAPESP',
            'locale' => 'pt_BR',
        ];

        PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);

        $this->assertEquals([
            'en' => ['NSF', 'DOE'],
            'pt_BR' => ['CNPq', 'FAPESP'],
        ], $editParams['supportingAgencies']);
    }

    public function testProcessSupportingAgenciesMultiLocaleReturnsEarlyWhenEmpty(): void
    {
        $editCallCount = 0;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub) use (&$editCallCount) {
            $editCallCount++;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'supportingAgencies' => '',
            'locale' => 'pt_BR',
        ];

        PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);

        $this->assertEquals(0, $editCallCount);
    }

    public function testProcessSupportingAgenciesMultiLocaleWithNullExisting(): void
    {
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editParams) {
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'supportingAgencies' => 'FAPESP',
            'locale' => 'pt_BR',
        ];

        PublicationProcessor::processSupportingAgenciesMultiLocale($data, $publication);

        $this->assertEquals([
            'pt_BR' => ['FAPESP'],
        ], $editParams['supportingAgencies']);
    }

    // ==================== DOI Handling Tests ====================

    public function testProcessSetsDoiWhenProvided(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $currentPub = new Publication();
        $currentPub->setId(1);
        $currentPub->setData('submissionId', 1);

        $pubRepoMock->shouldReceive('get')->with(1)->andReturn($currentPub);

        $submission = $this->createMockSubmission([
            'id' => 1,
            'contextId' => 1,
            'currentPublication' => $currentPub,
        ]);

        $server = $this->createMockServer(['id' => 1]);

        $subRepoMock = $this->mockSubmissionRepository();
        $subRepoMock->shouldReceive('get')->with(1)->andReturn($submission);

        $capturedDoi = null;
        $doiRepoMock = Mockery::mock(\APP\doi\Repository::class)->makePartial();
        $doiRepoMock->shouldReceive('newDataObject')->andReturnUsing(function ($params) use (&$capturedDoi) {
            $capturedDoi = $params['doi'];
            $doiObj = new \PKP\doi\Doi();
            $doiObj->setData('doi', $params['doi']);
            $doiObj->setData('contextId', $params['contextId']);
            return $doiObj;
        });
        $doiRepoMock->shouldReceive('add')->andReturn(42);
        app()->instance(\APP\doi\Repository::class, $doiRepoMock);

        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withTitle('Test Title')
            ->withDoi('10.5555/test-doi')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = PublicationProcessor::process($submission, $data, $server, $this->tempDir);

        $this->assertEquals('10.5555/test-doi', $capturedDoi, 'DOI should be properly stored in the database');
        $this->assertEquals(42, $result->getData('doiId'), 'Publication should reference the stored DOI ID');
    }

    public function testProcessDoesNotSetDoiWhenEmpty(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $currentPub = new Publication();
        $currentPub->setId(1);

        $pubRepoMock->shouldReceive('get')->with(1)->andReturn($currentPub);

        $submission = $this->createMockSubmission([
            'id' => 1,
            'currentPublication' => $currentPub,
        ]);

        $server = $this->createMockServer(['id' => 1]);

        $data = CsvTestDataBuilder::preprint()
            ->withLocale('en')
            ->withTitle('Test Title')
            ->withDoi('')
            ->withDatePosted('2024-01-15')
            ->buildObject();

        $result = PublicationProcessor::process($submission, $data, $server, $this->tempDir);

        $this->assertNull($result->getData('doiId'), 'DOI should NOT be added when doi column is empty');
    }

    public function testProcessVersionedPublicationDoesNotSetDoiWhenEmpty(): void
    {
        $pubRepoMock = $this->mockPublicationRepository();

        $publication = new Publication();
        $publication->setId(2);

        $basePublication = new Publication();
        $basePublication->setId(1);

        $pubRepoMock->shouldReceive('get')->with(2)->andReturn($publication);

        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->withTitle('Title')
            ->withDoi('')
            ->withDatePosted('2024-06-15')
            ->withLocale('en')
            ->buildObject();

        $result = PublicationProcessor::processVersionedPublication($publication, $data, $basePublication, $this->tempDir);

        $this->assertNull($result->getData('doiId'), 'DOI should NOT be added when doi column is empty');
    }

    // ==================== uploadCoverImage Validation Return Path ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUploadCoverImageThrowsWhenValidationReturnsError(): void
    {
        $validationMock = Mockery::mock('overload:' . InvalidRowValidations::class);
        $validationMock->shouldReceive('validateCoverImageIsValid')
            ->andReturn('Invalid image format error');

        $publicFileManager = Mockery::mock(PublicFileManager::class);
        $fileManager = Mockery::mock(FileManager::class);

        $data = (object) ['coverImageFilename' => 'cover.bmp'];

        $this->expectException(\Exception::class);
        PublicationProcessor::uploadCoverImage($data, 1, '/tmp', $publicFileManager, $fileManager);
    }
}
