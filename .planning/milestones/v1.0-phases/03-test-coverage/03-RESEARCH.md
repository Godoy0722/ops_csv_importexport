# Phase 3: Test Coverage - Research

**Researched:** 2026-04-02
**Domain:** PHPUnit 11 + Mockery 1.6 — command-level dry-mode testing with DB facade mocking
**Confidence:** HIGH

## Summary

Phase 3 adds no production code. Its entire scope is writing two new test files — `PreprintCommandDryModeTest.php` and `UserCommandDryModeTest.php` — that drive `PreprintCommand::run()` and `UserCommand::run()` with `$dryMode = true` and assert the transaction lifecycle, the no-write guarantee, and the `CachedEntities::reset()` invariant.

All patterns needed are already in the codebase. `PreprintCommandTest.php` demonstrates every technique required: `Mockery::mock('overload:...')` for static processors and validators, `DB::swap()` for facade mocking, `ob_start()`/`ob_get_clean()` for stdout capture, `#[RunInSeparateProcess]` + `#[PreserveGlobalState(false)]` for overload isolation, and `CachedEntities` pre-population via direct static assignment. The dry-mode tests follow the identical skeleton — they just swap `new PreprintCommand($dir, $user)` for `new PreprintCommand($dir, $user, true)` and add DB facade expectations.

The main planning challenge is scenario breadth. The CONTEXT decisions enumerate seven scenario groups (D-07) that must be covered for both commands, alongside the three structural invariants (transaction rollback D-05, no writes D-04, cache reset D-03). Each scenario requires careful mock setup to let processors run inside the transaction while still producing meaningful output assertions.

**Primary recommendation:** Reuse `setupAllMocks()` as the model for a per-test-class `setupDryModeMocks()` helper that pre-wires the DB facade mock (`beginTransaction`, `rollBack`, `statement`) alongside all processor overloads, then assert those expectations after `run()`.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Tests are command-level only — test dry-mode through `PreprintCommand::run()` and `UserCommand::run()` with mocked repos. DryModeReporter is implicitly covered through command output assertions, not tested separately.
- **D-02:** No separate entry point flag-parsing tests — the flag wiring is simple and exercised by every command test that passes `$dryMode = true`.
- **D-03:** Tests MUST assert that `CachedEntities::reset()` is called after each file's rollback — this is a core dry-mode invariant (TXVAL-03) preventing stale ID leakage between files.
- **D-04:** Use Mockery `shouldNotReceive` / `shouldReceive()->times(0)` on Repo write methods that should not persist, to verify the no-write guarantee (TEST-02).
- **D-05:** Mock `DB::shouldReceive('beginTransaction')` and `DB::shouldReceive('rollBack')` to explicitly verify the transaction lifecycle — clear proof of the rollback contract.
- **D-06:** Combine mock expectations with exit code assertions: Repo write mocks prove processors ran inside the transaction, DB facade mocks prove rollback happened, exit code proves the contract is fulfilled.
- **D-07:** Comprehensive scenario coverage including: all-valid rows (exit 0, report shows all passed), mixed valid + invalid rows (exit 1, report shows failures with reasons), multi-file processing with CachedEntities::reset() between files, multi-locale preprints, multi-version preprints, empty files / files with only invalid rows, `--dry-mode` + `--sendWelcomeEmail` interaction (dry-mode wins, no emails sent).
- **D-08:** Reuse existing test builders: `CsvTestDataBuilder`, `MultiVersionScenarioBuilder`, and `MockFactory` fluent APIs.
- **D-09:** Create new dedicated test files: `PreprintCommandDryModeTest.php` and `UserCommandDryModeTest.php` in `tests/Unit/Commands/`. Separate files keep dry-mode tests focused and independently runnable.
- **D-10:** No separate `DryModeReporterTest.php` — reporter output verified through command test stdout assertions.

### Claude's Discretion

