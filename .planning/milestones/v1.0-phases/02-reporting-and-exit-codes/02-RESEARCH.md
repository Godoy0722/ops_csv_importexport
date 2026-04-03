# Phase 2: Reporting and Exit Codes - Research

**Researched:** 2026-04-01
**Domain:** PHP CLI output formatting, exit code propagation, command return types
**Confidence:** HIGH

## Summary

Phase 2 adds dry-mode console reporting and exit code signalling to the two command classes and the entry point. All required data already exists in the codebase: `$processedRows` and `$failedRows` are tracked per file, `RowValidationException::getMessage()` provides error reasons, and the catch block is the single point where failed rows are handled.

The changes are confined to three files: `PreprintCommand.php`, `UserCommand.php`, and `CSVImportExportPlugin.php`. The core decisions are: `run()` returns `int` instead of `void`, report data is accumulated during processing (not printed inline), and a separate `DryModeReporter` class (or equivalent inline logic) emits the formatted output only when `$this->dryMode` is true.

**Primary recommendation:** Accumulate per-file row results into an array during the catch block, then emit the table and summary at the end of each file's loop. Accumulate grand totals across files. Return 1 if `$totalFailed > 0`, 0 otherwise. Extract a `DryModeReporter` static class only if the formatting logic would otherwise be duplicated across both commands.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Only failed rows are printed to the console — passed rows are silent. Each failed row shows its row number and the specific validation error reason.
- **D-02:** Each file ends with a summary line: `Result: N passed, M failed (T total)`
- **D-03:** After all files, a grand total line is printed: `--- Dry-mode complete: F files, N passed, M failed ---`
- **D-04:** Tabular column layout with aligned columns: `ROW | STATUS | ERROR`
- **D-05:** Each file starts with a header: `=== filename.csv ===`
- **D-06:** If a file has zero failures, only the summary line is printed (no table header needed)
- **D-07:** `run()` method signature changes from `void` to `int` in both `PreprintCommand` and `UserCommand`
- **D-08:** In dry-mode, commands return 1 if any rows failed across all files, 0 if all passed
- **D-09:** In normal mode, commands always return 0 — preserving exact backward compatibility
- **D-10:** Entry point captures the return value and calls `exit($exitCode)` after the timing output
- **D-11:** The new detailed report (tabular failed rows + per-file summary + grand total) only appears in dry-mode
- **D-12:** Normal mode keeps its existing `fileProcessFinished` echo completely unchanged
- **D-13:** Invalid CSV file generation (`invalid_{filename}.csv`) continues in both modes unchanged (already decided in Phase 1, D-05)

### Claude's Discretion

- Report class/helper extraction vs inline in commands — Claude decides based on complexity and duplication
- Exact column width/alignment strategy for the tabular output
- Whether to accumulate report data during processing or print inline as rows are processed
- i18n key naming for new report strings

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| RPT-01 | Dry-mode prints a per-file console report to stdout showing pass/fail status for every row | `$processedRows` and `$failedRows` tracked per file; catch block is the single accumulation point |
| RPT-02 | Each failed row in the report includes the specific validation error reason | `RowValidationException::getMessage()` and `FileNotSavedException::getMessage()` already passed to `CsvFileHandler::processFailedRow()` — same value feeds the report |
| RPT-03 | Report is formatted in a user-friendly, readable way suitable for CLI operators | D-04/D-05/D-06 define the exact format; `str_pad()` / `printf()` patterns cover column alignment in plain PHP |
| RPT-04 | Per-file `invalid_{filename}.csv` is generated with failed rows and error reasons | Already implemented in Phase 1; no new work required |
| EXIT-01 | Dry-mode exits with code 0 when all rows across all files pass validation | `run()` returns `int`; entry point calls `exit($exitCode)` after timing echo |
| EXIT-02 | Dry-mode exits with code 1 when any row in any file fails validation | Grand total `$totalFailed` counter drives return value |
</phase_requirements>

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP built-in `echo` / `printf` | 8.2+ | Console output | Only mechanism available; no external deps allowed |
| PHP `str_pad()` | 8.2+ | Column alignment in tabular output | Native, zero-dependency string padding |
| PHP process exit | 8.2+ | `exit(int)` propagation | Standard CLI exit code mechanism |

