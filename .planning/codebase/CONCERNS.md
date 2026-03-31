# Codebase Concerns

**Analysis Date:** 2026-03-31

## Tech Debt

**Typo in locale key `importexpot` (missing `r`):**
- Issue: The locale key `plugins.importexpot.csv.fileProcessFinished` is misspelled (should be `importexport`). This typo is baked into production code, test assertions, and the locale file.
- Files: `classes/commands/PreprintCommand.php` (line 484), `classes/commands/UserCommand.php` (line 133), `locale/en/locale.po` (line 91), plus ~19 test assertions in `tests/Unit/Commands/PreprintCommandTest.php` and `tests/Unit/Commands/UserCommandTest.php`
- Impact: Cosmetic only since OPS locale system returns the key as-is when not found, but it signals a missed review step. Fixing requires updating all three locations simultaneously.
- Fix approach: Correct the key in `locale/en/locale.po`, both command classes, and all test assertions in a single commit.

**Typo in locale key `erroWhile` and wrong plugin prefix:**
- Issue: `plugin.importexport.csv.erroWhileSavingBookCoverImage` has two errors: the prefix is `plugin` instead of `plugins`, and `erro` is missing the final `r`.
- Files: `classes/processors/PublicationProcessor.php` (line 150), `locale/en/locale.po` (line 97)
- Impact: The error message will never resolve to the intended locale string. Users will see the raw key instead of a human-readable error.
- Fix approach: Correct both the prefix and the spelling in the locale key and the PHP call site.

**Duplicate test files for CsvFileHandler:**
- Issue: Two test files exist with different casing: `CSVFileHandlerTest.php` and `CsvFileHandlerTest.php` in `tests/Unit/Handlers/`. They contain identical test logic but with different class names and copyright years.
- Files: `tests/Unit/Handlers/CSVFileHandlerTest.php`, `tests/Unit/Handlers/CsvFileHandlerTest.php`
- Impact: Both files run during `phpunit`, doubling test execution time for that handler. Risk of divergence if one is updated but not the other.
- Fix approach: Delete one file (keep `CsvFileHandlerTest.php` to match the class name `CsvFileHandler`).

**Stale docblock on UserCommand:**
- Issue: The class-level docblock says "Handles the issue import when the user uses the issue command" -- this is a copy-paste from a different plugin. Same issue on `CsvFileHandler`.
- Files: `classes/commands/UserCommand.php` (line 15), `classes/handlers/CsvFileHandler.php` (line 15)
- Impact: Misleading for developers reading the code. No runtime effect.
- Fix approach: Update docblocks to accurately describe each class.

**Commented-out uncertainty in RequiredPreprintHeaders:**
- Issue: Line 86 contains the comment `//1 is enough to cover duplicate check?` -- an unresolved design question left in production code.
- Files: `classes/validations/RequiredPreprintHeaders.php` (line 86)
- Impact: The `$version > 1` check skips required-field validation for all versions above 1, which may be intentionally lenient for versioned imports but the comment indicates uncertainty about correctness.
- Fix approach: Verify the behavior is correct for multi-version imports and either remove the comment or add a proper explanation.

**`@TODO` for exception type change:**
- Issue: Line 353 of PreprintCommand has `// @TODO: change the exception type here` for supplementary file handling. The code currently throws `FileNotSavedException` which triggers submission deletion -- this may be too aggressive for a supplementary file failure.
- Files: `classes/commands/PreprintCommand.php` (line 353)
- Impact: A single supplementary file failure deletes the entire submission (including already-uploaded galley files). The cascading delete behavior is overkill for supplementary file errors.
- Fix approach: Create a dedicated exception (e.g., `SupplementaryFileException`) that logs the failure and skips the supplementary file without deleting the submission.

## Known Bugs