- Exact test method names and section banner organization within the new test files
- How to structure setUp/tearDown for DB facade mocking alongside existing BaseTestCase patterns
- Whether to use data providers for scenario variations or individual test methods
- Specific Mockery expectation syntax for verifying processor calls inside transactions

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| TEST-01 | Dry-mode functionality is covered by unit tests following existing BaseTestCase/CsvTestDataBuilder patterns | `PreprintCommandDryModeTest` and `UserCommandDryModeTest` extend `BaseTestCase`, use `CsvTestDataBuilder` builders, follow `#[CoversClass]` + section banner conventions established in existing command tests |
| TEST-02 | Tests verify that no database writes occur during dry-mode execution | D-04: Mockery `shouldNotReceive` / `times(0)` on Repo write methods; D-05: `DB::swap()` to intercept and assert `beginTransaction` + `rollBack` calls |
</phase_requirements>

---

## Standard Stack

### Core (no new dependencies — all already present)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | 11.5.11 | Test runner, attribute-driven test metadata | Already the project test runner; config at `phpunit.xml` |
| Mockery | 1.6.12 | Static class overloading via `mock('overload:...')`, partial mocks, `shouldNotReceive` | Already used in every command test; handles static processor interception |
| Laravel DB Facade | ^11.0 | `DB::swap()` to inject mock root for transaction verification | Already used in `PreprintCommandTest` line 1207-1210 |

### No installation required

All dependencies are provided by the host OPS application. The plugin has no `composer.json`. Running tests:

```bash
# Full suite
php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml

# Single new file (once created)
php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml tests/Unit/Commands/PreprintCommandDryModeTest.php

# Single method
php ../../../../lib/pkp/lib/vendor/bin/phpunit --configuration phpunit.xml --filter testDryModeAllValidRowsExitsZero
```

---

## Architecture Patterns

### Recommended Project Structure

```
tests/Unit/Commands/
├── PreprintCommandTest.php          # existing (47 tests, normal-mode)
├── PreprintCommandDryModeTest.php   # NEW — dry-mode tests
├── UserCommandTest.php              # existing (36 tests, normal-mode)
└── UserCommandDryModeTest.php       # NEW — dry-mode tests
```

### Pattern 1: Test File Skeleton

Every test class in this project follows this skeleton exactly:

```php
namespace APP\plugins\importexport\csv\tests\Unit\Commands;

use APP\plugins\importexport\csv\classes\commands\PreprintCommand;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

#[CoversClass(PreprintCommand::class)]
class PreprintCommandDryModeTest extends BaseTestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();  // calls backupCachedStatics(), Mockery setup
        $this->tempDir = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->cleanupTempDirectory($this->tempDir);
        parent::tearDown();  // calls restoreCachedStatics(), Mockery::close()
    }

    // ==================== Section Name ====================

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSomething(): void { ... }
}
```

`#[RunInSeparateProcess]` + `#[PreserveGlobalState(false)]` are **required on every test that uses `Mockery::mock('overload:...')`** — Mockery class overloads pollute the global class registry and cannot coexist across test methods in the same process.

### Pattern 2: DB Facade Mocking for Transaction Verification (D-05)

Derived from `PreprintCommandTest.php` line 1207-1210 and the `UserCommand`/`PreprintCommand` source which calls `DB::statement`, `DB::beginTransaction`, `DB::rollBack`:

```php
// Source: tests/Unit/Commands/PreprintCommandTest.php:1207
$realDb = \Illuminate\Support\Facades\DB::getFacadeRoot();
$dbMock = Mockery::mock($realDb)->makePartial();

// Assert transaction lifecycle (D-05)
$dbMock->shouldReceive('statement')->with('SET FOREIGN_KEY_CHECKS=0')->once();
$dbMock->shouldReceive('beginTransaction')->once();
$dbMock->shouldReceive('rollBack')->once();
$dbMock->shouldReceive('statement')->with('SET FOREIGN_KEY_CHECKS=1')->once();

\Illuminate\Support\Facades\DB::swap($dbMock);
```

`makePartial()` is critical — it lets unmocked DB calls (e.g., `DB::table()` inside processors) fall through to the real facade root.

### Pattern 3: No-Write Assertion on Repo Mocks (D-04)

After calling `setupAllMocks()` (which sets `->byDefault()` expectations permitting writes), override specific write expectations with `times(0)` or `shouldNotReceive` in the dry-mode test:

