# Architecture

**Analysis Date:** 2026-03-31

## Pattern Overview

**Overall:** Command-Orchestrated Processor Pipeline (CLI Plugin for OPS 3.5.x)

**Key Characteristics:**
- CLI-only plugin with no web UI; invoked via OPS `tools/runScheduledTasks.php`
- Validate-first, fail-fast per row; failed rows written to `invalid_{filename}.csv`
- Stateless, single-domain processors called explicitly by command orchestrators
- Multi-locale and multi-version imports are first-class routing concerns in the command layer
- All DB operations go through the OPS `Repo` facade; no raw queries except `DB::table()` for statistics and cover image sync

## Component Diagram

```
CSVImportExportPlugin.php  (entry point, extends ImportExportPlugin)
    |
    |-- parses CLI args: command, username, sourceDir, --sendWelcomeEmail
    |-- validates user via Repo::user()->getByUsername()
    |-- dispatches via match():
    |
    +-- PreprintCommand                         (orchestrator: 737 lines)
    |   |
    |   |-- [Validation Layer]
    |   |   +-- RequiredPreprintHeaders         (CSV schema: 35 columns, 5 required)
    |   |   +-- InvalidRowValidations           (semantic checks per row)
    |   |
    |   |-- [Caching Layer]
    |   |   +-- CachedEntities                  (static memo cache for servers, genres, users, etc.)
    |   |
    |   |-- [Processing Layer] (all static utility classes)
    |   |   +-- SubmissionProcessor             (creates Submission via Repo::submission())
    |   |   +-- PublicationProcessor            (creates/updates Publication, handles versioning + multi-locale)
    |   |   +-- AuthorsProcessor                (creates/clones/updates authors + multi-locale)
    |   |   +-- GalleyProcessor                 (creates Galley objects)
    |   |   +-- SubmissionFileProcessor         (creates SubmissionFile records)
    |   |   +-- CategoriesProcessor             (creates/assigns categories + versioning + multi-locale)
    |   |   +-- SectionsProcessor               (resolves or creates sections)
    |   |   +-- KeywordsProcessor               (sets keywords per locale)
    |   |   +-- SubjectsProcessor               (sets subjects per locale)
    |   |   +-- FundersProcessor                (integrates with Funding plugin)
    |   |   +-- StatisticsProcessor             (inserts metrics_submission rows via DB::table())
    |   |
    |   |-- [File Handling Layer]
    |   |   +-- CsvFileHandler                  (SplFileObject read/write)
    |   |
    |   +-- [Post-processing]
    |       +-- syncCoverImagesForProcessedPreprints()
    |       +-- setCurrentVersionsForProcessedPreprints()
    |
    +-- UserCommand                             (orchestrator: 140 lines)
        |
        |-- [Validation Layer]
        |   +-- RequiredUserHeaders             (CSV schema: 11 columns, 5 required)
        |   +-- InvalidRowValidations           (reused from preprint flow)
        |
        |-- [Processing Layer]
        |   +-- UsersProcessor                  (creates User via Repo::user())
        |   +-- UserGroupsProcessor             (assigns roles to users)
        |   +-- UserInterestsProcessor          (sets review interests)
        |
        +-- [Email Layer]
            +-- WelcomeEmailHandler             (sends UserCreated mailable via Laravel Mail)
```

## Layers

**Entry Point Layer:**
- Purpose: Parse CLI arguments, validate the invoking user, dispatch to the correct command
- Location: `CSVImportExportPlugin.php`
- Contains: Argument parsing, user authentication, command dispatch via `match()`
- Depends on: OPS `ImportExportPlugin` base class, `Repo::user()`
- Used by: OPS CLI runner (`tools/runScheduledTasks.php`)

**Command Layer (Orchestrators):**
- Purpose: Read CSV files from source directory, iterate rows, validate, route to processors based on import type
- Location: `classes/commands/PreprintCommand.php`, `classes/commands/UserCommand.php`
- Contains: Row iteration loop, import-type detection (new/multi-locale/multi-version), error handling with catch-and-continue, post-processing hooks
- Depends on: Validation layer, Processor layer, CachedEntities, CsvFileHandler
- Used by: Entry point (`CSVImportExportPlugin.php`)

**Validation Layer:**
- Purpose: Define CSV schema (header contracts) and perform structural + semantic validation before any DB write
- Location: `classes/validations/RequiredPreprintHeaders.php`, `classes/validations/RequiredUserHeaders.php`, `classes/validations/InvalidRowValidations.php`
- Contains: Header definitions (allowed + required columns), field-level validators (email, ORCID, DOI, file existence, path traversal protection, server locale, versioning rules, funder format)
- Depends on: CachedEntities (for server/user lookups during validation), FundersProcessor (for plugin-enabled checks)
- Used by: Command layer

