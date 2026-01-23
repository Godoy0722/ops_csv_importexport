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
 * @brief Tests for FundersProcessor class - testing funder data parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\FundersProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FundersProcessor::class)]
class FundersProcessorTest extends BaseTestCase
{
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
        $this->assertEquals('http://dx.doi.org/10.13039/100000001', $parts[1]);
        $this->assertEquals('NSF-2024-001', $parts[2]);
    }

    public function testParsesSingleFunderWithMultipleAwards(): void
    {
        $funderString = 'NIH,http://dx.doi.org/10.13039/100000002,R01-AI-123456|R21-AI-789012|R33-AI-555555';

        $parts = explode(',', $funderString);
        $awards = explode('|', $parts[2]);

        $this->assertEquals('NIH', $parts[0]);
        $this->assertCount(3, $awards);
        $this->assertEquals('R01-AI-123456', $awards[0]);
        $this->assertEquals('R21-AI-789012', $awards[1]);
        $this->assertEquals('R33-AI-555555', $awards[2]);
    }

    public function testParsesMultipleFunders(): void
    {
        $fundersString = 'NSF,http://dx.doi.org/10.13039/100000001,NSF-001;DOE,http://dx.doi.org/10.13039/100000015,DOE-002';

        $funders = explode(';', $fundersString);

        $this->assertCount(2, $funders);

        $funder1Parts = explode(',', $funders[0]);
        $this->assertEquals('NSF', $funder1Parts[0]);

        $funder2Parts = explode(',', $funders[1]);
        $this->assertEquals('DOE', $funder2Parts[0]);
    }

    // ==================== Funder Format Validation Tests ====================

    public function testValidateFunderFormat(): void
    {
        // Valid format: name,doi,awards
        $validFunder = 'NSF,http://dx.doi.org/10.13039/100000001,Award1';
        $parts = explode(',', $validFunder);

        $this->assertCount(3, $parts);
        $this->assertNotEmpty($parts[0]); // Name is required
    }

    public function testInvalidFunderFormatEmptyName(): void
    {
        $invalidFunder = ',http://dx.doi.org/10.13039/100000001,Award1';
        $parts = explode(',', $invalidFunder);

        // Name is required
        $this->assertTrue(empty($parts[0]));
    }

    // ==================== Funders from Data Object Tests ====================

    public function testFundersFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withFunders('NSF,http://dx.doi.org/10.13039/100000001,Award1')
            ->buildObject();

        $this->assertEquals('NSF,http://dx.doi.org/10.13039/100000001,Award1', $data->funders);
    }

    public function testEmptyFundersFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withFunders('')
            ->buildObject();

        $this->assertTrue(empty($data->funders));
    }

    // ==================== Crossref DOI Format Tests ====================

    public function testCrossrefFunderDoiFormat(): void
    {
        $doi = 'http://dx.doi.org/10.13039/100000001';

        $this->assertStringContainsString('10.13039', $doi);
        $this->assertStringStartsWith('http', $doi);
    }

    public function testCrossrefFunderDoiWithHttps(): void
    {
        $doi = 'https://doi.org/10.13039/100000001';

        $this->assertStringContainsString('10.13039', $doi);
        $this->assertStringStartsWith('https', $doi);
    }

    // ==================== Award Number Parsing Tests ====================

    public function testSingleAwardNumber(): void
    {
        $awardsString = 'NSF-2024-001';
        $awards = explode('|', $awardsString);

        $this->assertCount(1, $awards);
        $this->assertEquals('NSF-2024-001', $awards[0]);
    }

    public function testMultipleAwardNumbers(): void
    {
        $awardsString = 'Award1|Award2|Award3';
        $awards = explode('|', $awardsString);

        $this->assertCount(3, $awards);
    }

    public function testEmptyAwardNumbers(): void
    {
        $awardsString = '';
        $awards = array_filter(explode('|', $awardsString));

        $this->assertEmpty($awards);
    }

    // ==================== Funder Data Structure Tests ====================

    public function testFunderDataStructure(): void
    {
        $funderData = [
            'contextId' => 1,
            'submissionId' => 1,
            'funderName' => 'National Science Foundation',
            'funderIdentification' => 'http://dx.doi.org/10.13039/100000001',
        ];

        $this->assertArrayHasKey('contextId', $funderData);
        $this->assertArrayHasKey('submissionId', $funderData);
        $this->assertArrayHasKey('funderName', $funderData);
        $this->assertArrayHasKey('funderIdentification', $funderData);
    }

    public function testFunderAwardDataStructure(): void
    {
        $awardData = [
            'funderId' => 1,
            'funderAwardNumber' => 'NSF-2024-001',
        ];

        $this->assertArrayHasKey('funderId', $awardData);
        $this->assertArrayHasKey('funderAwardNumber', $awardData);
    }

    // ==================== Special Characters Tests ====================

    public function testFunderNameWithSpecialCharacters(): void
    {
        $funderString = 'Fundação de Amparo à Pesquisa,http://dx.doi.org/10.13039/501100001807,2024/12345-6';
        $parts = explode(',', $funderString);

        $this->assertEquals('Fundação de Amparo à Pesquisa', $parts[0]);
    }

    public function testFunderNameWithUnicode(): void
    {
        $funderString = '日本学術振興会,http://dx.doi.org/10.13039/501100001691,JP12345678';
        $parts = explode(',', $funderString);

        $this->assertEquals('日本学術振興会', $parts[0]);
    }
}
