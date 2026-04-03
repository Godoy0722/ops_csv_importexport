# OPS CSV Import Plugin

## What This Is

A CLI-only plugin for Open Preprint Systems (OPS) 3.5.x that bulk-imports users and preprints from CSV files. Includes a `--dry-mode` flag that runs the full validation pipeline without writing to the database, producing per-file console reports and invalid CSV files so operators can preview and fix issues before committing a real import.

## Core Value

Operators must be able to bulk-import CSV data reliably — with the confidence that comes from validating everything first without database side effects.

## Requirements

### Validated

- ✓ Bulk import preprints from CSV with multi-locale and multi-version support — existing
- ✓ Bulk import users from CSV with user group assignment and interest mapping — existing
- ✓ Row-level validation with structural (header) and semantic (data) checks — existing
- ✓ Invalid rows written to `invalid_{filename}.csv` with error reason — existing
- ✓ Static entity caching (servers, genres, sections, categories) to avoid repeated DB queries — existing
- ✓ ORCID validation via HTTP HEAD requests — existing
- ✓ Cover image synchronization across all locales of a publication — existing
- ✓ Welcome email support for user imports via `--sendWelcomeEmail` — existing
- ✓ Statistics/metrics import from CSV — existing
- ✓ Funder integration via OPS Funding plugin — existing
- ✓ `--dry-mode` CLI flag for both preprints and users commands — v1.0
- ✓ Dry-mode runs exact same validation pipeline as real import — v1.0
- ✓ Dry-mode performs read-only DB access with zero writes (transaction rollback) — v1.0
- ✓ Per-file console reports with pass/fail status and error reasons — v1.0
- ✓ Per-file `invalid_{filename}.csv` generated in dry-mode — v1.0
- ✓ Non-zero exit code on validation failures (exit 0 = all valid, exit 1 = failures) — v1.0
- ✓ Dry-mode test coverage (18 tests) verifying no-write guarantee — v1.0

### Active

(None yet — planning next milestone)

### Out of Scope

- Web UI for dry-mode — plugin is CLI-only by design
- Combined multi-file report — user chose per-file reports
- Extra validation beyond what real import does — same validations for consistency
- File output for reports — console stdout only (invalid CSVs are still written)
- Machine-readable report format (JSON/XML) — deferred to v2+

## Context

- **Shipped v1.0 Dry-Run Mode:** 16 files changed, +2244/-589 lines, 48 commits
- **Architecture:** Commands (PreprintCommand, UserCommand) orchestrate processor calls; dry-mode wraps each file in a DB transaction and rolls back
- **Test suite:** 658+ tests including 18 dry-mode tests
- **Tech stack:** PHP 8.2+, OPS 3.5.0 framework, PHPUnit 11.x

## Constraints

- **Tech stack**: PHP 8.2+, OPS 3.5.0 framework — must use existing patterns and `Repo` facade
- **No new dependencies**: No external libraries; use built-in PHP
- **Backward compatibility**: Existing import behavior must be unaffected by new features
- **Test coverage**: New code must be covered by tests following existing BaseTestCase/CsvTestDataBuilder patterns

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Same validations as real import | Consistency — dry-mode results must predict real import results | ✓ Good |
| Read-only DB access in dry-mode | Need real entity lookups (sections, categories) for meaningful validation | ✓ Good |
| Per-file reports to stdout | Operator reviews one file at a time; console output is simplest for CLI tool | ✓ Good |
| Generate invalid CSVs in dry-mode | Operators can use the invalid file to fix rows before real import | ✓ Good |
| Non-zero exit code on failures | Enables scripting and CI integration | ✓ Good |
| Transaction-based dry-mode | Per-file DB transaction wrapping with rollback for zero side effects | ✓ Good |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-03 after v1.0 milestone*
