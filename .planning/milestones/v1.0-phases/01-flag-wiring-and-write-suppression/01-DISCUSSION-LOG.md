# Phase 1: Flag Wiring and Write Suppression - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-01
**Phase:** 01-flag-wiring-and-write-suppression
**Areas discussed:** Flag propagation, Write suppression, Dry-mode side effects

---

## Flag Propagation

### Q1: How should --dry-mode reach the commands?

| Option | Description | Selected |
|--------|-------------|----------|
| Constructor param | Add bool $dryMode to both command constructors. Matches existing pattern. | ✓ |
| Config/options object | Create an ImportOptions class holding dryMode, sendWelcomeEmail, etc. | |
| You decide | Let Claude choose the simplest approach. | |

**User's choice:** Constructor param (Recommended)
**Notes:** Matches existing pattern where sourceDir, user, and sendWelcomeEmail are already constructor params.

### Q2: Should flag parsing follow the same array_search+unset pattern as --sendWelcomeEmail?

| Option | Description | Selected |
|--------|-------------|----------|
| Same pattern | array_search('--dry-mode', $args), unset, array_values. Identical to --sendWelcomeEmail. | ✓ |
| You decide | Let Claude choose whatever fits best. | |

**User's choice:** Same pattern (Recommended)
**Notes:** None.

---

## Write Suppression

### Q3: How should commands skip DB writes in dry-mode?

| Option | Description | Selected |
|--------|-------------|----------|
| Guard in run() loop | After validation passes, if ($this->dryMode) { continue; } to skip processor block entirely. | ✓ |
| Guard each processor call | Wrap each individual processor::process() call. 15+ guards in PreprintCommand. | |
| You decide | Let Claude pick the cleanest approach. | |

**User's choice:** Guard in run() loop (Recommended)
**Notes:** Single guard per code path, validation still runs fully, processors never called at all.

### Q4: PreprintCommand has 3 code paths (new/multi-locale/multi-version). Guard per path or restructure?

| Option | Description | Selected |
|--------|-------------|----------|
| Guard per path | Add dry-mode guard in each of the 3 code paths after validation. Minimal restructuring. | ✓ |
| Restructure to single guard | Refactor so all 3 paths validate first, then a single guard. Requires restructuring. | |
| You decide | Let Claude determine least invasive approach. | |

**User's choice:** Guard per path (Recommended)
**Notes:** Preserves existing branching logic with minimal restructuring.

---

## Dry-Mode Side Effects

### Q5: Should invalid_{filename}.csv files still be written in dry-mode?

| Option | Description | Selected |
|--------|-------------|----------|
| Already write them | Invalid CSV writing is part of existing validation flow. Happens naturally. | ✓ |
| Defer to Phase 2 | Suppress in Phase 1, add back in Phase 2. | |
| You decide | Let Claude determine based on requirements mapping. | |

**User's choice:** Already write them (Recommended)
**Notes:** No extra work needed — validation flow is unchanged in dry-mode.

### Q6: Should post-processing steps be skipped in dry-mode?

| Option | Description | Selected |
|--------|-------------|----------|
| Skip in dry-mode | Explicitly skip with a guard. Cleaner than letting them run on empty data. | ✓ |
| Let them run on empty data | They iterate empty $processedPreprints — natural no-ops. | |
| You decide | Let Claude choose based on what's cleanest. | |

**User's choice:** Skip in dry-mode (Recommended)
**Notes:** None.

### Q7: Should dry-mode suppress welcome emails even if --sendWelcomeEmail is also passed?

| Option | Description | Selected |
|--------|-------------|----------|
| Explicitly suppress | Dry-mode wins. No emails sent. Zero side effects semantic. | ✓ |
| Let both flags coexist | Honor --sendWelcomeEmail even in dry-mode. | |
| You decide | Let Claude determine safest behavior. | |

**User's choice:** Explicitly suppress (Recommended)
**Notes:** Dry-mode = zero side effects, overrides all other flags.

---

## Claude's Discretion

- Property naming (`$dryMode` vs `$isDryMode`) and exact guard placement within each code path

## Deferred Ideas

None — discussion stayed within phase scope.
