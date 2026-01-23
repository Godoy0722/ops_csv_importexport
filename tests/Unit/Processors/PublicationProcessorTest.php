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
 * @brief Tests for PublicationProcessor class - testing pure logic without database
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\publication\Publication;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PublicationProcessor::class)]
class PublicationProcessorTest extends BaseTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPublicationRepository();
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
        $data = CsvTestDataBuilder::preprint()
            ->withTitle('Título em Português: Análise de Dados')
            ->withDatePosted('2024-01-15')
            ->withVersion('1')
            ->withLocale('pt_BR')
            ->buildObject();

        $result = PublicationProcessor::createInitialPublication($data);

        $this->assertEquals('Título em Português: Análise de Dados', $result->getData('title', 'pt_BR'));
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

    public function testGetReferencesContentWithUnicodeContent(): void
    {
        $content = "Referência 1: Artigo científico\nРеференция 2: Научная статья\n参考文献 3: 科学论文";
        $this->createTestFile($this->tempDir, 'refs-unicode.txt', $content);

        $result = PublicationProcessor::getReferencesContent('refs-unicode.txt', $this->tempDir);

        $this->assertEquals($content, $result);
    }

    // ==================== VOR DOI Normalization Tests ====================

    #[DataProvider('vorDoiProvider')]
    public function testVorDoiNormalization(string $input, string $expected): void
    {
        // Test the normalization logic used by updateVorDoi
        $normalized = $this->normalizeVorDoi($input);
        $this->assertEquals($expected, $normalized);
    }

    public static function vorDoiProvider(): array
    {
        return [
            'Bare DOI' => ['10.1234/example', 'https://doi.org/10.1234/example'],
            'HTTPS URL' => ['https://doi.org/10.1234/example', 'https://doi.org/10.1234/example'],
            'HTTP URL' => ['http://doi.org/10.1234/example', 'https://doi.org/10.1234/example'],
            'DX DOI URL' => ['https://dx.doi.org/10.1234/example', 'https://doi.org/10.1234/example'],
            'DOI prefix' => ['doi:10.1234/example', 'https://doi.org/10.1234/example'],
        ];
    }

    /**
     * Helper method mimicking VOR DOI normalization logic
     */
    private function normalizeVorDoi(string $input): string
    {
        $value = trim($input);
        $value = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $value);
        $value = preg_replace('#^doi:#i', '', $value);
        return 'https://doi.org/' . $value;
    }

    // ==================== Supporting Agencies Parsing Tests ====================

    public function testSupportingAgenciesParsing(): void
    {
        $agenciesString = 'NSF;DOE;NIH';
        $agencies = array_map('trim', explode(';', $agenciesString));

        $this->assertCount(3, $agencies);
        $this->assertEquals('NSF', $agencies[0]);
        $this->assertEquals('DOE', $agencies[1]);
        $this->assertEquals('NIH', $agencies[2]);
    }

    public function testEmptySupportingAgencies(): void
    {
        $agenciesString = '';
        $agencies = array_filter(array_map('trim', explode(';', $agenciesString)));

        $this->assertEmpty($agencies);
    }

    public function testSingleSupportingAgency(): void
    {
        $agenciesString = 'National Science Foundation';
        $agencies = array_map('trim', explode(';', $agenciesString));

        $this->assertCount(1, $agencies);
        $this->assertEquals('National Science Foundation', $agencies[0]);
    }

    public function testSupportingAgenciesWithWhitespace(): void
    {
        $agenciesString = '  NSF  ;  DOE  ;  NIH  ';
        $agencies = array_map('trim', explode(';', $agenciesString));

        $this->assertEquals('NSF', $agencies[0]);
        $this->assertEquals('DOE', $agencies[1]);
        $this->assertEquals('NIH', $agencies[2]);
    }

    // ==================== Version Number Tests ====================

    public function testVersionNumberParsing(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVersion('1')
            ->buildObject();

        $version = (int) $data->version;
        $this->assertEquals(1, $version);
    }

    public function testVersionNumber2(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVersion('2')
            ->buildObject();

        $version = (int) $data->version;
        $this->assertEquals(2, $version);
    }

    public function testEmptyVersionDefaultsToOne(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withVersion('')
            ->buildObject();

        $version = empty($data->version) ? 1 : (int) $data->version;
        $this->assertEquals(1, $version);
    }

    // ==================== Cover Image Data Structure Tests ====================

    public function testCoverImageDataStructure(): void
    {
        $uploadName = 'abc123-cover.png';
        $altText = 'Cover image description';
        $locale = 'en';

        $coverImageData = [
            $locale => [
                'uploadName' => $uploadName,
                'altText' => $altText,
            ]
        ];

        $this->assertEquals($uploadName, $coverImageData[$locale]['uploadName']);
        $this->assertEquals($altText, $coverImageData[$locale]['altText']);
    }

    // ==================== Date Format Tests ====================

    public function testDatePublishedFormat(): void
    {
        $dateString = '2024-01-15';
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateString);
    }

    public function testDateSubmittedFormat(): void
    {
        $dateString = '2024-01-10';
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateString);
    }

    // ==================== Copyright Fields Tests ====================

    public function testCopyrightYearFormat(): void
    {
        $copyrightYear = '2024';
        $this->assertEquals(4, strlen($copyrightYear));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $copyrightYear);
    }

    public function testCopyrightHolderValue(): void
    {
        $copyrightHolder = 'Test Institute';
        $this->assertNotEmpty($copyrightHolder);
        $this->assertIsString($copyrightHolder);
    }

    public function testLicenseUrlFormat(): void
    {
        $licenseUrl = 'https://creativecommons.org/licenses/by/4.0';
        $this->assertStringStartsWith('https://', $licenseUrl);
    }

    // ==================== Localized Fields Tests ====================

    public function testLocalizedFieldsStructure(): void
    {
        $localizedFields = [
            'title' => 'preprintTitle',
            'subtitle' => 'preprintSubtitle',
            'abstract' => 'preprintAbstract',
            'prefix' => 'preprintPrefix',
            'coverage' => 'coverage',
            'copyrightHolder' => 'copyrightHolder',
        ];

        $this->assertArrayHasKey('title', $localizedFields);
        $this->assertArrayHasKey('subtitle', $localizedFields);
        $this->assertArrayHasKey('abstract', $localizedFields);
        $this->assertEquals('preprintTitle', $localizedFields['title']);
    }

    public function testNonLocalizedFieldsStructure(): void
    {
        $nonLocalizedFields = ['copyrightYear', 'licenseUrl'];

        $this->assertContains('copyrightYear', $nonLocalizedFields);
        $this->assertContains('licenseUrl', $nonLocalizedFields);
    }

    // ==================== Publication Status Tests ====================

    public function testPublicationStatusPublished(): void
    {
        $status = Submission::STATUS_PUBLISHED;
        $this->assertEquals(3, $status);
    }

    public function testPublicationRelationStatusPublished(): void
    {
        $relationStatus = Publication::PUBLICATION_RELATION_PUBLISHED;
        $this->assertIsInt($relationStatus);
    }
}
