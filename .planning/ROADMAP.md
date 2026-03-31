# Roadmap: OPS CSV Import Plugin — Dry-Run Mode

## Overview

This milestone adds `--dry-mode` to the existing CLI plugin. Three phases deliver the feature: first, wire the flag through the entry point and teach both commands to suppress all DB writes when it is active; second, build the console report and enforce the exit-code contract so operators get actionable output; third, cover every new code path with unit tests that also assert the no-write guarantee.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Flag Wiring and Write Suppression** - Parse --dry-mode and make both commands skip all DB writes while running the full validation pipeline
- [ ] **Phase 2: Reporting and Exit Codes** - Emit per-file console reports and return the correct exit code based on validation results
- [ ] **Phase 3: Test Coverage** - Unit tests verify dry-mode behavior and assert zero DB writes under the flag

## Phase Details

### Phase 1: Flag Wiring and Write Suppression
**Goal**: Operators can pass `--dry-mode` to both preprints and users commands and the full validation pipeline runs without any database side effects
**Depends on**: Nothing (first phase)
**Requirements**: CLI-01, CLI-02, CLI-03, VAL-01, VAL-02, VAL-03, VAL-04
**Success Criteria** (what must be TRUE):
  1. Running the CLI with `--dry-mode` does not cause any error or change to existing behavior when omitted
  2. Passing `--dry-mode` with a preprints CSV runs header validation and every semantic row check identically to a real import
  3. Passing `--dry-mode` with a users CSV runs header validation and every semantic row check identically to a real import
  4. No Submission, Publication, Author, User, File, or any other entity exists in the database after a dry-mode run
  5. Read-only DB lookups (sections, categories, servers, genres, users via CachedEntities) succeed and inform validation results
**Plans**: TBD

### Phase 2: Reporting and Exit Codes
**Goal**: Operators receive a per-file console report showing every row's pass/fail status with error reasons, and can use the exit code to drive scripting
**Depends on**: Phase 1
**Requirements**: RPT-01, RPT-02, RPT-03, RPT-04, EXIT-01, EXIT-02
**Success Criteria** (what must be TRUE):
  1. Running dry-mode prints a report to stdout that lists every row and its pass or fail status
  2. Each failed row in the report shows the specific validation error reason
  3. The report is readable at a glance by a CLI operator (clear structure, no raw stack traces)
  4. An `invalid_{filename}.csv` file is produced containing only the failed rows with their error reasons
  5. The process exits with code 0 when all rows pass and code 1 when any row fails
**Plans**: TBD

### Phase 3: Test Coverage
**Goal**: Every dry-mode code path is covered by unit tests that follow existing patterns and the no-write guarantee is machine-verified
**Depends on**: Phase 2
**Requirements**: TEST-01, TEST-02
**Success Criteria** (what must be TRUE):
  1. PHPUnit runs the full test suite (658 + new tests) without failures
  2. Tests for dry-mode use BaseTestCase and CsvTestDataBuilder following existing conventions
  3. At least one test asserts that Repo write methods (add, edit, delete) are never called during a dry-mode run
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Flag Wiring and Write Suppression | 0/TBD | Not started | - |
| 2. Reporting and Exit Codes | 0/TBD | Not started | - |
| 3. Test Coverage | 0/TBD | Not started | - |
