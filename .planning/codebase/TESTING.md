# Testing Patterns

**Analysis Date:** 2026-03-31

## Test Framework

**Runner:**
- PHPUnit 11.x
- Config: `phpunit.xml`
- Bootstrap: `../../../lib/pkp/tests/phpunit-bootstrap.php` (PKP framework bootstrap)

**Assertion Library:**
- PHPUnit built-in assertions
- Mockery 1.6.x for mock expectations

**Run Commands:**
```bash
# Run all tests (658 tests, 1108 assertions)
php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml

# Run a single test file
php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Processors/AuthorsProcessorTest.php

# Run a single test method
php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml --filter testMethodName

# Run with coverage (requires XDEBUG_MODE=coverage)
XDEBUG_MODE=coverage php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml
```

## Test File Organization

**Location:**
- All tests under `tests/Unit/` mirroring `classes/` structure
- Test infrastructure under `tests/Fixtures/` and `tests/BaseTestCase.php`

**Naming:**
- Test files: `{ClassName}Test.php` (e.g., `AuthorsProcessorTest.php`)
- Test methods: `test{DescriptiveName}` in camelCase (e.g., `testProcessCreatesSingleAuthor`)
- Data providers: `{descriptiveName}Provider` (e.g., `validOrcidProvider`, `invalidOrcidProvider`)

**Structure:**
```
tests/
  BaseTestCase.php                          # Base class for all tests
  Fixtures/
    CsvTestDataBuilder.php                  # Fluent builder for CSV test data
    MockFactory.php                         # Fluent builder for domain object mocks
  Mocks/                                    # (empty directory)
  Unit/
    CachedAttributes/
      CachedEntitiesTest.php                # 55 tests
    Commands/
      PreprintCommandTest.php               # 47 tests
      UserCommandTest.php                   # 36 tests
    Handlers/
      CSVFileHandlerTest.php                # 20 tests
      CsvFileHandlerTest.php                # 20 tests
      WelcomeEmailHandlerTest.php           # 15 tests
    Processors/
      AuthorsProcessorTest.php              # 40 tests
      CategoriesProcessorTest.php           # 31 tests
      FundersProcessorTest.php              # 30 tests
      GalleyProcessorTest.php               # 22 tests
      KeywordsProcessorTest.php             # 24 tests
      PublicationProcessorTest.php          # 46 tests
      SectionsProcessorTest.php             # 22 tests
      SubjectsProcessorTest.php             # 23 tests
      SubmissionFileProcessorTest.php       # 22 tests (note: separate from SubmissionProcessorTest)
      SubmissionProcessorTest.php           # (listed in files)
      UserGroupsProcessorTest.php           # 11 tests
      UserInterestsProcessorTest.php        # 13 tests
      UsersProcessorTest.php                # 36 tests
    Validations/
      InvalidRowValidationsTest.php         # 117 tests (largest test file)
      RequiredPreprintHeadersTest.php       # 18 tests
      RequiredUserHeadersTest.php           # 15 tests
```

## Test Types

| Type | Count | Location |
|---|---|---|
| Unit (processors) | ~300+ | `tests/Unit/Processors/` |
| Unit (validations) | ~150 | `tests/Unit/Validations/` |
| Unit (commands) | ~83 | `tests/Unit/Commands/` |
| Unit (cached entities) | ~55 | `tests/Unit/CachedAttributes/` |
| Unit (handlers) | ~55 | `tests/Unit/Handlers/` |
| Integration | 0 | None |
| E2E | 0 | None |

All tests are unit tests. No integration or end-to-end tests exist.

## Test Structure

**Suite Organization:**
```php
#[CoversClass(AuthorsProcessor::class)]
class AuthorsProcessorTest extends BaseTestCase
{
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
            'Full HTTPS URL' => ['https://orcid.org/0000-0002-1825-0097', 'https://orcid.org/0000-0002-1825-0097'],
            'Dashed format' => ['0000-0002-1825-0097', 'https://orcid.org/0000-0002-1825-0097'],
        ];
    }
}
```

**PHPUnit Attributes Used:**
- `#[CoversClass(ClassName::class)]` on every test class (required)
- `#[DataProvider('providerName')]` for parameterized tests
- `#[RunInSeparateProcess]` and `#[PreserveGlobalState(false)]` for tests requiring process isolation

**Section Comment Banners:**
- Group related tests with `// ==================== Section Name ====================`
- Common sections: "Integration Tests", "Parsing Tests", "Validation Tests", "Edge Cases"

**Setup/Teardown Pattern:**
```php
protected function setUp(): void
{
    parent::setUp();              // Calls BaseTestCase::setUp() which backs up CachedEntities statics
    $this->tempDir = $this->createTempDirectory();  // For file-based tests
}

protected function tearDown(): void
{
    $this->cleanupTempDirectory($this->tempDir);    // Clean up temp files
    parent::tearDown();           // Restores CachedEntities statics + Mockery::close()
}
```