**`syncCoverImagesForProcessedPreprints` updates wrong publication object:**
- Symptoms: Cover image sync for multi-locale preprints may silently fail or produce stale data.
- Files: `classes/commands/PreprintCommand.php` (lines 693-696)
- Trigger: When a cover image needs to be propagated to a locale that lacks one, the method loads `$reloadedPublication` but then calls `Repo::publication()->dao->update($publication)` (the original, non-reloaded object). The `setCoverImage` call modifies `$reloadedPublication`, but the update persists `$publication`.
- Workaround: None currently. The cover image data set on `$reloadedPublication` is lost.
- Fix: Change line 696 from `Repo::publication()->dao->update($publication)` to `Repo::publication()->dao->update($reloadedPublication)`.

**Double-negation readability issue in cover image sync:**
- Symptoms: The condition `if (!(!isset($coverImagesByLocale[$locale]) && $sourceCoverImage))` on line 689 is hard to reason about.
- Files: `classes/commands/PreprintCommand.php` (line 689)
- Trigger: Always present, makes code review and maintenance error-prone.
- Workaround: Functionally equivalent to `if (isset($coverImagesByLocale[$locale]) || !$sourceCoverImage)`.
- Fix: Rewrite as `if (isset($coverImagesByLocale[$locale]) || !$sourceCoverImage) { continue; }` for clarity.

## Security Considerations

**Path traversal protection is implemented correctly:**
- Risk: CSV filenames could contain `../` sequences to escape the source directory.
- Files: `classes/validations/InvalidRowValidations.php` (lines 38-51)
- Current mitigation: `validatePathWithinSourceDir()` uses `realpath()` to resolve symlinks and checks that the resolved path starts with the source directory. This is called for cover images, galleys, supplementary files, and references.
- Recommendations: Coverage is good. No action needed.

**ORCID validation makes external HTTP requests:**
- Risk: During user import, each ORCID is validated via an HTTP HEAD request to `orcid.org`. A CSV with many rows could generate excessive outbound requests, potentially causing rate limiting or slow imports.
- Files: `classes/validations/InvalidRowValidations.php` (lines 420-436)
- Current mitigation: 5-second connect timeout per request. Failures return `false` (ORCID treated as not found, row rejected).
- Recommendations: Consider adding a flag to skip ORCID validation for bulk imports, or batch/cache ORCID lookups to reduce external requests.

**Passwords stored using `encryptCredentials` with username as salt:**
- Risk: If `tempPassword` is not provided, a random password is generated and encrypted using the username as a salt via `Validation::encryptCredentials($data->username, $data->tempPassword)`.
- Files: `classes/processors/UsersProcessor.php` (line 37)
- Current mitigation: This follows OPS's built-in password handling pattern. Generated passwords are strong randoms via `Validation::generatePassword()`.
- Recommendations: No additional action needed as this follows the host framework's convention.

## Performance Bottlenecks

**No database transactions for row-level operations:**
- Problem: Each CSV row triggers multiple individual INSERT/UPDATE queries (submission, publication, authors, keywords, subjects, categories, galleys, files, statistics) without wrapping them in a database transaction.
- Files: `classes/commands/PreprintCommand.php` (lines 287-458), `classes/commands/UserCommand.php` (lines 110-115)
- Cause: Each processor independently calls `Repo::*()->add()` or `Repo::*()->edit()`, resulting in auto-committed queries.
- Improvement path: Wrap the per-row processing block in `DB::beginTransaction()` / `DB::commit()` with `DB::rollBack()` in the catch block. This would also eliminate the need for the manual submission-deletion cleanup in `FileNotSavedException` handling, as the rollback would undo all partial writes.

**`syncCoverImagesForProcessedPreprints` bypasses the Repo facade with raw DB queries:**
- Problem: The method queries `publication_settings` directly via `DB::table()` instead of using the `Repo` facade, and fetches the server via `DAORegistry` instead of `CachedEntities`.
- Files: `classes/commands/PreprintCommand.php` (lines 643-686)
- Cause: The method was likely written to avoid loading full ORM objects, but it creates inconsistency with the rest of the codebase.
- Improvement path: Use `CachedEntities::getCachedServer()` for server lookups. Consider using `Repo::publication()->get()` with the publication object's existing data instead of raw queries.

