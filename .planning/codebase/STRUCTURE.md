# Codebase Structure

**Analysis Date:** 2026-03-31

## Directory Layout

```
plugins/importexport/csv/
├── CSVImportExportPlugin.php       # Entry point, CLI dispatcher (145 lines)
├── index.php                       # OPS plugin loader shim
├── phpunit.xml                     # PHPUnit config
├── CLAUDE.md                       # Development instructions
├── README.md                       # User-facing documentation
├── .gitignore                      # Git ignore rules
├── classes/                        # All production source code
│   ├── commands/                   # Orchestrator commands
│   │   ├── PreprintCommand.php     # Preprint import orchestration (737 lines)
│   │   └── UserCommand.php         # User import orchestration (140 lines)
│   ├── processors/                 # Single-domain DB processors (all static)
│   │   ├── AuthorsProcessor.php    # Author CRUD + multi-locale + cloning (334 lines)
│   │   ├── CategoriesProcessor.php # Category assignment + versioning (139 lines)
│   │   ├── FundersProcessor.php    # Funding plugin integration (245 lines)
│   │   ├── GalleyProcessor.php     # Galley creation (40 lines)
│   │   ├── KeywordsProcessor.php   # Keyword assignment per locale (74 lines)
│   │   ├── PublicationProcessor.php# Publication CRUD, versioning, cover images (396 lines)
│   │   ├── SectionsProcessor.php   # Section resolution/creation (106 lines)
│   │   ├── StatisticsProcessor.php # Usage metrics insertion (80 lines)
│   │   ├── SubjectsProcessor.php   # Subject assignment per locale (74 lines)
│   │   ├── SubmissionFileProcessor.php # Submission file records (70 lines)
│   │   ├── SubmissionProcessor.php # Submission creation (46 lines)
│   │   ├── UserGroupsProcessor.php # User role assignment (33 lines)
│   │   ├── UserInterestsProcessor.php # Review interests (32 lines)
│   │   └── UsersProcessor.php      # User creation (70 lines)
│   ├── validations/                # CSV schema + row validation
│   │   ├── RequiredPreprintHeaders.php  # 35 column headers, 5 required (100 lines)
│   │   ├── RequiredUserHeaders.php      # 11 column headers, 5 required (58 lines)
│   │   └── InvalidRowValidations.php    # All semantic validators (696 lines)
│   ├── handlers/                   # Cross-cutting I/O
│   │   ├── CsvFileHandler.php      # CSV read/write via SplFileObject (66 lines)
│   │   └── WelcomeEmailHandler.php # Welcome email sending (53 lines)
│   ├── cachedAttributes/           # Static memoization cache
│   │   └── CachedEntities.php      # Server, genre, user group, category, section cache (224 lines)
│   └── exceptions/                 # Custom exceptions for flow control
│       ├── RowValidationException.php   # Validation failure (23 lines)
│       └── FileNotSavedException.php    # File upload failure (23 lines)
├── tests/                          # Test suite (658 tests, 1108 assertions)
│   ├── Unit/                       # Unit tests mirroring classes/ structure
│   │   ├── Commands/
│   │   │   ├── PreprintCommandTest.php
│   │   │   └── UserCommandTest.php
│   │   ├── Processors/
│   │   │   ├── AuthorsProcessorTest.php
│   │   │   ├── CategoriesProcessorTest.php
│   │   │   ├── FundersProcessorTest.php
│   │   │   ├── GalleyProcessorTest.php
│   │   │   ├── KeywordsProcessorTest.php
│   │   │   ├── PublicationProcessorTest.php
│   │   │   ├── SectionsProcessorTest.php
│   │   │   ├── SubmissionFileProcessorTest.php
│   │   │   ├── SubmissionProcessorTest.php
│   │   │   ├── SubjectsProcessorTest.php
│   │   │   ├── UserGroupsProcessorTest.php
│   │   │   ├── UserInterestsProcessorTest.php
│   │   │   ├── UsersProcessorTest.php
│   │   │   └── FundersProcessorTest.php
│   │   ├── Validations/
│   │   │   ├── InvalidRowValidationsTest.php
│   │   │   ├── RequiredPreprintHeadersTest.php
│   │   │   └── RequiredUserHeadersTest.php
│   │   ├── Handlers/
│   │   │   ├── CsvFileHandlerTest.php
│   │   │   ├── CSVFileHandlerTest.php
│   │   │   └── WelcomeEmailHandlerTest.php
│   │   └── CachedAttributes/
│   │       └── CachedEntitiesTest.php
│   ├── Fixtures/                   # Test infrastructure
│   │   ├── MockFactory.php         # Centralized mock creation for OPS domain objects
│   │   └── CsvTestDataBuilder.php  # Fluent builder for test CSV row data
│   ├── Mocks/                      # Mock classes (directory exists)
│   └── coverage/                   # Generated coverage reports (not committed)
│       ├── coverage.txt
│       ├── clover.xml
│       └── html/                   # HTML coverage report
├── examples/                       # Example CSV files
│   ├── preprints/                  # Preprint CSV examples
│   └── users/                      # User CSV examples
├── imports/                        # Default import directory
│   ├── preprint_assets/            # Galley/supplementary files for import
│   └── users/                      # User CSV import source
├── locale/                         # i18n
│   └── en/                         # English locale strings
└── .planning/                      # Planning documents
    └── codebase/                   # Codebase analysis documents
```