## Base Test Case

**Location:** `tests/BaseTestCase.php`
**Extends:** `PKP\tests\PKPTestCase` (PKP framework base)

**Provides:**
1. **CachedEntities static backup/restore** - backs up all static arrays in setUp, clears them, restores in tearDown
2. **Container instance backup/restore** - backs up Laravel container bindings before mocking, restores after test
3. **Repository mock helpers** - pre-configured mock methods for all OPS repositories:
   - `mockAuthorRepository()`, `mockPublicationRepository()`, `mockSubmissionRepository()`
   - `mockUserRepository()`, `mockCategoryRepository()`, `mockGalleyRepository()`
   - `mockSectionRepository()`, `mockSubmissionFileRepository()`, `mockUserGroupRepository()`
   - `mockControlledVocabRepository()`, `mockUserInterestRepository()`, `mockEmailTemplateRepository()`
   - `mockAffiliationRepository()`
4. **Entity creation helpers** - `createMockUser()`, `createMockServer()`, `createMockPublication()`, `createMockSubmission()`, `createMockAuthor()`, `createMockSection()`, `createMockCategory()`, `createMockUserGroup()`, `createMockGalley()`
5. **File system helpers** - `createTempDirectory()`, `cleanupTempDirectory()`, `createTestCsvFile()`, `createTestFile()`
6. **Data object helpers** - `createPreprintDataObject()`, `createUserDataObject()` for quick stdClass creation
7. **Database helpers** - `beginDatabaseTransaction()`, `rollbackDatabaseTransaction()` for DB-touching tests

## Mocking

**Framework:** Mockery 1.6.x + PHPUnit MockObject (both used)

**Repository Mock Pattern (Mockery):**
```php
protected function mockAuthorRepository(): MockInterface
{
    $this->backupContainerInstance(AuthorRepository::class);

    $authorDaoMock = Mockery::mock(AuthorDAO::class)->makePartial();
    $authorDaoMock->shouldReceive('update')->andReturn(true);

    $mock = Mockery::mock(AuthorRepository::class)->makePartial();
    $mock->shouldReceive('newDataObject')->andReturnUsing(fn() => new Author());
    $mock->shouldReceive('add')->andReturn(1)->byDefault();
    $mock->shouldReceive('edit')->andReturn(true);
    $mock->dao = $authorDaoMock;

    app()->instance(AuthorRepository::class, $mock);
    return $mock;
}
```

**Key mocking conventions:**
- Use `->makePartial()` on repository mocks (partial mocks)
- Use `->byDefault()` on expectations that tests may override
- Register mocks in Laravel container via `app()->instance(Class::class, $mock)`
- Always backup container before mocking, restore in tearDown
- DAO mocks are set as public property on repository mocks: `$mock->dao = $daoMock`

**PHPUnit MockObject Pattern (for Server/Submission):**
```php
protected function createMockServer(array $data = []): Server|MockObject
{
    $server = $this->getMockBuilder(Server::class)
        ->onlyMethods(['getSupportedSubmissionLocales', 'getPrimaryLocale', 'getContactEmail'])
        ->getMock();
    $server->setId($data['id'] ?? 1);
    $server->method('getSupportedSubmissionLocales')->willReturn($supportedLocales);
    return $server;
}
```

