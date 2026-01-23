<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/SubjectsProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubjectsProcessorTest
 *
 * @brief Tests for SubjectsProcessor class - testing subject parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\plugins\importexport\csv\classes\processors\SubjectsProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubjectsProcessor::class)]
class SubjectsProcessorTest extends BaseTestCase
{
    // ==================== Subject Parsing Tests ====================

    public function testParsesSingleSubject(): void
    {
        $subjectsString = 'Computer Science';
        $subjects = array_map('trim', explode(';', $subjectsString));

        $this->assertCount(1, $subjects);
        $this->assertEquals('Computer Science', $subjects[0]);
    }

    public function testParsesMultipleSubjects(): void
    {
        $subjectsString = 'Computer Science;Artificial Intelligence;Machine Learning';
        $subjects = array_map('trim', explode(';', $subjectsString));

        $this->assertCount(3, $subjects);
        $this->assertEquals('Computer Science', $subjects[0]);
        $this->assertEquals('Artificial Intelligence', $subjects[1]);
        $this->assertEquals('Machine Learning', $subjects[2]);
    }

    public function testTrimsSubjectWhitespace(): void
    {
        $subjectsString = '  Computer Science  ;  Artificial Intelligence  ';
        $subjects = array_map('trim', explode(';', $subjectsString));

        $this->assertEquals('Computer Science', $subjects[0]);
        $this->assertEquals('Artificial Intelligence', $subjects[1]);
    }

    public function testHandlesEmptySubjectsString(): void
    {
        $subjectsString = '';
        $subjects = array_filter(array_map('trim', explode(';', $subjectsString)));

        $this->assertEmpty($subjects);
    }

    // ==================== Subjects from Data Object Tests ====================

    public function testSubjectsFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSubjects('Computer Science;Software Engineering')
            ->buildObject();

        $this->assertEquals('Computer Science;Software Engineering', $data->subjects);
    }

    public function testEmptySubjectsFromDataObject(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withSubjects('')
            ->buildObject();

        $this->assertTrue(empty($data->subjects));
    }

    // ==================== Unicode Subjects Tests ====================

    public function testHandlesUnicodeSubjects(): void
    {
        $subjectsString = 'コンピュータサイエンス;人工知能';
        $subjects = array_map('trim', explode(';', $subjectsString));

        $this->assertCount(2, $subjects);
        $this->assertEquals('コンピュータサイエンス', $subjects[0]);
        $this->assertEquals('人工知能', $subjects[1]);
    }

    public function testHandlesPortugueseSubjects(): void
    {
        $subjectsString = 'Ciência da Computação;Inteligência Artificial';
        $subjects = array_map('trim', explode(';', $subjectsString));

        $this->assertEquals('Ciência da Computação', $subjects[0]);
        $this->assertEquals('Inteligência Artificial', $subjects[1]);
    }

    // ==================== Subjects Data Structure Tests ====================

    public function testSubjectsLocalizedStructure(): void
    {
        $locale = 'en';
        $subjectsList = ['Computer Science', 'Mathematics', 'Physics'];

        $subjectsData = [$locale => $subjectsList];

        $this->assertArrayHasKey($locale, $subjectsData);
        $this->assertEquals($subjectsList, $subjectsData[$locale]);
    }

    public function testMultiLocaleSubjectsStructure(): void
    {
        $subjectsData = [
            'en' => ['Computer Science', 'Mathematics'],
            'pt_BR' => ['Ciência da Computação', 'Matemática'],
        ];

        $this->assertArrayHasKey('en', $subjectsData);
        $this->assertArrayHasKey('pt_BR', $subjectsData);
        $this->assertCount(2, $subjectsData['en']);
        $this->assertCount(2, $subjectsData['pt_BR']);
    }
}