## Directory Purposes

**`classes/commands/`:**
- Purpose: Workflow orchestrators that drive the entire import process per command type
- Contains: Two command classes, one per import type (preprints, users)
- Key files: `PreprintCommand.php` is the most complex file (737 lines) with import-type routing logic

**`classes/processors/`:**
- Purpose: Single-domain database operations, each handling exactly one entity type
- Contains: 14 static utility classes, each with `process()` and optionally `processMultiLocale()` / `processForVersion()` variants
- Key files: `PublicationProcessor.php` (396 lines, most complex), `AuthorsProcessor.php` (334 lines), `FundersProcessor.php` (245 lines)

**`classes/validations/`:**
- Purpose: CSV schema definition and semantic row validation
- Contains: Header contract classes (define allowed/required columns) and the central validator class
- Key files: `InvalidRowValidations.php` (696 lines) contains all validation logic including security checks

**`classes/handlers/`:**
- Purpose: Cross-cutting I/O concerns (CSV file read/write, email sending)
- Contains: File handler and email handler, both static utility classes

**`classes/cachedAttributes/`:**
- Purpose: Static memoization of frequently-queried entities to prevent N+1 queries
- Contains: Single class with static arrays for each entity type

**`classes/exceptions/`:**
- Purpose: Custom exceptions for import flow control
- Contains: Two simple exception classes that extend PHP `Exception`

**`tests/Unit/`:**
- Purpose: Unit tests mirroring the `classes/` directory structure
- Contains: Test classes for every production class

**`tests/Fixtures/`:**
- Purpose: Shared test infrastructure
- Contains: `MockFactory` for OPS domain object mocks, `CsvTestDataBuilder` for fluent test data creation

**`examples/`:**
- Purpose: Reference CSV files showing correct format for imports
- Contains: Sample preprint and user CSVs (single/multi locale, multi version)

**`imports/`:**
- Purpose: Default directory for import source files (CSVs and associated assets)
- Contains: Preprint asset files (galleys, cover images) and user CSVs

**`locale/`:**
- Purpose: Internationalization strings
- Contains: English locale translations for all plugin messages

## Key File Locations

**Entry Points:**
- `CSVImportExportPlugin.php`: Plugin registration + CLI dispatch (the only entry point)
- `index.php`: OPS plugin loader shim (returns plugin instance)

