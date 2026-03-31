# Coding Conventions

**Analysis Date:** 2026-03-31

## Naming Conventions

**Files:**
- Classes use PascalCase matching class name: `AuthorsProcessor.php`, `CsvFileHandler.php`, `RowValidationException.php`
- Test files append `Test` suffix: `AuthorsProcessorTest.php`, `InvalidRowValidationsTest.php`
- One class per file (exception: `CsvTestDataBuilder.php` contains builder + sub-builder classes)

**Classes:**
- PascalCase: `AuthorsProcessor`, `CachedEntities`, `RequiredPreprintHeaders`
- Processors are noun-based: `AuthorsProcessor`, `KeywordsProcessor`, `PublicationProcessor`
- Commands use `Command` suffix: `PreprintCommand`, `UserCommand`
- Validation classes: `InvalidRowValidations`, `RequiredPreprintHeaders`, `RequiredUserHeaders`
- Exception classes: `RowValidationException`, `FileNotSavedException`
- Test builders: `CsvTestDataBuilder`, `MockFactory`

**Methods:**
- camelCase: `processMultiLocale()`, `validateServerIsValid()`, `createInitialPublication()`
- Validation methods use `validate` prefix: `validateEmail()`, `validateOrcid()`, `validateRowContainAllFields()`
- Process methods use `process` prefix with variant suffixes: `process()`, `processMultiLocale()`, `processForVersion()`
- Boolean checks use `is`/`has` prefix: `isFundingPluginEnabled()`, `versionExistsInAnyLocale()`
- Private helpers describe action: `parseAuthorString()`, `normalizeOrcid()`, `cloneAuthorsFromBasePublication()`

**Variables:**
- camelCase: `$contactEmail`, `$userGroupId`, `$sourceDir`, `$processedPreprints`
- Data objects accessed via `$data->propertyName` (stdClass from CSV row)

**Namespaces:**
- Root: `APP\plugins\importexport\csv`
- Classes: `APP\plugins\importexport\csv\classes\{subdirectory}`
- Tests: `APP\plugins\importexport\csv\tests\{subdirectory}`

## Code Style

**Formatting:**
- No external formatter tool (no .prettierrc, .php-cs-fixer, phpcs.xml detected)
- 4-space indentation (standard PHP)
- Opening braces on same line for classes and methods
- Some mixed tab/space indentation in older code (e.g., `KeywordsProcessor.php` lines 43, 52)

**Type Declarations:**
- Use PHP 8.2 features: nullable types (`?Publication`), union types (`Server|MockObject`), typed properties
- Return type declarations on most methods: `void`, `?string`, `Publication`, `int`
- Some methods omit return types (older code, e.g., `KeywordsProcessor::process()`)
- Typed arrays via PHPDoc: `/** @var array<string,Server> */`

**PHPDoc Headers:**
- Every file starts with a standard block:
```php
/**
 * @file plugins/importexport/csv/classes/processors/AuthorsProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorsProcessor
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Process the authors data into the database.
 */
```
- Use `@class`, `@ingroup plugins_importexport_csv`, `@brief` in class-level doc
- Method-level doc uses `@throws RowValidationException` consistently for validation methods

## Import Organization

**Order:**
1. PHP namespace declaration
2. Framework/vendor imports (`APP\facades\Repo`, `APP\publication\Publication`)
3. Plugin internal imports (`APP\plugins\importexport\csv\classes\...`)
4. PHP built-in classes (`Exception`, `SplFileObject`)

**Path Aliases:**
- None used. All imports use fully-qualified PSR-4 namespaces.

## Patterns Used

**Static Utility Classes (Processors):**
- All processors are stateless static classes with no constructor or instance state
- Expose up to three method variants:
  - `process()` - new import
  - `processMultiLocale()` - adds locale data to existing entity
  - `processForVersion()` - creates new version inheriting from base publication
- Call example: `AuthorsProcessor::process($data, $contactEmail, $submissionId, $publication, $userGroupId)`

**Validate-Then-Process:**
- Commands call `InvalidRowValidations::validate*()` methods before any processor
- Validation methods throw `RowValidationException`; commands catch it and write to invalid CSV
- Processors assume validation has already passed

**Static Caching (CachedEntities):**
- `CachedEntities` at `classes/cachedAttributes/CachedEntities.php` uses static arrays
- Stores `null` for failed lookups to avoid repeated DB queries
- Properties: `$servers`, `$userGroupIds`, `$userGroups`, `$genreIds`, `$categories`, `$sections`, `$users`, `$subscriptionTypes`

**OPS Repo Facade for All DB Operations:**
- All database access goes through `Repo::submission()`, `Repo::publication()`, `Repo::author()`, etc.
- Pattern: `Repo::author()->newDataObject()` then `Repo::author()->add($author)`
- No raw SQL queries or direct DAO instantiation (except `DAORegistry` for legacy lookups)

**Data Object as stdClass:**
- CSV rows are combined with headers into `stdClass` objects
- Accessed via `$data->preprintTitle`, `$data->locale`, `$data->authors`
- Not typed DTOs -- just dynamic property access on plain objects

**Fluent Builder Pattern (Test Infrastructure):**
- `CsvTestDataBuilder::preprint()->withTitle('X')->withAuthors('Y')->buildObject()`
- `MockFactory::publication()->withId(1)->withAuthors([...])->build()`
- Both return concrete domain objects, not mocks (except for `ServerBuilder` and `SubmissionBuilder` which return `MockInterface`)

## Error Handling Conventions

**Two Custom Exceptions:**
- `RowValidationException` (`classes/exceptions/RowValidationException.php`) - thrown during row validation, caught by command, row written to invalid CSV, import continues
- `FileNotSavedException` (`classes/exceptions/FileNotSavedException.php`) - thrown during file upload failure, triggers full submission deletion to prevent orphaned entities

**Validation Methods Throw, Commands Catch:**
```php
// In validation class:
public static function validateEmail(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RowValidationException(__('plugins.importexport.csv.invalidEmail', ['email' => $email]));
    }
}

// In command:
try {
    InvalidRowValidations::validateEmail($data->email);
} catch (RowValidationException $e) {
    CsvFileHandler::processFailedRow($invalidFile, $fields, $rowSize, $e->getMessage(), $failedRows);
    continue;
}
```

**Localized Error Messages:**
- All exception messages use `__('plugins.importexport.csv.messageKey')` for i18n
- Message keys live in `locale/en/locale.po`

**Early Return Pattern:**
- Processors use early returns for empty/optional data:
```php
if (empty($data->keywords)) {
    return;
}
```

## Logging

**Framework:** CLI output via OPS `output()` method
**No structured logging framework.** Errors are communicated via:
- Thrown exceptions (caught by commands)
- Invalid row CSV files (written by `CsvFileHandler::processFailedRow()`)
- CLI output for progress/summary

## Comments

**Section Dividers in Test Files:**
- Tests use comment banners to group related tests:
```php
// ==================== ORCID Normalization Tests ====================
// ==================== Author String Parsing Tests ====================
// ==================== process() Integration Tests ====================
```

**Inline Comments:**
- Sparingly used, mostly for non-obvious logic
- Never JSDoc-style `@param`/`@return` on private methods (PHPDoc reserved for public/protected)

## Module Design

**Exports:**
- Each file exports exactly one class (exception: builder files in test fixtures)
- No barrel files or index re-exports

**Static vs Instance:**
- Processors, Validators, CachedEntities: all static
- Commands, Plugin: instance-based (constructed and executed)
- Test builders: static factory entry point, instance fluent chain

---

*Convention analysis: 2026-03-31*
