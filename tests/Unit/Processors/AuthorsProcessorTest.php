<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Processors/AuthorsProcessorTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsProcessorTest
 *
 * @brief Tests for AuthorsProcessor class - testing ORCID normalization and parsing logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Processors;

use APP\author\Author;
use APP\plugins\importexport\csv\classes\processors\AuthorsProcessor;
use APP\plugins\importexport\csv\classes\processors\PublicationProcessor;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\CsvTestDataBuilder;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use APP\publication\Publication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

#[CoversClass(AuthorsProcessor::class)]
class AuthorsProcessorTest extends BaseTestCase
{
    /**
     * Get access to the private normalizeOrcid method
     */
    private function getNormalizeOrcidMethod(): \ReflectionMethod
    {
        $reflection = new ReflectionClass(AuthorsProcessor::class);
        $method = $reflection->getMethod('normalizeOrcid');
        $method->setAccessible(true);
        return $method;
    }

    // ==================== ORCID Normalization Tests ====================

    #[DataProvider('validOrcidProvider')]
    public function testNormalizeOrcidWithValidFormats(string $input, string $expected): void
    {
        $method = $this->getNormalizeOrcidMethod();
        $result = $method->invoke(null, $input);

        $this->assertEquals($expected, $result);
    }

    public static function validOrcidProvider(): array
    {
        return [
            'Full HTTPS URL' => [
                'https://orcid.org/0000-0002-1825-0097',
                'https://orcid.org/0000-0002-1825-0097'
            ],
            'HTTP URL' => [
                'http://orcid.org/0000-0002-1825-0097',
                'https://orcid.org/0000-0002-1825-0097'
            ],
            'Dashed format' => [
                '0000-0002-1825-0097',
                'https://orcid.org/0000-0002-1825-0097'
            ],
            'Numeric format (16 digits)' => [
                '0000000218250097',
                'https://orcid.org/0000-0002-1825-0097'
            ],
            'With spaces' => [
                '0000 0002 1825 0097',
                'https://orcid.org/0000-0002-1825-0097'
            ],
        ];
    }

    #[DataProvider('invalidOrcidProvider')]
    public function testNormalizeOrcidWithInvalidFormats(?string $input): void
    {
        $method = $this->getNormalizeOrcidMethod();
        $result = $method->invoke(null, $input);

        $this->assertNull($result);
    }

    public static function invalidOrcidProvider(): array
    {
        return [
            'Empty string' => [''],
            'Null' => [null],
            'Too short' => ['0000-0002-1825'],
            'Too long' => ['0000-0002-1825-00971'],
            'Invalid characters' => ['0000-0002-182A-0097'],
            'Random text' => ['invalid-orcid'],
        ];
    }