**Configuration:**
- `phpunit.xml`: PHPUnit test configuration
- `CLAUDE.md`: Development and coding guidelines

**Core Logic (by importance):**
- `classes/commands/PreprintCommand.php`: Main orchestrator for preprint import (most complex file)
- `classes/processors/PublicationProcessor.php`: Publication CRUD with versioning + multi-locale
- `classes/validations/InvalidRowValidations.php`: All semantic validation rules
- `classes/processors/AuthorsProcessor.php`: Author processing with complex multi-locale/version logic
- `classes/processors/FundersProcessor.php`: External plugin integration (Funding plugin)
- `classes/cachedAttributes/CachedEntities.php`: Performance-critical caching layer
- `classes/commands/UserCommand.php`: User import orchestrator

**Testing:**
- `tests/Fixtures/CsvTestDataBuilder.php`: Fluent builder for test data
- `tests/Fixtures/MockFactory.php`: Centralized mock creation

## Naming Conventions

**Files:**
- PascalCase class names matching the class: `PublicationProcessor.php`, `CsvFileHandler.php`
- Test files: `{ClassName}Test.php` in a directory mirroring `classes/`

**Directories:**
- camelCase: `cachedAttributes/`, `commands/`, `processors/`, `validations/`, `handlers/`, `exceptions/`
- Test directories use PascalCase: `Unit/`, `Commands/`, `Processors/`, `Validations/`, `Handlers/`, `CachedAttributes/`, `Fixtures/`

**Namespaces:**
- All classes under `APP\plugins\importexport\csv\classes\{subdirectory}\{ClassName}`
- Example: `APP\plugins\importexport\csv\classes\processors\PublicationProcessor`

## Where to Add New Code

**New Processor (e.g., for a new CSV domain):**
- Implementation: `classes/processors/{DomainName}Processor.php`
- Tests: `tests/Unit/Processors/{DomainName}ProcessorTest.php`
- Pattern: Static class with `process()`, optionally `processMultiLocale()` and `processForVersion()`
- Integration: Add calls in `PreprintCommand::run()` or `UserCommand::run()`

**New Validation Rule:**
- Add static method to `classes/validations/InvalidRowValidations.php`
- Add call in the appropriate command's validation block
- Tests: Add to `tests/Unit/Validations/InvalidRowValidationsTest.php`

**New CSV Column:**
- Add column name to header array in `classes/validations/RequiredPreprintHeaders.php` or `classes/validations/RequiredUserHeaders.php`
- If required, add to `$preprintRequiredHeaders` / `$userRequiredHeaders`
- Add processing logic in the appropriate processor or command
- Update example CSVs in `examples/`

**New Handler:**
- Implementation: `classes/handlers/{HandlerName}Handler.php`
- Tests: `tests/Unit/Handlers/{HandlerName}HandlerTest.php`

**New Exception:**
- Implementation: `classes/exceptions/{ExceptionName}Exception.php`
- Pattern: Extend PHP `Exception`, no additional logic needed

**New Cached Entity:**
- Add static array property and getter method to `classes/cachedAttributes/CachedEntities.php`
- Follow cache-null pattern for failed lookups
- Tests: Add to `tests/Unit/CachedAttributes/CachedEntitiesTest.php`

## Special Directories

**`tests/coverage/`:**
- Purpose: Generated coverage reports
- Generated: Yes (by PHPUnit with Xdebug)
- Committed: Partially (coverage.txt and clover.xml appear tracked)

**`imports/`:**
- Purpose: Default import source directory with sample data
- Generated: No
- Committed: Yes

**`.planning/`:**
- Purpose: Planning and analysis documents for development tooling
- Generated: Yes (by codebase mapping)
- Committed: Yes

**`.phpunit.cache/`:**
- Purpose: PHPUnit result cache for faster re-runs
- Generated: Yes
- Committed: No (in .gitignore)

---

*Structure analysis: 2026-03-31*