```php
// From BaseTestCase mock helpers — byDefault() permits calls
$subRepoMock = $this->mockSubmissionRepository();  // add() byDefault() allows
$pubRepoMock = $this->mockPublicationRepository();  // add() byDefault() allows

// Override for dry-mode no-write verification (D-04)
// NOTE: In dry-mode the transaction rollback is the real write-prevention mechanism.
// The processors DO call Repo write methods (that is the point of transaction wrapping).
// So "no writes" means "no persistent writes" — verified by asserting rollBack, not
// by asserting zero Repo::add() calls. The Repo mocks still need to return valid objects.
// Use shouldNotReceive only for methods that must NEVER be called in dry-mode:
$subRepoMock->shouldNotReceive('delete');  // no cleanup deletes in dry-mode
```

**Critical nuance (D-04 vs transaction semantics):** The dry-mode architecture (Phase 02.1) runs ALL processors inside a transaction and rolls back. The Repo write methods (`add`, `edit`) ARE called — that is intentional, so validation can exercise real DB lookups for cross-row consistency. The no-write guarantee comes from `DB::rollBack()`, not from suppressing `add()` calls. `shouldNotReceive` is appropriate for operations that must never fire in dry-mode at all (e.g., `Repo::submission()->delete()`, welcome email sending).

For the "zero persistent DB writes" claim: assert that `DB::rollBack()` was called exactly once per file. That is the machine-verifiable proof (D-05, D-06).

### Pattern 4: CachedEntities::reset() Verification (D-03)

`CachedEntities::reset()` is a static void method. It cannot be mocked with Mockery overload in the same process as other static mocks because it is not a processor — it is a data class. The correct assertion strategy is:

```php
// Pre-populate the cache to prove it gets cleared
CachedEntities::$servers['testserver'] = $server;
CachedEntities::$genreIds['SUBMISSION'] = 1;
CachedEntities::$userGroupIds['testserver'] = 1;

ob_start();
$command->run();  // internally calls CachedEntities::reset()
ob_get_clean();

// Assert reset() fired: all static caches are empty after run()
$this->assertEmpty(CachedEntities::$servers);
$this->assertEmpty(CachedEntities::$genreIds);
$this->assertEmpty(CachedEntities::$userGroupIds);
$this->assertEmpty(CachedEntities::$categories);
$this->assertEmpty(CachedEntities::$sections);
$this->assertEmpty(CachedEntities::$users);
```

This is reliable because `BaseTestCase::backupCachedStatics()` clears the caches in `setUp()` (giving a known-empty starting point), and `restoreCachedStatics()` in `tearDown()` undoes test mutations. Pre-populating then asserting empty is a direct behavioral assertion.

### Pattern 5: stdout Capture for Reporter Verification (D-01, D-10)

```php
ob_start();
$exitCode = $command->run();
$output = ob_get_clean();

// Reporter output (DryModeReporter static methods echo i18n keys)
$this->assertStringContainsString('plugins.importexport.csv.dryModeFileHeader', $output);
$this->assertStringContainsString('plugins.importexport.csv.dryModeFileSummary', $output);
$this->assertStringContainsString('plugins.importexport.csv.dryModeGrandTotal', $output);

// Exit code (D-06)
$this->assertSame(0, $exitCode);   // all-valid scenario
$this->assertSame(1, $exitCode);   // any-failure scenario
```

The i18n `__()` function returns the message key as a string in test context (no real locale), so asserting on the key string is reliable.

### Pattern 6: setupAllMocks() as the Foundation for setupDryModeMocks()

The existing `setupAllMocks()` helper in `PreprintCommandTest` (line 400-543) overloads all static processors and pre-populates `CachedEntities`. For dry-mode tests, a `setupDryModeMocks()` helper adds the DB facade mock on top:

```php
private function setupDryModeMocks(): array
{
    $mocks = $this->setupAllMocksBase();  // or inline the same logic

    $realDb = \Illuminate\Support\Facades\DB::getFacadeRoot();
    $dbMock = Mockery::mock($realDb)->makePartial();
    $dbMock->shouldReceive('statement')->byDefault();
    $dbMock->shouldReceive('beginTransaction')->byDefault();
    $dbMock->shouldReceive('rollBack')->byDefault();
    \Illuminate\Support\Facades\DB::swap($dbMock);

    $mocks['dbMock'] = $dbMock;
    return $mocks;
}
```

