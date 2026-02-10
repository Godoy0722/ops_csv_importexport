<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/KeywordsProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class KeywordsProcessorTest
 *
 * @brief Tests for KeywordsProcessor class - testing keyword parsing and processing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\KeywordsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use APP\publication\Publication;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(KeywordsProcessor::class)]
class KeywordsProcessorTest extends BaseTestCase
{
    // ==================== Keyword Parsing Tests ====================

    public function testParsesSingleKeyword(): void
    {
        $keywordsString = 'machine learning';
        $keywords = array_map('trim', explode(';', $keywordsString));

        $this->assertCount(1, $keywords);
        $this->assertEquals('machine learning', $keywords[0]);
    }

    public function testParsesMultipleKeywords(): void
    {
        $keywordsString = 'machine learning;artificial intelligence;deep learning';
        $keywords = array_map('trim', explode(';', $keywordsString));

        $this->assertCount(3, $keywords);
        $this->assertEquals('machine learning', $keywords[0]);
        $this->assertEquals('artificial intelligence', $keywords[1]);
        $this->assertEquals('deep learning', $keywords[2]);
    }

    public function testTrimsKeywordWhitespace(): void
    {
        $keywordsString = '  machine learning  ;  artificial intelligence  ';
        $keywords = array_map('trim', explode(';', $keywordsString));

        $this->assertEquals('machine learning', $keywords[0]);
        $this->assertEquals('artificial intelligence', $keywords[1]);
    }

    public function testHandlesEmptyKeywordsString(): void
    {
        $keywordsString = '';
        $keywords = array_filter(array_map('trim', explode(';', $keywordsString)));

        $this->assertEmpty($keywords);
    }

    // ==================== Keywords from Data Object Tests ====================

    public function testKeywordsFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withKeywords('test;unit testing;php')
            ->buildObject();