**`CachedEntities::getCachedCategory` queries all categories per lookup:**
- Problem: Each call to `getCachedCategory` for an uncached category executes `Repo::category()->getCollector()->filterByContextIds(...)->getMany()`, loading ALL categories for the server. The result is iterated to find a match, but only the matching category is cached.
- Files: `classes/cachedAttributes/CachedEntities.php` (lines 162-179)
- Cause: The collector returns all categories but only the matched one is stored.
- Improvement path: On first access for a given serverId, cache ALL categories in a map keyed by path. Subsequent lookups become O(1) hash lookups instead of repeated full queries.

**`CachedEntities::getCachedSection` has the same pattern:**
- Problem: Same as categories -- loads all sections every time an uncached section is looked up.
- Files: `classes/cachedAttributes/CachedEntities.php` (lines 182-201)
- Improvement path: Cache all sections for a server on first access.

**ORCID HTTP validation per author per row:**
- Problem: Each author with an ORCID triggers an HTTP HEAD request during validation. A CSV with 1000 rows and 3 authors each = 3000 HTTP requests.
- Files: `classes/validations/InvalidRowValidations.php` (lines 420-436)
- Cause: No caching of previously validated ORCIDs.
- Improvement path: Add a static cache of validated ORCIDs in `InvalidRowValidations` or `CachedEntities` to avoid redundant HTTP requests for the same ORCID across rows.

## Fragile Areas

**PreprintCommand.run() method (737 lines total, ~370 lines in the main loop):**
- Files: `classes/commands/PreprintCommand.php`
- Why fragile: The `run()` method contains the entire orchestration logic in a single method with deeply nested conditionals. The main `foreach` loop spans from line 142 to line 482 with multiple try-catch blocks, conditional processor invocations, and state tracking.
- Safe modification: Always add new processing steps at a clearly demarcated point (after categories, before tracking). Test with multi-locale, multi-version, and single-version CSV fixtures.
- Test coverage: Well covered by `PreprintCommandTest.php` (1485 lines), but the method's complexity makes edge cases easy to miss.

**Author parsing via comma/semicolon splitting:**
- Files: `classes/processors/AuthorsProcessor.php` (lines 273-288)
- Why fragile: Authors are parsed by splitting on `;` (between authors) and `,` (between fields). Author names, affiliations, or biographies containing commas will be incorrectly parsed. Example: an affiliation like "University of California, Berkeley" would shift all subsequent fields.
- Safe modification: This is a fundamental CSV-within-CSV limitation. Changing the delimiter would break existing CSVs.
- Test coverage: Tests exist but do not cover edge cases with embedded commas.

**Galley DOI shared with publication DOI:**
- Files: `classes/processors/GalleyProcessor.php` (lines 34-36)
- Why fragile: The galley receives the same DOI as the publication (`$data->doi`). If a preprint has multiple galleys, all receive the same DOI, which violates DOI uniqueness requirements.
- Safe modification: Add a separate `galleyDoi` column to the CSV headers, or only assign DOI to the first galley.
- Test coverage: Not specifically tested for multi-galley DOI conflicts.

## Scaling Limits

**In-memory `$processedPreprints` tracking:**
- Current capacity: Stores all processed preprints with their full Submission and Publication objects in memory.
- Limit: For very large imports (tens of thousands of versioned preprints), memory usage could grow significantly since each entry holds full ORM objects.
- Scaling path: Store only IDs (submissionId, publicationId) instead of full objects in the tracking array. Reload objects only when needed.

**Username generation retry loop:**
- Files: `classes/processors/UsersProcessor.php` (lines 53-67)
- Current capacity: Works for small batches.
- Limit: The `do-while` loop generates random 3-letter suffixes and checks for uniqueness. With 26^3 = 17,576 possible suffixes, collisions become likely at scale for common names. The loop has no maximum retry count.
- Scaling path: Add a retry limit (e.g., 100 attempts) and fall back to longer suffixes or UUIDs.

## Dependencies at Risk