**Processor Layer:**
- Purpose: Perform single-domain DB operations via OPS `Repo` facade
- Location: `classes/processors/*.php` (14 processor classes)
- Contains: Static utility classes with up to three method variants per processor: `process()`, `processMultiLocale()`, `processForVersion()`
- Depends on: OPS `Repo` facade, `DAORegistry`, `DB::table()` (StatisticsProcessor only), Funding plugin DAOs (FundersProcessor only)
- Used by: Command layer exclusively

**Caching Layer:**
- Purpose: Memoize repeated DB lookups (servers, genres, user groups, categories, sections, users) to avoid N+1 queries
- Location: `classes/cachedAttributes/CachedEntities.php`
- Contains: Static properties per entity type, cache-null pattern for failed lookups
- Depends on: OPS `Repo` facade, `DAORegistry`
- Used by: Validation layer, Command layer, Processor layer (UsersProcessor, CategoriesProcessor)

**Handler Layer:**
- Purpose: Cross-cutting file I/O and email concerns
- Location: `classes/handlers/CsvFileHandler.php`, `classes/handlers/WelcomeEmailHandler.php`
- Contains: CSV file reading/writing via `SplFileObject`, invalid row output, welcome email sending via Laravel Mail
- Depends on: PHP `SplFileObject`, Laravel `Mail` facade, OPS `Repo::emailTemplate()`
- Used by: Command layer

**Exception Layer:**
- Purpose: Flow control for row-level failures
- Location: `classes/exceptions/RowValidationException.php`, `classes/exceptions/FileNotSavedException.php`
- Contains: Two simple exception classes extending PHP `Exception`
- Used by: Validation layer throws `RowValidationException`; `PreprintCommand::saveSubmissionFile()` throws `FileNotSavedException`

## Data Flow

**Preprint Import (New Submission):**

1. `CSVImportExportPlugin::executeCLI()` parses args, validates user, creates `PreprintCommand`
2. `PreprintCommand::run()` iterates CSV files in source directory via `DirectoryIterator`
3. For each CSV file, `CsvFileHandler::createReadableCSVFile()` opens it as `SplFileObject`
4. For each row: trim fields, combine with `RequiredPreprintHeaders::$preprintHeaders` into `$data` object
5. Structural validation: `InvalidRowValidations::validateRowContainAllFields()` checks column count
6. Semantic validation: required fields, versioning fields, galley/supp file existence, email, ORCID, server, locale, genre, user group, funders
7. Cover image uploaded if present via `PublicationProcessor::uploadCoverImage()`
8. Import-type routing based on `$processedPreprints` state:
   - **New submission**: `PublicationProcessor::createInitialPublication()` -> `SubmissionProcessor::process()` -> `PublicationProcessor::process()`
   - **Multi-locale** (same identifier+version, different locale): `PublicationProcessor::processMultiLocalePublication()`
   - **New version** (same identifier, higher version): `PublicationProcessor::createPublicationVersion()` -> `PublicationProcessor::processVersionedPublication()`
9. Stage assignment created if CSV specifies a valid username
10. Galley files uploaded via `saveSubmissionFile()`, then `SubmissionFileProcessor::process()` + `GalleyProcessor::process()`
11. Statistics inserted if preprintViews/galleyViews provided
12. Supplementary files processed similarly to galleys
13. Domain processors called: `AuthorsProcessor`, `KeywordsProcessor`, `SubjectsProcessor`, `FundersProcessor`, `PublicationProcessor::processSupportingAgencies()`
14. Section resolved or created via `SectionsProcessor::process()`
15. Categories assigned via `CategoriesProcessor`
16. Publication updated and reloaded
17. Row tracked in `$processedPreprints[identifier][version][locale]`
18. After all files: `syncCoverImagesForProcessedPreprints()` propagates cover images across locales; `setCurrentVersionsForProcessedPreprints()` marks highest version as current

**Preprint Import (Multi-Locale):**

1. Steps 1-6 same as new submission
2. At step 8, `versionExistsInAnyLocale()` returns true -> `$isMultiLocaleImport = true`
3. Reuses existing submission and publication objects
4. `PublicationProcessor::processMultiLocalePublication()` adds locale-specific fields to existing publication
5. Multi-locale variants of processors called: `AuthorsProcessor::processMultiLocale()`, `KeywordsProcessor::processMultiLocale()`, etc.

**Preprint Import (New Version):**

1. Steps 1-6 same as new submission
2. At step 8, identifier exists but version is new -> `$existingSubmission` and `$basePublication` set from previous version
3. `PublicationProcessor::createPublicationVersion()` creates a new Publication inheriting base data
4. `PublicationProcessor::processVersionedPublication()` overlays CSV data on inherited fields
5. Regular processors called with `$basePublication` param for inheritance fallback

**User Import:**

1. `CSVImportExportPlugin::executeCLI()` creates `UserCommand`
2. Same file iteration pattern as preprint
3. Validation: column count, required fields, server, email uniqueness, username uniqueness, roles, ORCID
4. `UsersProcessor::process()` creates user with encrypted password
5. `UserInterestsProcessor::process()` sets review interests
6. `UserGroupsProcessor::process()` assigns roles
7. `WelcomeEmailHandler::sendWelcomeEmail()` if `--sendWelcomeEmail` flag

