# OPS CSV Import Plugin — Dry-Run Mode

## What This Is

A CLI-only plugin for Open Preprint Systems (OPS) 3.5.x that bulk-imports users and preprints from CSV files. This milestone adds a `--dry-mode` flag that runs the full validation pipeline (including read-only DB lookups) without writing to the database, producing per-file console reports and invalid CSV files so operators can preview and fix issues before committing a real import.

## Core Value

Operators must be able to validate an entire CSV import — seeing every pass, failure, and reason — without any database side effects.

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

### Active

- [ ] `--dry-mode` CLI flag accepted by the plugin entry point for both preprints and users commands
- [ ] Dry-mode runs the exact same validation pipeline as a real import (structural + semantic)
- [ ] Dry-mode performs read-only DB access (entity lookups via CachedEntities, Repo reads) but zero writes
- [ ] Per-file console report showing pass/fail status and failure reasons for every row
- [ ] Per-file `invalid_{filename}.csv` generated with failed rows and error reasons (same as normal mode)
- [ ] Non-zero exit code when any rows fail validation (exit 0 = all valid, exit 1 = failures found)
- [ ] Report output to stdout in a user-friendly, readable format

### Out of Scope

- Web UI for dry-mode — plugin is CLI-only by design
- Combined multi-file report — user chose per-file reports
- Extra validation beyond what real import does — same validations, no additional checks
- File output for reports — console stdout only (invalid CSVs are still written)

## Context

- **Brownfield codebase:** 658 tests, 1108 assertions, well-structured with processors, commands, validators
- **Architecture:** Commands (PreprintCommand, UserCommand) orchestrate processor calls; processors are stateless static classes that call OPS `Repo` facade for DB operations
- **Validation flow:** CSV row → trim & combine with headers → validate (structural then semantic) → pass to processors → DB writes. Dry-mode needs to stop before the processor DB write step
- **Error handling:** `RowValidationException` for validation failures (row skipped, written to invalid file), `FileNotSavedException` for file upload failures (submission deleted)
- **Entry point:** `CSVImportExportPlugin.php` parses CLI args and dispatches to commands
- **Current CLI:** `php tools/runScheduledTasks.php plugins.importexport.csv.CSVImportExportPlugin {preprints|users} {username} {sourceDir} [--sendWelcomeEmail]`

## Constraints

- **Tech stack**: PHP 8.2+, OPS 3.5.0 framework — must use existing patterns and `Repo` facade
- **No new dependencies**: No external libraries for reporting; use built-in PHP output
- **Backward compatibility**: Existing import behavior must be completely unaffected when `--dry-mode` is not passed
- **Test coverage**: New code must be covered by tests following existing BaseTestCase/CsvTestDataBuilder patterns

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Same validations as real import | Consistency — dry-mode results must predict real import results | — Pending |
| Read-only DB access in dry-mode | Need real entity lookups (sections, categories) for meaningful validation | — Pending |
| Per-file reports to stdout | Operator reviews one file at a time; console output is simplest for CLI tool | — Pending |
| Generate invalid CSVs in dry-mode | Operators can use the invalid file to fix rows before real import | — Pending |
| Non-zero exit code on failures | Enables scripting and CI integration | — Pending |
| Report format design delegated to implementation | User trusts implementation to choose user-friendly format | — Pending |

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
*Last updated: 2026-03-31 after initialization*
