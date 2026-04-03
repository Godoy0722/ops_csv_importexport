---
status: testing
phase: 01-flag-wiring-and-write-suppression
source: [01-01-SUMMARY.md, 01-02-SUMMARY.md]
started: 2026-04-01T14:30:00Z
updated: 2026-04-01T14:30:00Z
---

## Current Test

number: 1
name: CLI accepts --dry-mode flag
expected: |
  Running the CLI with `--dry-mode` for preprints does not produce an "unknown argument" error or crash.
  Command: `php tools/importExport.php CSVImportExportPlugin preprints {username} {sourceDir} --dry-mode`
  The command should start processing (or report CSV-level errors) — not reject the flag itself.
awaiting: user response

## Tests

### 1. CLI accepts --dry-mode flag
expected: Running the CLI with `--dry-mode` for preprints does not produce an "unknown argument" error or crash. The command should start processing (or report CSV-level errors) — not reject the flag itself.
result: [pending]

### 2. CLI without --dry-mode is unchanged
expected: Running the CLI without `--dry-mode` behaves exactly as before — imports proceed normally, entities are created in the database. No regression in existing behavior.
result: [pending]

### 3. Dry-mode users command runs validation but creates no users
expected: Running `php tools/importExport.php CSVImportExportPlugin users {username} {sourceDir} --dry-mode` with a valid users CSV runs all 7 validation checks (email, username, ORCID, server, user groups, etc.) but creates zero User entities in the database. No welcome emails are sent.
result: [pending]

### 4. Dry-mode preprints command runs validation but creates no submissions
expected: Running with `--dry-mode` and a valid preprints CSV runs all header and row validation (server, section, DOI, file existence, etc.) but creates zero Submission/Publication/Author entities. No files are uploaded to the filesystem. No cover images are written.
result: [pending]

### 5. Invalid rows still written to invalid CSV in dry-mode
expected: When running with `--dry-mode` and a CSV containing invalid rows (e.g., missing required fields, invalid email), the invalid rows are still written to `invalid_{filename}.csv` with their error reasons — same as in normal mode.
result: [pending]

### 6. Dry-mode with --sendWelcomeEmail sends no emails
expected: Running users import with both `--dry-mode` and `--sendWelcomeEmail` flags does NOT send any welcome emails. The `--dry-mode` flag overrides `--sendWelcomeEmail`.
result: [pending]

## Summary

total: 6
passed: 0
issues: 0
pending: 6
skipped: 0
blocked: 0

## Gaps

[none yet]