Per-test specific assertions (e.g., `->once()` count checks) are set after calling the helper.

### Pattern 7: sendWelcomeEmail Guard Verification (D-07, UserCommand)

`UserCommand::run()` guards: `if ($this->sendWelcomeEmail && !$this->dryMode)`. Test with `$dryMode = true, $sendWelcomeEmail = true`:

```php
$welcomeEmailMock = Mockery::mock('overload:' . WelcomeEmailHandler::class);
$welcomeEmailMock->shouldNotReceive('sendWelcomeEmail');  // must never fire in dry-mode

$command = new UserCommand($this->tempDir, $senderUser, true, true);  // sendWelcomeEmail=true, dryMode=true
```

### Anti-Patterns to Avoid

- **Missing `#[RunInSeparateProcess]`:** Any test using `Mockery::mock('overload:...')` without this attribute will corrupt Mockery's class registry for subsequent tests. Every dry-mode test that overloads processors or validators requires the attribute.
- **Asserting zero `Repo::add()` calls in dry-mode:** This is wrong. Processors call `add()` inside the transaction — they are intended to. The rollback is what prevents persistence. Assert `rollBack` was called, not that `add` was not called.
- **Forgetting `makePartial()` on DB mock:** Without `makePartial()`, ALL DB method calls fail, including `DB::table()` calls inside processors for statistics. Use `makePartial()` so only explicitly declared expectations are intercepted.
- **CachedEntities reset assertion in wrong position:** The cache assertion must come AFTER `run()`. Pre-populate BEFORE `run()`. If you assert before run, the test trivially passes because setUp clears caches.
- **Multi-file test with single CSV:** The `CachedEntities::reset()` and `$processedPreprints = []` calls only execute once per file in the outer `foreach`. To test the reset happens between files, you must create two CSV files in the temp directory.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Static class interception | Custom mock proxy | `Mockery::mock('overload:ClassName')` | Mockery overload replaces the class autoloader entry for the process lifetime; nothing else achieves this cleanly |
| DB facade substitution | Reflection hacks | `DB::getFacadeRoot()` + `DB::swap($mock)` | The facade swap API is the official pattern; already used in PreprintCommandTest |
| CSV test data construction | Raw `array_fill` or hardcoded arrays | `CsvTestDataBuilder::preprint()->...->buildArray()` | Builder ensures headers/values align correctly; manual arrays silently misalign with header changes |
| CachedEntities state management | Direct static assignment in tearDown | `BaseTestCase::backupCachedStatics()` / `restoreCachedStatics()` via `parent::setUp()/tearDown()` | Already handled automatically; test author must only call `parent::setUp()` |

---

## Common Pitfalls

### Pitfall 1: DB mock `statement()` call ambiguity

**What goes wrong:** `DB::statement('SET FOREIGN_KEY_CHECKS=0')` and `DB::statement('SET FOREIGN_KEY_CHECKS=1')` are called around the transaction. If the DB mock uses `->shouldReceive('statement')->once()`, it only matches one call and the second fails. StatisticsProcessor may also call `DB::table()` which `makePartial()` routes to the real root — but the real root may need a live DB.
**Why it happens:** Both commands call `DB::statement` twice (before beginTransaction and after rollBack) — two separate calls with different argument strings.
**How to avoid:** Use `->shouldReceive('statement')->withAnyArgs()->byDefault()` in `setupDryModeMocks()` and override with `.times(2)` only in tests that specifically verify the FK-checks guard.
**Warning signs:** `Mockery\Exception\InvalidCountException: Method statement() from ... should be called exactly 1 time(s) but called 2 time(s)`.

### Pitfall 2: `$processedPreprints` reset not tested by single-file tests

**What goes wrong:** `PreprintCommand::run()` resets `$this->processedPreprints = []` inside the dry-mode block after rollback. A single-file test will not catch if this line were removed, because there is no second file to see the stale state.
**Why it happens:** The invariant is a multi-file concern.
**How to avoid:** The multi-file scenario (D-07) must create two CSV files with the same `versionIdentifier`. The second file's processing must start with an empty `$processedPreprints` — verify this by asserting the second file's output does not show a multi-locale or multi-version path for the same identifier.