No external libraries are involved. The constraint "no new dependencies" is fully satisfiable with built-in PHP string functions.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `str_pad()` alignment | Symfony Console Table | Symfony is available via OPS but would be overkill and contradicts "no new dependencies" spirit |
| Inline echo in commands | Dedicated `DryModeReporter` class | Reporter class adds a file but removes duplication across two commands |

---

## Architecture Patterns

### Recommended Project Structure

No new directories required. If a reporter class is extracted:

```
classes/
├── commands/
│   ├── PreprintCommand.php   ← run() now returns int, accumulates report data
│   └── UserCommand.php       ← same changes
├── handlers/
│   ├── CsvFileHandler.php    ← unchanged
│   └── DryModeReporter.php   ← optional, if duplication justifies extraction
CSVImportExportPlugin.php     ← captures run() return value, calls exit()
```

### Pattern 1: Accumulate then Emit (recommended over inline printing)

**What:** Collect failed-row data into an array during the catch block, then emit the full table and summary after the file's inner loop completes.

**When to use:** Whenever output needs to be structured (header + rows + summary) — printing inline would interleave with any other echo statements inside the loop.

**Example structure inside the per-file loop:**

```php
// At top of each file processing
$fileFailedRows = [];   // accumulate: [['row' => int, 'reason' => string], ...]

// Inside catch block (after existing CsvFileHandler::processFailedRow call)
if ($this->dryMode) {
    $fileFailedRows[] = ['row' => $this->processedRows, 'reason' => $e->getMessage()];
}

// After the inner foreach loop, before moving to next file
if ($this->dryMode) {
    DryModeReporter::printFileReport($basename, $fileFailedRows, $this->processedRows, $this->failedRows);
    $totalFailed += $this->failedRows;
    $totalPassed += ($this->processedRows - $this->failedRows);
    $totalFiles++;
}
```

**Why accumulate instead of inline print:**
- D-05 requires a file header before any rows; header cannot be printed before we know if failures exist
- D-06 requires suppressing the table header entirely when zero failures — impossible with inline printing
- Accumulation also makes unit testing trivial: pass an array, assert output

### Pattern 2: Row Number Tracking

The commands use `++$this->processedRows` before the try block. At the catch point, `$this->processedRows` holds the 1-based row number of the failed row (header row is index 0, skipped; first data row increments to 1). This is the correct row number to display.

**Verified by reading PreprintCommand.php line 147 and UserCommand.php line 79:**

```php
++$this->processedRows;  // incremented before try/catch
// ...
} catch (RowValidationException | FileNotSavedException $e) {
    // $this->processedRows is already the current row number here
    CsvFileHandler::processFailedRow(..., $this->failedRows);
}
```

### Pattern 3: Entry Point Exit Code Propagation

**Current code (CSVImportExportPlugin.php lines 134-138):**

```php
match ($this->command) {
    'preprints' => (new PreprintCommand($this->sourceDir, $this->user, $dryMode))->run(),
    'users' => (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail, $dryMode))->run(),
    default => throw new \InvalidArgumentException(...)
};

$endTime = microtime(true);
// ...
echo __('plugins.importexport.csv.ExecutedInNSeconds', ...);
```

**After change — D-10 requires `exit()` after timing output:**

```php
$exitCode = match ($this->command) {
    'preprints' => (new PreprintCommand($this->sourceDir, $this->user, $dryMode))->run(),
    'users' => (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail, $dryMode))->run(),
    default => throw new \InvalidArgumentException(...)
};

$endTime = microtime(true);
// ...
echo __('plugins.importexport.csv.ExecutedInNSeconds', ...);
exit($exitCode);
```

The existing `exit(1)` calls for bad arguments and unknown user/dir remain unchanged.

### Pattern 4: Tabular Column Alignment

The exact format from 02-CONTEXT.md specifics:

```
=== filename.csv ===
ROW  | STATUS | ERROR
  3  | FAILED | Invalid ORCID format
  7  | FAILED | Section 'Biology' not found
Result: 47 passed, 3 failed (50 total)
```

Column width strategy using `str_pad()`:

```php
// Row number column: right-align in 4 chars
$rowCol = str_pad((string)$row, 4, ' ', STR_PAD_LEFT);
// Status column: fixed "FAILED" (6 chars)
$statusCol = 'FAILED';
// Error column: raw reason string (no truncation needed for CLI)
echo "{$rowCol} | {$statusCol} | {$reason}\n";
```

Header line:
```php
echo "ROW  | STATUS | ERROR\n";
```

File header:
```php
echo "=== {$basename} ===\n";
```

Summary:
```php
$passed = $processedRows - $failedRows;
echo "Result: {$passed} passed, {$failedRows} failed ({$processedRows} total)\n";
```

Grand total:
```php
echo "--- Dry-mode complete: {$totalFiles} files, {$totalPassed} passed, {$totalFailed} failed ---\n";
```

### Anti-Patterns to Avoid

- **Printing the table header unconditionally (D-06):** The `ROW | STATUS | ERROR` header must only appear when at least one failure exists in the file. Guard it with `if (!empty($fileFailedRows))`.
- **Mixing dry-mode output with normal-mode output:** D-12 requires normal-mode output to be completely unchanged. All new echo statements must be inside `if ($this->dryMode)` guards.
- **Calling `exit()` inside `run()`:** Exit code must be returned to the entry point, not terminated inside the command. The entry point owns `exit()` (D-10).
- **Adding a separate `exit()` in the match expression:** The match in `executeCLI()` must capture the return value first, then call `exit()` after the timing echo — not inside or alongside the match.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Column width for row numbers | Custom padding logic | `str_pad()` | Native PHP, one line |
| Formatted number output | sprintf patterns | Direct string interpolation | Simple integers, no float formatting needed |

**Key insight:** This is simple string concatenation and echo. There is no formatting complexity that warrants a library.

---

## Decision: Inline vs Extracted Reporter Class

**Duplication analysis:** Both `PreprintCommand` and `UserCommand` need identical formatting logic:
- File header print
- Table header print (conditional)
- Per-row line print  
- File summary print
- Grand total print (after outer loop)

