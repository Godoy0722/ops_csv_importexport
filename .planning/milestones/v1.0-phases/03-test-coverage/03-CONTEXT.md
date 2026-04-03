# Phase 3: Test Coverage - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Unit tests verifying every dry-mode code path and asserting the no-write guarantee. Tests exercise the full command-level `run()` flow (transaction wrapping, processor execution, rollback, reporting, exit codes) for both `PreprintCommand` and `UserCommand` in dry-mode. No new production code is written in this phase.

</domain>

<decisions>
## Implementation Decisions

### Test Scope & Granularity
- **D-01:** Tests are command-level only — test dry-mode through `PreprintCommand::run()` and `UserCommand::run()` with mocked repos. DryModeReporter is implicitly covered through command output assertions, not tested separately.
- **D-02:** No separate entry point flag-parsing tests — the flag wiring is simple (`array_search` + constructor param) and exercised by every command test that passes `$dryMode = true`.
- **D-03:** Tests MUST assert that `CachedEntities::reset()` is called after each file's rollback — this is a core dry-mode invariant (TXVAL-03) preventing stale ID leakage between files.

### No-Write Assertion Strategy
- **D-04:** Use Mockery `shouldNotReceive` / `shouldReceive()->times(0)` on Repo write methods that should not persist, to verify the no-write guarantee (TEST-02).
- **D-05:** Mock `DB::shouldReceive('beginTransaction')` and `DB::shouldReceive('rollBack')` to explicitly verify the transaction lifecycle — clear proof of the rollback contract.
- **D-06:** Combine mock expectations with exit code assertions: Repo write mocks prove processors ran inside the transaction, DB facade mocks prove rollback happened, exit code proves the contract is fulfilled.

### Test Data & Scenarios
- **D-07:** Comprehensive scenario coverage including:
  - All-valid rows -> exit 0, report shows all passed
  - Mixed valid + invalid rows -> exit 1, report shows failures with reasons
  - Multi-file processing with CachedEntities::reset() between files
  - Multi-locale preprints (same identifier, different locales)
  - Multi-version preprints (same identifier, different versions)
  - Empty files / files with only invalid rows
  - `--dry-mode` + `--sendWelcomeEmail` interaction (dry-mode wins, no emails sent)
- **D-08:** Reuse existing test builders: `CsvTestDataBuilder`, `MultiVersionScenarioBuilder`, and `MockFactory` fluent APIs — follows existing patterns and handles header/field combinations correctly.

### File Organization
- **D-09:** Create new dedicated test files: `PreprintCommandDryModeTest.php` and `UserCommandDryModeTest.php` in `tests/Unit/Commands/`. Existing command test files are already large; separate files keep dry-mode tests focused and independently runnable.
- **D-10:** No separate `DryModeReporterTest.php` — reporter output is verified through command test stdout assertions, consistent with the command-level scope decision (D-01).

### Claude's Discretion
- Exact test method names and section banner organization within the new test files
- How to structure setUp/tearDown for DB facade mocking alongside existing BaseTestCase patterns
- Whether to use data providers for scenario variations or individual test methods
- Specific Mockery expectation syntax for verifying processor calls inside transactions

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Commands (test targets)
- `classes/commands/PreprintCommand.php` — Preprint import orchestrator with dry-mode transaction wrapping, filesystem guards, DryModeReporter calls. Primary test target.
- `classes/commands/UserCommand.php` — User import orchestrator with dry-mode transaction wrapping. Primary test target.

### Dry-Mode Infrastructure
- `classes/handlers/DryModeReporter.php` — Report formatting (implicitly tested through commands)
- `classes/cachedAttributes/CachedEntities.php` — Static cache with `reset()` method. Must assert reset is called after rollback.
- `CSVImportExportPlugin.php` — Entry point with `--dry-mode` flag parsing

### Test Infrastructure (patterns to follow)
- `tests/BaseTestCase.php` — Base class with repo mock helpers, entity creators, CachedEntities backup/restore, DB transaction helpers
- `tests/Fixtures/CsvTestDataBuilder.php` — Fluent builder for CSV test data, includes `MultiVersionScenarioBuilder`
- `tests/Fixtures/MockFactory.php` — Fluent builder for domain object mocks

### Existing Command Tests (reference for patterns)
- `tests/Unit/Commands/PreprintCommandTest.php` — 47 tests, normal-mode patterns to follow
- `tests/Unit/Commands/UserCommandTest.php` — 36 tests, normal-mode patterns to follow

### Requirements
- `.planning/REQUIREMENTS.md` — TEST-01 (dry-mode covered by unit tests), TEST-02 (tests verify zero DB writes)

### Prior Phase Context
- `.planning/phases/02.1-transaction-based-dry-mode-validation/02.1-CONTEXT.md` — D-01 through D-16 (transaction scope, filesystem guards, CachedEntities reset, guard restructuring)
- `.planning/phases/02-reporting-and-exit-codes/02-CONTEXT.md` — D-01 through D-13 (report format, exit codes)
- `.planning/phases/01-flag-wiring-and-write-suppression/01-CONTEXT.md` — D-01 through D-07 (flag propagation, write suppression)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `BaseTestCase` repo mock helpers (`mockAuthorRepository()`, `mockSubmissionRepository()`, etc.) — pre-configured with `->byDefault()` expectations, easily overridden per test
- `CsvTestDataBuilder::minimalPreprintRow()`, `completePreprintRow()`, `minimalUserRow()`, `completeUserRow()` — quick test data
- `MultiVersionScenarioBuilder` — for multi-version/multi-locale preprint scenarios
- `MockFactory::server()`, `MockFactory::submission()`, etc. — domain object builders
- `BaseTestCase::createTempDirectory()`, `createTestCsvFile()` — file-based test helpers

### Established Patterns
- `#[CoversClass(ClassName::class)]` attribute on every test class
- Section comment banners: `// ==================== Section Name ====================`
- setUp/tearDown calling parent for CachedEntities backup/restore and Mockery cleanup
- Repo mocks registered in Laravel container via `app()->instance()`
- `expectOutputString()` or `ob_start()`/`ob_get_clean()` for stdout capture

### Integration Points
- New test files extend `BaseTestCase` and use its full mock infrastructure
- Tests construct commands directly with `$dryMode = true` constructor param
- Tests create temp directories with CSV files, run `$command->run()`, assert on exit code + captured output + mock expectations

</code_context>

<specifics>
## Specific Ideas

- User wants comprehensive scenario coverage including multi-locale, multi-version, edge cases, and flag interaction (`--dry-mode` + `--sendWelcomeEmail`)
- CachedEntities::reset() assertion is explicitly required — not optional
- DB facade mocking (beginTransaction/rollBack) is explicitly required to prove rollback contract
- Reporter formatting is only tested through command output, not in isolation

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 03-test-coverage*
*Context gathered: 2026-04-01*