### Pitfall 3: Overload mock pollution between test methods

**What goes wrong:** Running two `overload:` tests in the same process causes `Mockery\Exception\BadMethodCallException` on the second test because the first overload already registered the class.
**Why it happens:** PHP class autoloading is per-process. Mockery's overload hook runs once per class name per process.
**How to avoid:** Every test method using overloads must have `#[RunInSeparateProcess]` + `#[PreserveGlobalState(false)]`. This is already the established pattern in `PreprintCommandTest` — apply it universally in the new test files.

### Pitfall 4: `DB::swap()` persists across test methods

**What goes wrong:** If a test swaps the DB facade and does not restore it, the next test inherits the mock as the facade root, causing unexpected failures.
**Why it happens:** `DB::swap()` replaces the facade's internal root reference, which survives test method boundaries (it is a class-level static).
**How to avoid:** `#[RunInSeparateProcess]` isolates the facade swap to a single process. Tests that use `DB::swap()` must always use this attribute. Do not use `DB::swap()` in tests without process isolation.

### Pitfall 5: Reporter output assertions vs. i18n key format

**What goes wrong:** Assertions like `assertStringContainsString('Dry Mode Report', $output)` fail because `__()` in test context returns the key string, not the translated value.
**Why it happens:** The OPS i18n helper returns the key when no locale is loaded.
**How to avoid:** Assert on the i18n key string itself, e.g., `assertStringContainsString('plugins.importexport.csv.dryModeFileSummary', $output)`. This is already the pattern used in `PreprintCommandTest` (`assertStringContainsString('plugins.importexpot.csv.fileProcessFinished', $output)`).

---

## Code Examples

### All-Valid Dry-Mode Run — PreprintCommand (exit 0)

```php
#[RunInSeparateProcess]
#[PreserveGlobalState(false)]
public function testDryModeAllValidRowsExitsZeroAndReportsPass(): void
{
    $mocks = $this->setupDryModeMocks();

    // Verify transaction lifecycle (D-05)
    $mocks['dbMock']->shouldReceive('beginTransaction')->once();
    $mocks['dbMock']->shouldReceive('rollBack')->once();

    $headers = CsvTestDataBuilder::getPreprintHeaders();
    $row = CsvTestDataBuilder::minimalPreprintRow();
    $this->createTestCsvFile($this->tempDir, 'preprints.csv', $headers, [$row]);

    $command = new PreprintCommand($this->tempDir, $this->createMockUser(), true);

    ob_start();
    $exitCode = $command->run();
    $output = ob_get_clean();

    $this->assertSame(0, $exitCode);
    $this->assertStringContainsString('plugins.importexport.csv.dryModeFileSummary', $output);
    $this->assertStringContainsString('plugins.importexport.csv.dryModeGrandTotal', $output);
    // CachedEntities reset after rollback (D-03)
    $this->assertEmpty(CachedEntities::$servers);
}
```

### Mixed Valid + Invalid Rows — exit 1

```php
// RowValidationException thrown by overloaded validator for second row
$validationMock->shouldReceive('validateRowContainAllFields')
    ->andReturnNull()
    ->andThrow(new RowValidationException('Missing required field'));

// Exit code reflects failure (D-06)
$this->assertSame(1, $exitCode);
$this->assertStringContainsString('plugins.importexport.csv.dryModeFailedRow', $output);
```

### Multi-File — CachedEntities Reset Between Files

```php
// Create two CSV files
$this->createTestCsvFile($this->tempDir, 'file1.csv', $headers, [$row1]);
$this->createTestCsvFile($this->tempDir, 'file2.csv', $headers, [$row2]);

// Pre-populate cache before run
CachedEntities::$servers['testserver'] = $server;

ob_start();
$command->run();
ob_get_clean();

// After processing both files, cache has been reset (D-03)
$this->assertEmpty(CachedEntities::$servers);
// Report shows two file headers
$this->assertSame(2, substr_count($output, 'plugins.importexport.csv.dryModeFileHeader'));
```

