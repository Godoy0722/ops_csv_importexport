# Phase 3: Test Coverage - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-01
**Phase:** 03-test-coverage
**Areas discussed:** Test scope & granularity, No-write assertion strategy, Test data & scenarios, File organization

---

## Test Scope & Granularity

| Option | Description | Selected |
|--------|-------------|----------|
| Command-level only | Test dry-mode through PreprintCommand and UserCommand run() — exercises transaction wrapping, reporting, and exit codes in one flow. DryModeReporter implicitly covered. | ✓ |
| All layers separately | Separate tests for: entry point flag parsing, DryModeReporter formatting, and command-level integration. More granular but more test code. | |
| You decide | Claude determines the right test granularity based on code complexity | |

**User's choice:** Command-level only
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, assert reset | CachedEntities::reset() after rollback is a core dry-mode invariant (TXVAL-03). Asserting it prevents regressions where stale IDs leak between files. | ✓ |
| No, skip it | Trust that the transaction tests implicitly cover this — if reset fails, multi-file tests would see stale data. | |
| You decide | Claude determines based on how testable it is | |

**User's choice:** Yes, assert reset
**Notes:** None

---

## No-Write Assertion Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Mock expectations | Use Mockery shouldNotReceive/shouldReceive(0) on Repo write methods. Since Phase 2.1 uses transactions, verify DB::rollBack() is called. Fits existing test patterns. | ✓ |
| Real DB state assertions | Use beginDatabaseTransaction() in tests, run dry-mode against real DB, assert row counts unchanged after. More realistic but heavier setup. | |
| Both approaches | Mock expectations for unit tests, plus one integration-style test with real DB if feasible | |

**User's choice:** Mock expectations
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Mock DB facade too | Mock DB::shouldReceive('beginTransaction') and DB::shouldReceive('rollBack') to explicitly verify the transaction lifecycle. | ✓ |
| Repo calls + exit code only | If Repo::add() is called but no persistent data exists (mocked), and exit code is correct, the transaction is implicitly working. | |
| You decide | Claude picks based on what's most maintainable | |

**User's choice:** Mock DB facade too
**Notes:** None

---

## Test Data & Scenarios

| Option | Description | Selected |
|--------|-------------|----------|
| Core paths only | Cover: all-valid, mixed valid+invalid, multi-file with reset. Use CsvTestDataBuilder for minimal test data. | |
| Comprehensive coverage | Core paths PLUS: multi-locale, multi-version, empty files, only-invalid files, --dry-mode + --sendWelcomeEmail interaction. | ✓ |
| You decide | Claude determines based on risk analysis | |

**User's choice:** Comprehensive coverage
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Reuse builders | Use MultiVersionScenarioBuilder and CsvTestDataBuilder fluent API — follows existing patterns. | ✓ |
| Inline minimal data | Hand-craft minimal stdClass objects. Less abstraction but may duplicate builder logic. | |
| You decide | Claude picks based on what produces cleaner tests | |

**User's choice:** Reuse builders
**Notes:** None

---

## File Organization

| Option | Description | Selected |
|--------|-------------|----------|
| Extend existing files | Add dry-mode test sections to PreprintCommandTest.php and UserCommandTest.php. | |
| New dedicated files | Create PreprintCommandDryModeTest.php and UserCommandDryModeTest.php. Keeps dry-mode tests focused and independently runnable. | ✓ |
| You decide | Claude picks based on file size and test count | |

**User's choice:** New dedicated files
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| No separate test | DryModeReporter is simple static formatter. Output verified through command tests. Matches command-level scope. | ✓ |
| Yes, separate test | Create DryModeReporterTest.php for isolated formatting tests. | |
| You decide | Claude decides based on complexity | |

**User's choice:** No separate test
**Notes:** None

---

## Claude's Discretion

- Test method naming and section banner organization
- setUp/tearDown structure for DB facade mocking
- Data provider vs individual test method choices
- Specific Mockery expectation syntax

## Deferred Ideas

None — discussion stayed within phase scope