**What to Mock:**
- OPS Repository classes (all DB access goes through `Repo` facade)
- DAO classes (set on repository mock's `dao` property)
- Server objects (need method stubs for `getSupportedSubmissionLocales()` etc.)
- Submission objects (need `getCurrentPublication()` stub)
- HTTP clients (for ORCID validation)

**What NOT to Mock:**
- Domain value objects: `Publication`, `Author`, `Category`, `Section`, `UserGroup`, `Galley` - use real instances
- Processors under test - test actual static methods
- Validation classes - test actual static methods
- Test data builders

## Fixtures and Factories

**CsvTestDataBuilder** (`tests/Fixtures/CsvTestDataBuilder.php`):
```php
// Fluent builder for CSV row data
$data = CsvTestDataBuilder::preprint()
    ->withServerPath('testserver')
    ->withLocale('en')
    ->withTitle('Test Preprint')
    ->withAuthors('John,Doe,john@example.com,,MIT')
    ->withDatePosted('2024-01-15')
    ->withSectionTitle('Preprints')
    ->withSectionAbbrev('PRE')
    ->buildObject();  // Returns stdClass

$row = CsvTestDataBuilder::preprint()->...->buildArray();  // Returns indexed array (CSV row)

// Pre-built convenience methods:
CsvTestDataBuilder::minimalPreprintRow();   // Only required fields
CsvTestDataBuilder::completePreprintRow();  // All fields populated
CsvTestDataBuilder::minimalUserRow();
CsvTestDataBuilder::completeUserRow();
```

**MockFactory** (`tests/Fixtures/MockFactory.php`):
```php
// Fluent builder for domain objects
$publication = MockFactory::publication()
    ->withId(1)
    ->withSubmissionId(1)
    ->withTitle('Test', 'en')
    ->withAuthors([$author])
    ->build();  // Returns real Publication instance

$server = MockFactory::server()
    ->withPath('testserver')
    ->withSupportedLocales(['en', 'pt_BR'])
    ->build();  // Returns Mockery MockInterface (needs method stubs)
```

**Available MockFactory builders:**
- `MockFactory::user()`, `MockFactory::server()`, `MockFactory::publication()`
- `MockFactory::submission()`, `MockFactory::author()`, `MockFactory::section()`
- `MockFactory::category()`, `MockFactory::userGroup()`, `MockFactory::galley()`
- `MockFactory::genre()`, `MockFactory::submissionFile()`

**MultiVersionScenarioBuilder** (in `CsvTestDataBuilder.php`):
```php
// For multi-version/multi-locale test scenarios
$scenario = new MultiVersionScenarioBuilder();
$scenario->addVersion1('en')->withTitle('V1 English')->withServerPath('test');
$scenario->addLocale('1', 'pt_BR')->withTitle('V1 Portuguese');
$scenario->addVersion2('en')->withTitle('V2 English');
$rows = $scenario->buildAllObjects();
```

## Reflection for Private Methods

Tests access private methods via reflection when testing internal logic:
```php
private function getNormalizeOrcidMethod(): \ReflectionMethod
{
    $reflection = new ReflectionClass(AuthorsProcessor::class);
    $method = $reflection->getMethod('normalizeOrcid');
    $method->setAccessible(true);
    return $method;
}

// Usage:
$method = $this->getNormalizeOrcidMethod();
$result = $method->invoke(null, '0000-0002-1825-0097');
```

## Coverage

**Requirements:** No minimum threshold enforced, but coverage reports are configured.

**Configuration** (from `phpunit.xml`):
```xml
<source>
    <include>
        <directory suffix=".php">classes</directory>
    </include>
    <exclude>
        <directory>tests</directory>
        <directory>vendor</directory>
    </exclude>
</source>
<coverage>
    <report>
        <html outputDirectory="tests/coverage/html"/>
        <text outputFile="tests/coverage/coverage.txt" showOnlySummary="false"/>
        <clover outputFile="tests/coverage/clover.xml"/>
    </report>
</coverage>
```

**View Coverage:**
```bash
XDEBUG_MODE=coverage php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml
# Then open tests/coverage/html/index.html
```

## Common Patterns

**Testing Validation Methods (expect exception):**
```php
public function testValidateRowContainAllFieldsWithFewerFields(): void
{
    $fields = ['field1', 'field2'];
    $expectedSize = 5;

    $this->expectException(RowValidationException::class);
    InvalidRowValidations::validateRowContainAllFields($fields, $expectedSize);
}
```

**Testing Processor Integration (mock repo, count calls):**
```php
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
```

**Testing with Data Providers:**
```php
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
        'Full HTTPS URL' => ['https://orcid.org/0000-0002-1825-0097', 'https://orcid.org/0000-0002-1825-0097'],
        'Dashed format' => ['0000-0002-1825-0097', 'https://orcid.org/0000-0002-1825-0097'],
    ];
}
```

**Testing File-Based Operations:**
```php
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

public function testFilePathBasename(): void
{
    $filePath = $this->tempDir . '/my-research-paper.pdf';
    $this->createTestFile($this->tempDir, 'my-research-paper.pdf', 'fake content');
    $basename = pathinfo($filePath, PATHINFO_FILENAME);
    $this->assertEquals('my-research-paper', $basename);
}
```

**Testing Multi-Locale Behavior:**
```php
public function testProcessMultiLocaleUpdatesExistingAuthorByEmail(): void
{
    $authorRepoMock = $this->mockAuthorRepository();
    $this->mockAffiliationRepository();

    $existingAuthor = new Author();
    $existingAuthor->setId(1);
    $existingAuthor->setGivenName('John', 'en');
    $existingAuthor->setEmail('john@example.com');

    $publication = MockFactory::publication()
        ->withAuthors([$existingAuthor])
        ->build();

    $data = (object) ['authors' => 'Joao,Silva,john@example.com,,MIT', 'locale' => 'pt_BR'];

    AuthorsProcessor::processMultiLocale($data, 'contact@example.com', 1, $publication, 1);

    $this->assertEquals('Joao', $existingAuthor->getGivenName('pt_BR'));
}
```

---

*Testing analysis: 2026-03-31*