### Welcome Email Guard — UserCommand dry-mode wins

```php
$welcomeMock = Mockery::mock('overload:' . WelcomeEmailHandler::class);
$welcomeMock->shouldNotReceive('sendWelcomeEmail');  // never called in dry-mode

// sendWelcomeEmail=true AND dryMode=true
$command = new UserCommand($this->tempDir, $senderUser, sendWelcomeEmail: true, dryMode: true);
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Guard writes with `if (!$dryMode)` | Wrap entire file in DB transaction + rollback | Phase 02.1 | Processors run real Repo calls inside transaction; rollback prevents persistence |
| Suppress galley/filesystem writes via guard | Guard filesystem only; DB writes are transactional | Phase 02.1 | File-system writes still blocked by `if (!$this->dryMode)` guards; no coverage needed for file-level assertions in tests |

---

## Environment Availability

Step 2.6: SKIPPED — no external dependencies. This phase writes PHP test files that run against the already-installed PHPUnit binary at `../../../../lib/pkp/lib/vendor/bin/phpunit`. All dependencies are already in the OPS vendor directory.

---

## Open Questions

1. **DB::statement mock strictness**
   - What we know: Both commands call `DB::statement('SET FOREIGN_KEY_CHECKS=0')` before `beginTransaction` and `DB::statement('SET FOREIGN_KEY_CHECKS=1')` after `rollBack`. Mockery's `.once()` on `statement` will fail if called twice.
   - What's unclear: Whether tests should assert the exact two-call sequence or just allow them with `byDefault()`.
   - Recommendation: Use `->withAnyArgs()->byDefault()` in `setupDryModeMocks()` for `statement`. Tests asserting the transaction lifecycle only need to assert `beginTransaction` + `rollBack` strictly; the FK guards are implementation details.

2. **`DB::table()` calls inside StatisticsProcessor during dry-mode**
   - What we know: `StatisticsProcessor::insertPreprintViews` runs unconditionally inside the transaction (Phase 02.1 decision). It calls `DB::table('metrics_submission')`. With `makePartial()`, this falls through to the real DB facade root.
   - What's unclear: Whether the test DB (phpunit bootstrap) has the `metrics_submission` table available and whether its absence causes test failures.
   - Recommendation: Overload `StatisticsProcessor` in `setupDryModeMocks()` just as `setupAllMocks()` does — `$statisticsMock->shouldReceive('insertPreprintViews')`. This avoids any real DB table dependency.

---

## Sources

### Primary (HIGH confidence)

- Direct source read: `classes/commands/PreprintCommand.php` — full dry-mode run() logic, transaction wrapping, rollback, reporter calls
- Direct source read: `classes/commands/UserCommand.php` — parallel UserCommand dry-mode logic
- Direct source read: `tests/Unit/Commands/PreprintCommandTest.php` lines 400-543, 1207-1210 — `setupAllMocks()` pattern, `DB::swap()` pattern
- Direct source read: `tests/BaseTestCase.php` — all mock helpers, CachedEntities backup/restore, file helpers
- Direct source read: `tests/Fixtures/CsvTestDataBuilder.php` — `PreprintDataBuilder`, `UserDataBuilder`, `MultiVersionScenarioBuilder`
- Direct source read: `classes/cachedAttributes/CachedEntities.php` — `reset()` method, all static properties
- Direct source read: `classes/handlers/DryModeReporter.php` — i18n key names used in output assertions

### Secondary (MEDIUM confidence)

- Mockery 1.6 documentation: `shouldNotReceive`, `times(0)`, `makePartial()` behavior — consistent with observed usage in codebase

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — no new dependencies; all tools verified in existing tests
- Architecture (test file structure): HIGH — directly derived from existing test files in same directory
- Patterns (DB facade mocking): HIGH — pattern already used in PreprintCommandTest line 1207-1210
- Patterns (CachedEntities reset assertion): HIGH — reset() verified in source; static property visibility confirmed
- Pitfalls: HIGH — derived from reading actual mock setup code and known Mockery constraints

**Research date:** 2026-04-02
**Valid until:** Stable — PHPUnit 11 + Mockery 1.6 are locked by OPS host; patterns are stable