**Failed Row Handling (both flows):**

1. `RowValidationException` or `FileNotSavedException` caught in command loop
2. `CsvFileHandler::createCSVFileInvalidRows()` creates `invalid_{filename}.csv` on first failure
3. `CsvFileHandler::processFailedRow()` appends the failed row + error reason column
4. Import continues with next row

**State Management:**
- `PreprintCommand::$processedPreprints` tracks `[identifier][version][locale]` -> `{data, submission, publication}` for within-file multi-locale/version routing
- `CachedEntities` uses static arrays for cross-row memoization (survives until process exit)
- No persistence between separate CLI invocations

## Key Abstractions

**Processor Method Variants:**
- Purpose: Make import semantics explicit at the call site for each domain
- Examples: `AuthorsProcessor::process()`, `AuthorsProcessor::processMultiLocale()`, `CategoriesProcessor::processForVersion()`
- Pattern: Each processor exposes up to three static methods. The command decides which to call based on import type.

**Header Classes as Data Contracts:**
- Purpose: Define the CSV schema (column names + required subset) as static arrays
- Examples: `classes/validations/RequiredPreprintHeaders.php` (35 columns, 5 required), `classes/validations/RequiredUserHeaders.php` (11 columns, 5 required)
- Pattern: `$preprintHeaders` defines all allowed columns; `$preprintRequiredHeaders` defines minimum. Row data is created via `array_combine($headers, $fields)`.

**CachedEntities as Static Memo Cache:**
- Purpose: Avoid repeated DB queries for the same entity within a single import run
- Examples: `classes/cachedAttributes/CachedEntities.php`
- Pattern: Static arrays keyed by entity identifier. Null cached for failed lookups. Garbage collected on process exit.

**Processed Preprints State Tracker:**
- Purpose: Enable within-file detection of multi-locale and multi-version rows
- Examples: `PreprintCommand::$processedPreprints`
- Pattern: Nested associative array `[identifier][version][locale]` -> `{data, submission, publication}`

## Entry Points

**CLI Entry Point:**
- Location: `CSVImportExportPlugin.php` -> `executeCLI($scriptName, &$args)`
- Triggers: OPS CLI runner: `php tools/runScheduledTasks.php plugins.importexport.csv.CSVImportExportPlugin {preprints|users} {username} {sourceDir} [--sendWelcomeEmail]`
- Responsibilities: Parse command/username/sourceDir, validate user, dispatch to PreprintCommand or UserCommand

**Plugin Registration:**
- Location: `CSVImportExportPlugin.php` -> `register($category, $path, $mainContextId)`
- Triggers: OPS plugin loader
- Responsibilities: Register plugin with OPS, load locale data

## Error Handling

**Strategy:** Row-level catch-and-continue with invalid row logging

**Patterns:**

- **RowValidationException**: Thrown by `InvalidRowValidations` static methods during pre-processing validation. Caught in command's main loop. Row written to `invalid_{filename}.csv` with error reason. Import continues with next row.

- **FileNotSavedException**: Thrown in `PreprintCommand::saveSubmissionFile()` when file upload fails. Before throwing, the entire submission is deleted via `Repo::submission()->delete()` (cascades to publications, authors, files). Any already-uploaded supplementary/galley files not yet linked are manually deleted via `$fileService->delete()`. Caught in the same catch block as RowValidationException.

- **Generic Exception in cover image upload**: Caught and re-thrown as RowValidationException to enter the standard failed-row path.

- **TransportException in email**: Caught in `WelcomeEmailHandler::sendWelcomeEmail()`. Creates a notification and echoes the error. Does not prevent user creation.

- **No partial entities**: If file saving fails mid-row, the submission (and all cascading entities) are deleted. The row is logged to the invalid file. The DB returns to a consistent state.

## Cross-Cutting Concerns

**Logging:** Console output via `echo` and OPS `__()` i18n function. No structured logging framework. Failed rows logged to `invalid_{filename}.csv` files in the source directory.

**Validation:** Two-phase: structural (column count via `validateRowContainAllFields`) then semantic (field-level checks in `InvalidRowValidations`). All validation completes before any DB write for the row. Security-critical: path traversal prevention via `validatePathWithinSourceDir()` using `realpath()`.

**Authentication:** CLI user validated via `Repo::user()->getByUsername()` at startup. User object passed to commands for file ownership and stage assignment. Per-row `username` field optionally resolves a different user for file upload ownership.

**Internationalization:** Locale strings via OPS `__()` function. Locale data in `locale/en/locale.po`. All user-facing messages are localized keys.

**Security:** Path traversal protection on all file references in CSV (`galleyFilenames`, `suppFilenames`, `coverImageFilename`, `references`). Cover image filenames sanitized with random prefix. ORCID validated via HTTP HEAD request.

---

*Architecture analysis: 2026-03-31*