**Tight coupling to Funding plugin internals:**
- Risk: `FundersProcessor` directly instantiates `FunderDAO` and `FunderAwardDAO` from `APP\plugins\generic\funding\classes\*`. If the Funding plugin changes its internal class structure, this plugin will break.
- Files: `classes/processors/FundersProcessor.php` (lines 19-25, 103, 143)
- Impact: Any update to the Funding plugin's DAO layer requires corresponding updates here.
- Migration plan: Consider using the Funding plugin's public API if one exists, or document the version dependency explicitly.

## Missing Critical Features

**No `--dry-run` mode:**
- Problem: There is no way to validate a CSV without actually importing it. Users must import to discover errors.
- Blocks: Safe testing of large CSV files before committing to a production import.

**No import progress indicator:**
- Problem: For large CSV files, there is no progress output during processing. The only output is the final summary line per file.
- Blocks: Users cannot estimate import completion time or detect hung processes.

## Test Coverage Gaps

**StatisticsProcessor has no dedicated test file:**
- What's not tested: `StatisticsProcessor::insertPreprintViews()`, `insertGalleyViews()`, and `resolveFileType()` have no unit tests. They are only indirectly tested via `PreprintCommandTest`.
- Files: `classes/processors/StatisticsProcessor.php`
- Risk: Direct DB inserts to `metrics_submission` could silently break if the table schema changes.
- Priority: Medium

**Duplicate test file wastes CI time:**
- What's not tested: N/A -- the duplicate `CSVFileHandlerTest.php` / `CsvFileHandlerTest.php` runs the same tests twice.
- Files: `tests/Unit/Handlers/CSVFileHandlerTest.php`, `tests/Unit/Handlers/CsvFileHandlerTest.php`
- Risk: No coverage gap, but doubles execution time for handler tests and may cause confusion.
- Priority: Low

**Author parsing edge cases:**
- What's not tested: Authors with commas in names/affiliations, empty author strings within a semicolon-delimited list, authors with only ORCID and no email.
- Files: `classes/processors/AuthorsProcessor.php` (lines 273-288)
- Risk: Malformed author data could silently create incorrect author records.
- Priority: Medium

**Multi-galley DOI assignment:**
- What's not tested: Behavior when multiple galleys share the same DOI from `$data->doi`.
- Files: `classes/processors/GalleyProcessor.php` (lines 34-36)
- Risk: DOI uniqueness violations at the galley level.
- Priority: Medium

## TODOs & FIXMEs Found

| Location | Description |
|---|---|
| `classes/commands/PreprintCommand.php:353` | `@TODO: change the exception type here` -- supplementary file error handling uses `FileNotSavedException` which triggers submission deletion |
| `classes/validations/RequiredPreprintHeaders.php:86` | `//1 is enough to cover duplicate check?` -- unresolved design question about version validation logic |

## Recommendations

1. **Fix the cover image sync bug** in `syncCoverImagesForProcessedPreprints()` -- line 696 updates `$publication` instead of `$reloadedPublication`. This is a data-loss bug.
2. **Wrap per-row processing in database transactions** to eliminate partial writes and simplify the manual cleanup logic in `FileNotSavedException` handling.
3. **Delete the duplicate test file** `tests/Unit/Handlers/CSVFileHandlerTest.php` (keep `CsvFileHandlerTest.php`).
4. **Fix the locale key typos**: `importexpot` -> `importexport` and `erroWhile` -> `errorWhile` with correct `plugins.` prefix.
5. **Cache all categories/sections per server on first access** in `CachedEntities` instead of querying all and caching one.
6. **Add ORCID validation caching** to avoid redundant HTTP requests for the same ORCID across CSV rows.
7. **Create a StatisticsProcessor unit test file** to cover direct DB insert operations.
8. **Resolve the `@TODO` on supplementary file exception handling** -- supplementary file failures should not delete the entire submission.
9. **Address the galley DOI sharing issue** -- either add a `galleyDoi` CSV column or only assign DOI to the first galley.
10. **Add a `--dry-run` flag** to validate CSV files without performing database writes.

---

*Concerns audit: 2026-03-31*
