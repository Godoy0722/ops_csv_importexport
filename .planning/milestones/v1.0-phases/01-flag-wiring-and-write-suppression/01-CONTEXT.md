# Phase 1: Flag Wiring and Write Suppression - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Parse `--dry-mode` from CLI arguments and propagate it through both commands (PreprintCommand, UserCommand) so the full validation pipeline runs without any database writes or other side effects. Existing import behavior is completely unaffected when `--dry-mode` is not passed.

</domain>

<decisions>
## Implementation Decisions

### Flag Propagation
- **D-01:** `--dry-mode` is parsed in `CSVImportExportPlugin::executeCLI()` using the same `array_search` + `unset` + `array_values` pattern as `--sendWelcomeEmail`
- **D-02:** The boolean `$dryMode` is passed as a constructor parameter to both `PreprintCommand` and `UserCommand`, matching the existing pattern for `$sourceDir`, `$user`, `$sendWelcomeEmail`

### Write Suppression Strategy
- **D-03:** Each command's row-processing code path gets a single guard: after validation passes, `if ($this->dryMode) { $this->processedRows++; continue; }` — skipping all processor calls entirely
- **D-04:** PreprintCommand has 3 code paths (new submission, new locale, new version); each path gets its own dry-mode guard after validation, preserving the existing branching logic with minimal restructuring

### Dry-Mode Side Effects
- **D-05:** Invalid CSV files (`invalid_{filename}.csv`) are still written in dry-mode — this happens naturally since the validation flow (catch `RowValidationException` → `CsvFileHandler::processFailedRow()`) runs unchanged
- **D-06:** PreprintCommand post-processing steps (`syncCoverImagesForProcessedPreprints()`, `setCurrentVersionsForProcessedPreprints()`) are explicitly skipped in dry-mode with a guard
- **D-07:** Dry-mode overrides `--sendWelcomeEmail` — if both flags are passed, dry-mode wins and no emails are sent. Dry-mode = zero side effects beyond invalid CSVs

### Claude's Discretion
- Property naming (`$dryMode` vs `$isDryMode`) and exact guard placement within each code path — Claude can determine what fits best after reading the full command code

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Plugin Entry Point
- `CSVImportExportPlugin.php` — CLI argument parsing, command dispatch. The `--sendWelcomeEmail` parsing at lines 103-108 is the pattern to follow for `--dry-mode`

### Commands
- `classes/commands/PreprintCommand.php` — Preprint import orchestrator (737 lines). Constructor at line 102, `run()` at line 115. Contains 3 code paths for new/multi-locale/multi-version routing
- `classes/commands/UserCommand.php` — User import orchestrator (140 lines). Constructor at line 40, `run()` at line 48

### Requirements
- `.planning/REQUIREMENTS.md` — Phase 1 requirements: CLI-01 through CLI-03, VAL-01 through VAL-04
- `.planning/ROADMAP.md` §Phase 1 — Success criteria defining what must be true

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `--sendWelcomeEmail` parsing pattern in `CSVImportExportPlugin::executeCLI()` — exact template for `--dry-mode` parsing
- Constructor parameter injection pattern in both commands — proven approach for flag propagation

### Established Patterns
- CLI flags parsed via `array_search` → `unset` → `array_values` in the entry point
- Commands receive all config through constructor promoted properties
- Validation runs before processor calls in all code paths — clean separation enables simple guard placement
- `RowValidationException` catch blocks handle invalid row writing — this flow is untouched by dry-mode

### Integration Points
- `CSVImportExportPlugin::executeCLI()` — flag parsing and constructor call sites
- `PreprintCommand::__construct()` and `UserCommand::__construct()` — new `$dryMode` parameter
- Each row-processing code path in both commands — guard insertion points after validation

</code_context>

<specifics>
## Specific Ideas

No specific requirements — standard constructor parameter and guard clause patterns apply.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-flag-wiring-and-write-suppression*
*Context gathered: 2026-04-01*