        $this->assertEquals('test;unit testing;php', $data->keywords);
    }

    public function testEmptyKeywordsFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withKeywords('')
            ->buildObject();

        $this->assertTrue(empty($data->keywords));
    }

    // ==================== Unicode Keywords Tests ====================

    public function testHandlesUnicodeKeywords(): void
    {
        $keywordsString = '机器学习;人工智能;深度学习';
        $keywords = array_map('trim', explode(';', $keywordsString));

        $this->assertCount(3, $keywords);
        $this->assertEquals('机器学习', $keywords[0]);
        $this->assertEquals('人工智能', $keywords[1]);
        $this->assertEquals('深度学习', $keywords[2]);
    }

    public function testHandlesPortugueseKeywords(): void
    {
        $keywordsString = 'aprendizado de máquina;inteligência artificial';
        $keywords = array_map('trim', explode(';', $keywordsString));

        $this->assertEquals('aprendizado de máquina', $keywords[0]);
        $this->assertEquals('inteligência artificial', $keywords[1]);
    }

    // ==================== Keywords Data Structure Tests ====================

    public function testKeywordsLocalizedStructure(): void
    {
        $locale = 'en';
        $keywordsList = ['machine learning', 'AI', 'deep learning'];

        $keywordsData = [$locale => $keywordsList];

        $this->assertArrayHasKey($locale, $keywordsData);
        $this->assertEquals($keywordsList, $keywordsData[$locale]);
    }

    public function testMultiLocaleKeywordsStructure(): void
    {
        $keywordsData = [
            'en' => ['machine learning', 'AI'],
            'pt_BR' => ['aprendizado de máquina', 'IA'],
        ];

        $this->assertArrayHasKey('en', $keywordsData);
        $this->assertArrayHasKey('pt_BR', $keywordsData);
        $this->assertCount(2, $keywordsData['en']);
        $this->assertCount(2, $keywordsData['pt_BR']);
    }

    // ==================== Special Characters Tests ====================

    public function testKeywordsWithSpecialCharacters(): void
    {
        $keywordsString = 'C++;.NET;Node.js';
        $keywords = array_map('trim', explode(';', $keywordsString));

        $this->assertEquals('C++', $keywords[0]);
        $this->assertEquals('.NET', $keywords[1]);
        $this->assertEquals('Node.js', $keywords[2]);
    }

    // ==================== process() Integration Tests ====================

    public function testProcessSetsKeywordsForPublication(): void
    {
        $editCallCount = 0;
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editCallCount, &$editParams) {
            $editCallCount++;
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'keywords' => 'machine learning;AI;deep learning',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication);

        $this->assertEquals(1, $editCallCount);
        $this->assertEquals(['en' => ['machine learning', 'AI', 'deep learning']], $editParams['keywords']);
    }

    public function testProcessReturnsEarlyWhenEmptyAndNoBase(): void
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
            'keywords' => '',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication);

        $this->assertEquals(0, $editCallCount);
    }

    public function testProcessCopiesBaseKeywordsWhenEmpty(): void
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
        $basePublication->setData('keywords', ['machine learning', 'AI'], 'en');

        $data = (object) [
            'keywords' => '',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication, $basePublication);

        $this->assertEquals(['en' => ['machine learning', 'AI']], $editParams['keywords']);
    }

    public function testProcessReturnsEarlyWhenBaseKeywordsEmpty(): void
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
        $basePublication->setData('keywords', [], 'en');

        $data = (object) [
            'keywords' => '',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication, $basePublication);

        $this->assertEquals(0, $editCallCount);
    }

    public function testProcessFiltersEmptyKeywords(): void
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
            'keywords' => 'machine learning;;AI;',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication);

        $this->assertEquals(['en' => ['machine learning', 'AI']], $editParams['keywords']);
    }

    public function testProcessReturnsEarlyWhenAllKeywordsFilteredOut(): void
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
            'keywords' => ';;',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication);

        $this->assertEquals(0, $editCallCount);
    }

    public function testProcessCopiesBaseKeywordsFilteringNulls(): void
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
        $basePublication->setData('keywords', ['machine learning', null, '', 'AI'], 'en');

        $data = (object) [
            'keywords' => '',
            'locale' => 'en',
        ];

        KeywordsProcessor::process($data, $publication, $basePublication);

        $this->assertEquals(['en' => ['machine learning', 'AI']], $editParams['keywords']);
    }

    // ==================== processMultiLocale() Tests ====================

    public function testProcessMultiLocaleMergesKeywords(): void
    {
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editParams) {
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);
        $publication->setData('keywords', ['en' => ['machine learning', 'AI']]);

        $data = (object) [
            'keywords' => 'aprendizado de máquina;IA',
            'locale' => 'pt_BR',
        ];

        KeywordsProcessor::processMultiLocale($data, $publication);

        $this->assertEquals([
            'en' => ['machine learning', 'AI'],
            'pt_BR' => ['aprendizado de máquina', 'IA'],
        ], $editParams['keywords']);
    }

    public function testProcessMultiLocaleReturnsEarlyWhenEmpty(): void
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
            'keywords' => '',
            'locale' => 'pt_BR',
        ];

        KeywordsProcessor::processMultiLocale($data, $publication);

        $this->assertEquals(0, $editCallCount);
    }

    public function testProcessMultiLocaleFiltersEmptyKeywords(): void
    {
        $editParams = null;
        $pubRepoMock = $this->mockPublicationRepository();
        $pubRepoMock->shouldReceive('edit')->andReturnUsing(function ($pub, $params) use (&$editParams) {
            $editParams = $params;
            return $pub;
        });

        $publication = new Publication();
        $publication->setId(1);
        $publication->setData('keywords', ['en' => ['AI']]);

        $data = (object) [
            'keywords' => 'IA;;aprendizado;',
            'locale' => 'pt_BR',
        ];

        KeywordsProcessor::processMultiLocale($data, $publication);

        $this->assertEquals([
            'en' => ['AI'],
            'pt_BR' => ['IA', 'aprendizado'],
        ], $editParams['keywords']);
    }

    public function testProcessMultiLocaleReturnsEarlyWhenAllFilteredOut(): void
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
            'keywords' => ';;',
            'locale' => 'pt_BR',
        ];

        KeywordsProcessor::processMultiLocale($data, $publication);

        $this->assertEquals(0, $editCallCount);
    }
}