That is five formatting operations, duplicated across two commands. Extracting a `DryModeReporter` static class (consistent with the codebase's stateless-static pattern) eliminates duplication and centralises the i18n keys. This aligns with the project's `AuthorsProcessor`, `CsvFileHandler`, etc. pattern.

**Recommendation:** Extract `classes/handlers/DryModeReporter.php` as a stateless static class. This is within Claude's discretion (CONTEXT.md) and the complexity justifies it.

The reporter would expose:

```php
class DryModeReporter
{
    public static function printFileHeader(string $basename): void
    public static function printTableHeader(): void
    public static function printFailedRow(int $rowNumber, string $reason): void
    public static function printFileSummary(int $passed, int $failed, int $total): void
    public static function printGrandTotal(int $files, int $passed, int $failed): void
}
```

---

## Common Pitfalls

### Pitfall 1: Row Number Off-By-One

**What goes wrong:** Report shows wrong row numbers (e.g., 2 instead of 3).
**Why it happens:** `$processedRows` is incremented before the try block (correct). If a developer increments after the try block or misreads the CSV index (which includes the header at index 0), the row number is wrong.
**How to avoid:** Use `$this->processedRows` at the catch site — it is already correct because `++$this->processedRows` runs before the try block.
**Warning signs:** Test with a CSV where row 1 passes and row 2 fails; verify report shows "2".

### Pitfall 2: Grand Total Counting Both Files

**What goes wrong:** Grand total only counts the last file, not all files.
**Why it happens:** `$this->processedRows` and `$this->failedRows` are reset to 0 at the start of each file (lines 139-140 in PreprintCommand, lines 71-72 in UserCommand). If you read these after the outer loop ends, you only get the last file's numbers.
**How to avoid:** Accumulate into separate `$totalPassed`, `$totalFailed`, `$totalFiles` variables during the outer loop (after each file finishes), not by reading instance properties after the loop.
**Warning signs:** Test with two CSV files where file 1 has failures and file 2 has none — grand total must include file 1's failures.

### Pitfall 3: Table Header Printed When No Failures (D-06 Violation)

**What goes wrong:** `ROW | STATUS | ERROR` header appears even for clean files.
**Why it happens:** Table header printed at start of file processing before failures are known.
**How to avoid:** Print table header only if `!empty($fileFailedRows)` — done after accumulation, before the table rows loop.
**Warning signs:** Test with a file that has zero failures; output must contain only `=== filename.csv ===` and `Result: N passed, 0 failed (N total)`.

### Pitfall 4: Normal Mode Broken (D-12 Violation)

**What goes wrong:** Normal mode now prints the dry-mode report or suppresses its existing `fileProcessFinished` message.
**Why it happens:** Forgetting to guard new echo statements with `if ($this->dryMode)`, or placing the guard where it also blocks normal-mode output.
**How to avoid:** The existing `fileProcessFinished` echo must remain outside and independent of any `$this->dryMode` block. New report code must be inside `if ($this->dryMode)`.
**Warning signs:** Run without `--dry-mode` — output must be identical to pre-Phase-2 output.

### Pitfall 5: `exit()` Inside `run()` Breaks Normal Mode

**What goes wrong:** Normal mode now exits with code 1 on any import row failure instead of continuing.
**Why it happens:** Placing `exit()` inside the command instead of returning an int to the entry point.
**How to avoid:** Commands return int, entry point calls `exit()`. The command must not call `exit()` directly.
**Warning signs:** In normal mode, import a CSV with one invalid row — process should complete (not exit early) and write the invalid CSV file.

### Pitfall 6: `$dryMode` Not Propagated to Grand Total Scope

**What goes wrong:** Grand total is always printed, even in normal mode.
**Why it happens:** Grand total accumulation variables are updated unconditionally; grand total print not guarded.
**How to avoid:** Either guard the entire accumulation block with `if ($this->dryMode)` or only print the grand total inside `if ($this->dryMode)` after the outer loop.

---

## Code Examples

Verified patterns from reading the actual source files:

### Current `run()` Return Type (to be changed)

```php
// PreprintCommand.php line 115 / UserCommand.php line 49
public function run(): void
```

Change to:

```php
public function run(): int
```

### Current Entry Point Match (to be changed)

```php
// CSVImportExportPlugin.php lines 134-138
match ($this->command) {
    'preprints' => (new PreprintCommand($this->sourceDir, $this->user, $dryMode))->run(),
    'users' => (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail, $dryMode))->run(),
    default => throw new \InvalidArgumentException(...)
};
```

Change to:

```php
$exitCode = match ($this->command) {
    'preprints' => (new PreprintCommand($this->sourceDir, $this->user, $dryMode))->run(),
    'users' => (new UserCommand($this->sourceDir, $this->user, $this->sendWelcomeEmail, $dryMode))->run(),
    default => throw new \InvalidArgumentException(...)
};

$endTime = microtime(true);
$executionTime = $endTime - $startTime;
echo __('plugins.importexport.csv.ExecutedInNSeconds', ['seconds' => number_format($executionTime, 2)]);
exit($exitCode);
```

### Current Catch Block (integration point for report accumulation)

```php
// PreprintCommand.php lines 476-485
} catch (RowValidationException | FileNotSavedException $e) {
    if (is_null($invalidCsvFile)) {
        $invalidCsvFile = CsvFileHandler::createCSVFileInvalidRows(...);
        if (is_null($invalidCsvFile)) {
            continue 2;
        }
    }
    CsvFileHandler::processFailedRow($invalidCsvFile, $fields, $this->expectedRowSize, $e->getMessage(), $this->failedRows);
    continue;
}
```

The report accumulation line inserts after `CsvFileHandler::processFailedRow(...)`:

```php
if ($this->dryMode) {
    $fileFailedRows[] = ['row' => $this->processedRows, 'reason' => $e->getMessage()];
}
```

### Current fileProcessFinished Echo (must remain unchanged for normal mode)

```php
// PreprintCommand.php line 488 / UserCommand.php line 138
echo __('plugins.importexpot.csv.fileProcessFinished', [   // note: typo "importexpot" is in the codebase
    'filename' => $fileInfo->getFilename(),
    'processedRows' => $this->processedRows,
    'failedRows' => $this->failedRows,
]) . "\n";
```

This must remain unconditional (no dry-mode guard). It runs in both modes. The dry-mode report output is separate and additionally printed when `$this->dryMode`.

### i18n Key Naming Convention

Existing keys follow `plugins.importexport.csv.{camelCaseTopic}`. New keys for Phase 2:

```
plugins.importexport.csv.dryModeFileHeader        → "=== {$filename} ==="
plugins.importexport.csv.dryModeTableHeader       → "ROW  | STATUS | ERROR"
plugins.importexport.csv.dryModeFailedRow         → "  {$row}  | FAILED | {$reason}"
plugins.importexport.csv.dryModeFileSummary       → "Result: {$passed} passed, {$failed} failed ({$total} total)"
plugins.importexport.csv.dryModeGrandTotal        → "--- Dry-mode complete: {$files} files, {$passed} passed, {$failed} failed ---"
```

Note: The existing `fileProcessFinished` key has a typo — `importexpot` instead of `importexport` — in both the code and the locale file. Do not fix it in this phase; fixing it would be a separate unrelated change.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `run(): void` | `run(): int` (to be added) | Phase 2 | Entry point can now propagate exit code |
| No console report in dry-mode | Tabular per-file report + grand total | Phase 2 | Operators see full validation results |

---

## Environment Availability

Step 2.6: SKIPPED (no external dependencies — this is a pure PHP code/output change with no new tools, services, or CLIs required).

---

## Open Questions

1. **Should normal mode also call `exit($exitCode)` or just `return`?**
   - What we know: D-09 says normal mode always returns 0. D-10 says entry point calls `exit($exitCode)` after timing output.
   - What's unclear: Whether `exit(0)` in normal mode has any negative interaction with OPS's CLI runner or test harness.
   - Recommendation: Always call `exit($exitCode)` after timing output — it is already the pattern (the existing bad-argument paths call `exit(1)`). The value will always be 0 for normal mode, so no behavior change.

2. **`fileFailedRows` array placement — instance property vs local variable?**
   - What we know: The array is reset per file. It does not need to survive across files.
   - What's unclear: Whether making it an instance property would complicate tests.
   - Recommendation: Local variable scoped to the outer foreach body (per-file). Reset implicitly each iteration.

---

## Sources

### Primary (HIGH confidence)

- Direct reading of `classes/commands/PreprintCommand.php` — full source, current state
- Direct reading of `classes/commands/UserCommand.php` — full source, current state
- Direct reading of `CSVImportExportPlugin.php` — full source, entry point dispatch and exit pattern
- Direct reading of `classes/handlers/CsvFileHandler.php` — processFailedRow signature and contract
- Direct reading of `locale/en/locale.po` — all existing i18n keys and naming convention
- Direct reading of `.planning/phases/02-reporting-and-exit-codes/02-CONTEXT.md` — locked decisions
- Direct reading of `.planning/REQUIREMENTS.md` — phase requirements RPT-01 through EXIT-02
- Direct reading of `tests/BaseTestCase.php` and `tests/Unit/Commands/UserCommandTest.php` — test patterns

### Secondary (MEDIUM confidence)

- PHP 8.2 `str_pad()` documentation — standard function, no version uncertainty
- PHP `exit()` / process return code semantics — well-established

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — pure PHP built-ins, no ecosystem ambiguity
- Architecture: HIGH — all integration points verified by reading actual source; no guesswork
- Pitfalls: HIGH — derived from concrete code analysis (row counter placement, reset pattern, typo in key name)

**Research date:** 2026-04-01
**Valid until:** Stable — this is internal code analysis, not ecosystem research. Valid until source files change.