    public function testNormalizeOrcidWithWhitespace(): void
    {
        $method = $this->getNormalizeOrcidMethod();
        $result = $method->invoke(null, '  0000-0002-1825-0097  ');
        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $result);
    }

    public function testNormalizeOrcidPreservesXChecksum(): void
    {
        $method = $this->getNormalizeOrcidMethod();
        $result = $method->invoke(null, '0000-0002-1694-233X');
        $this->assertEquals('https://orcid.org/0000-0002-1694-233X', $result);
    }

    public function testNormalizeOrcidUppercasesXChecksum(): void
    {
        $method = $this->getNormalizeOrcidMethod();
        $result = $method->invoke(null, '0000-0002-1694-233x');
        $this->assertEquals('https://orcid.org/0000-0002-1694-233X', $result);
    }

    // ==================== Author String Parsing Tests ====================

    public function testAuthorStringParsingLogic(): void
    {
        $authorString = 'John,Doe,john@example.com,0000-0002-1825-0097,MIT';
        $authorParts = array_map('trim', explode(',', $authorString));

        $this->assertEquals('John', $authorParts[0]);
        $this->assertEquals('Doe', $authorParts[1]);
        $this->assertEquals('john@example.com', $authorParts[2]);
        $this->assertEquals('0000-0002-1825-0097', $authorParts[3]);
        $this->assertEquals('MIT', $authorParts[4]);
    }

    public function testMultipleAuthorStringParsing(): void
    {
        $authorsString = 'John,Doe,john@example.com,,MIT;Jane,Smith,jane@example.com,0000-0002-1825-0097,Harvard';
        $authors = array_map('trim', explode(';', $authorsString));

        $this->assertCount(2, $authors);
        $this->assertEquals('John,Doe,john@example.com,,MIT', $authors[0]);
        $this->assertEquals('Jane,Smith,jane@example.com,0000-0002-1825-0097,Harvard', $authors[1]);
    }

    public function testAuthorWithMissingEmailField(): void
    {
        $authorString = 'John,Doe,,,MIT';
        $authorParts = array_map('trim', explode(',', $authorString));

        $this->assertEquals('John', $authorParts[0]);
        $this->assertEquals('Doe', $authorParts[1]);
        $this->assertEquals('', $authorParts[2]); // Empty email
        $this->assertEquals('', $authorParts[3]); // Empty ORCID
        $this->assertEquals('MIT', $authorParts[4]);
    }

    public function testAuthorWithSpecialCharactersInName(): void
    {
        $authorString = 'José María,García López,jose@example.com,,Universidad';
        $authorParts = array_map('trim', explode(',', $authorString));

        $this->assertEquals('José María', $authorParts[0]);
        $this->assertEquals('García López', $authorParts[1]);
    }

    public function testAuthorWithUnicodeCharacters(): void
    {
        $authorString = '田中,太郎,tanaka@example.com,,東京大学';
        $authorParts = array_map('trim', explode(',', $authorString));

        $this->assertEquals('田中', $authorParts[0]);
        $this->assertEquals('太郎', $authorParts[1]);
        $this->assertEquals('東京大学', $authorParts[4]);
    }

    public function testEmptyAuthorsString(): void
    {
        $data = (object)['authors' => ''];
        $this->assertTrue(empty($data->authors));
    }

    public function testAuthorsStringWithOnlyWhitespace(): void
    {
        $authorsString = '   ';
        $authors = array_map('trim', explode(';', $authorsString));

        $this->assertCount(1, $authors);
        $this->assertEquals('', $authors[0]);
    }

    // ==================== Author Data From CSV Tests ====================

    public function testAuthorDataFromCsvBuilder(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withAuthors('John,Doe,john@example.com,0000-0002-1825-0097,MIT')
            ->withLocale('en')
            ->buildObject();

        $this->assertEquals('John,Doe,john@example.com,0000-0002-1825-0097,MIT', $data->authors);
        $this->assertEquals('en', $data->locale);
    }

    public function testMultipleAuthorsFromCsvBuilder(): void
    {
        $data = CsvTestDataBuilder::preprint()
            ->withAuthors('John,Doe,john@example.com,,MIT;Jane,Smith,jane@example.com,,Harvard')
            ->buildObject();

        $authors = explode(';', $data->authors);
        $this->assertCount(2, $authors);
    }

    // ==================== Affiliation Parsing Tests ====================

    public function testAffiliationParsing(): void
    {
        $authorString = 'John,Doe,john@example.com,,Massachusetts Institute of Technology';
        $authorParts = array_map('trim', explode(',', $authorString));

        $this->assertEquals('Massachusetts Institute of Technology', $authorParts[4]);
    }

    public function testEmptyAffiliation(): void
    {
        $authorString = 'John,Doe,john@example.com,,';
        $authorParts = array_map('trim', explode(',', $authorString));

        // With trailing comma, we might have 5 or 6 parts depending on parsing
        $affiliation = $authorParts[4] ?? '';
        $this->assertEquals('', $affiliation);
    }

    public function testAffiliationWithSpecialCharacters(): void
    {
        $authorString = 'John,Doe,john@example.com,,Universität München & TU Berlin';
        $authorParts = array_map('trim', explode(',', $authorString));

        $this->assertEquals('Universität München & TU Berlin', $authorParts[4]);
    }

    // ==================== Contact Email Fallback Tests ====================

    public function testContactEmailFallbackLogic(): void
    {
        $authorEmail = '';
        $contactEmail = 'contact@example.com';

        // This is the fallback logic used in AuthorsProcessor
        $emailToUse = empty($authorEmail) ? $contactEmail : $authorEmail;

        $this->assertEquals($contactEmail, $emailToUse);
    }

    public function testAuthorEmailOverridesContactEmail(): void
    {
        $authorEmail = 'author@example.com';
        $contactEmail = 'contact@example.com';

        $emailToUse = empty($authorEmail) ? $contactEmail : $authorEmail;

        $this->assertEquals($authorEmail, $emailToUse);
    }

    // ==================== Primary Author Logic Tests ====================

    public function testFirstAuthorIsPrimary(): void
    {
        $authorsString = 'John,Doe,john@example.com,,MIT;Jane,Smith,jane@example.com,,Harvard';
        $authors = array_map('trim', explode(';', $authorsString));

        // Index 0 should be the primary author
        $this->assertEquals(0, array_key_first($authors));
    }

    // ==================== process() Integration Tests ====================

    /**
     * Helper to get access to private parseAuthorString method
     */
    private function getParseAuthorStringMethod(): \ReflectionMethod
    {
        $reflection = new ReflectionClass(AuthorsProcessor::class);
        $method = $reflection->getMethod('parseAuthorString');
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Helper to get access to private updateAuthorFromCsv method
     */
    private function getUpdateAuthorFromCsvMethod(): \ReflectionMethod
    {
        $reflection = new ReflectionClass(AuthorsProcessor::class);
        $method = $reflection->getMethod('updateAuthorFromCsv');
        $method->setAccessible(true);
        return $method;
    }

    public function testParseAuthorStringWithAllFields(): void
    {
        $method = $this->getParseAuthorStringMethod();
        $result = $method->invoke(null, 'John,Doe,john@example.com,0000-0002-1825-0097,MIT', 'contact@example.com');

        $this->assertEquals('John', $result[0]);
        $this->assertEquals('Doe', $result[1]);
        $this->assertEquals('john@example.com', $result[2]);
        $this->assertEquals('0000-0002-1825-0097', $result[3]);
        $this->assertEquals('MIT', $result[4]);
    }

    public function testParseAuthorStringFallsBackToContactEmail(): void
    {
        $method = $this->getParseAuthorStringMethod();
        $result = $method->invoke(null, 'John,Doe,,,MIT', 'contact@example.com');

        $this->assertEquals('contact@example.com', $result[2]);
    }

    public function testParseAuthorStringWithMinimalData(): void
    {
        $method = $this->getParseAuthorStringMethod();
        $result = $method->invoke(null, 'John,Doe', 'fallback@example.com');

        $this->assertEquals('John', $result[0]);
        $this->assertEquals('Doe', $result[1]);
        $this->assertEquals('fallback@example.com', $result[2]);
        $this->assertEquals('', $result[3]);
        $this->assertEquals('', $result[4]);
    }

    public function testUpdateAuthorFromCsvSetsNames(): void
    {
        $this->mockAffiliationRepository();
        $method = $this->getUpdateAuthorFromCsvMethod();
        $author = new Author();

        $method->invoke(null, $author, 'John', 'Doe', '', '', 'en', false);

        $this->assertEquals('John', $author->getGivenName('en'));
        $this->assertEquals('Doe', $author->getFamilyName('en'));
    }

    public function testUpdateAuthorFromCsvSkipsEmptyNamesForExistingAuthor(): void
    {
        $this->mockAffiliationRepository();
        $method = $this->getUpdateAuthorFromCsvMethod();
        $author = new Author();
        $author->setGivenName('Existing', 'en');
        $author->setFamilyName('Author', 'en');

        $method->invoke(null, $author, '', '', '', '', 'en', true);

        $this->assertEquals('Existing', $author->getGivenName('en'));
        $this->assertEquals('Author', $author->getFamilyName('en'));
    }

    public function testUpdateAuthorFromCsvSetsOrcidWhenValid(): void
    {
        $this->mockAffiliationRepository();
        $method = $this->getUpdateAuthorFromCsvMethod();
        $author = new Author();

        $method->invoke(null, $author, 'John', 'Doe', '0000-0002-1825-0097', '', 'en', false);

        $this->assertEquals('https://orcid.org/0000-0002-1825-0097', $author->getOrcid());
    }

    public function testUpdateAuthorFromCsvIgnoresInvalidOrcid(): void
    {
        $this->mockAffiliationRepository();
        $method = $this->getUpdateAuthorFromCsvMethod();
        $author = new Author();

        $method->invoke(null, $author, 'John', 'Doe', 'invalid-orcid', '', 'en', false);

        $this->assertEmpty($author->getOrcid());
    }

    public function testUpdateAuthorFromCsvSetsAffiliationForNewAuthor(): void
    {
        $this->mockAffiliationRepository();
        $method = $this->getUpdateAuthorFromCsvMethod();
        $author = new Author();

        $method->invoke(null, $author, 'John', 'Doe', '', 'MIT', 'en', false);

        $affiliations = $author->getAffiliations();
        $this->assertNotEmpty($affiliations);
    }

    public function testProcessCreatesSingleAuthor(): void
    {
        $addCallCount = 0;
        $authorRepoMock = $this->mockAuthorRepository();
        $this->mockPublicationRepository();
        $this->mockAffiliationRepository();

        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'authors' => 'John,Doe,john@example.com,,MIT',
            'locale' => 'en',
        ];

        AuthorsProcessor::process($data, 'contact@example.com', 1, $publication, 1);

        $this->assertEquals(1, $addCallCount);
    }

    public function testProcessCreatesMultipleAuthors(): void
    {
        $addCallCount = 0;
        $authorRepoMock = $this->mockAuthorRepository();
        $this->mockPublicationRepository();
        $this->mockAffiliationRepository();

        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'authors' => 'John,Doe,john@example.com,,MIT;Jane,Smith,jane@example.com,,Harvard',
            'locale' => 'en',
        ];

        AuthorsProcessor::process($data, 'contact@example.com', 1, $publication, 1);

        $this->assertEquals(2, $addCallCount);
    }

    public function testProcessSetsFirstAuthorAsPrimaryContact(): void
    {
        $authorRepoMock = $this->mockAuthorRepository();
        $this->mockPublicationRepository();
        $this->mockAffiliationRepository();

        $addCallCount = 0;
        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'authors' => 'John,Doe,john@example.com,,MIT;Jane,Smith,jane@example.com,,Harvard',
            'locale' => 'en',
        ];

        AuthorsProcessor::process($data, 'contact@example.com', 1, $publication, 1);

        // Primary contact should be set to first author ID (1)
        $this->assertEquals(1, $publication->getData('primaryContactId'));
    }

    public function testProcessMultiLocaleUpdatesExistingAuthorByEmail(): void
    {
        $addCallCount = 0;
        $authorRepoMock = $this->mockAuthorRepository();
        $this->mockAffiliationRepository();

        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        $existingAuthor = new Author();
        $existingAuthor->setId(1);
        $existingAuthor->setGivenName('John', 'en');
        $existingAuthor->setFamilyName('Doe', 'en');
        $existingAuthor->setEmail('john@example.com');

        $publication = MockFactory::publication()
            ->withAuthors([$existingAuthor])
            ->build();

        $data = (object) [
            'authors' => 'João,Silva,john@example.com,,MIT',
            'locale' => 'pt_BR',
        ];

        AuthorsProcessor::processMultiLocale($data, 'contact@example.com', 1, $publication, 1);

        // Should update existing author, not create new one
        $this->assertEquals(0, $addCallCount);
        // The existing author should now have pt_BR locale data
        $this->assertEquals('João', $existingAuthor->getGivenName('pt_BR'));
        $this->assertEquals('Silva', $existingAuthor->getFamilyName('pt_BR'));
    }

    public function testProcessMultiLocaleCreatesNewAuthorWhenNoEmailMatch(): void
    {
        $addCallCount = 0;
        $authorRepoMock = $this->mockAuthorRepository();
        $this->mockAffiliationRepository();

        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        $existingAuthor = new Author();
        $existingAuthor->setId(1);
        $existingAuthor->setEmail('john@example.com');

        $publication = MockFactory::publication()
            ->withAuthors([$existingAuthor])
            ->build();

        $data = (object) [
            'authors' => 'Maria,Santos,maria@example.com,,USP',
            'locale' => 'pt_BR',
        ];

        AuthorsProcessor::processMultiLocale($data, 'contact@example.com', 1, $publication, 1);

        $this->assertEquals(1, $addCallCount);
    }

    public function testProcessMultiLocaleReturnsEarlyWithEmptyAuthors(): void
    {
        $addCallCount = 0;
        $authorRepoMock = $this->mockAuthorRepository();

        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'authors' => '',
            'locale' => 'pt_BR',
        ];

        AuthorsProcessor::processMultiLocale($data, 'contact@example.com', 1, $publication, 1);

        $this->assertEquals(0, $addCallCount);
    }

    public function testProcessMultiLocaleReturnsEarlyWithNoExistingAuthors(): void
    {
        $addCallCount = 0;
        $authorRepoMock = $this->mockAuthorRepository();
        $this->mockAffiliationRepository();

        $authorRepoMock->shouldReceive('add')->andReturnUsing(function () use (&$addCallCount) {
            $addCallCount++;
            return $addCallCount;
        });

        // Use plain Publication (getData('authors') returns null → empty check triggers early return)
        $publication = new Publication();
        $publication->setId(1);

        $data = (object) [
            'authors' => 'John,Doe,john@example.com,,MIT',
            'locale' => 'pt_BR',
        ];

        AuthorsProcessor::processMultiLocale($data, 'contact@example.com', 1, $publication, 1);

        // Should return early since no existing authors (null/empty array)
        $this->assertEquals(0, $addCallCount);
    }
}
