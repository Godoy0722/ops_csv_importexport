# Milestones

## v1.0 Dry-Run Mode (Shipped: 2026-04-03)

**Phases completed:** 4 phases (1, 2, 2.1, 3), 48 commits
**Files changed:** 16 files, +2244 / -589 lines
**Timeline:** 2026-01-20 to 2026-04-02

**Key accomplishments:**

1. Wired `--dry-mode` CLI flag through plugin entry point to both PreprintCommand and UserCommand
2. Implemented write-suppression guards across all processor calls in both commands
3. Built DryModeReporter with per-file console reports showing pass/fail status per row
4. Enforced exit code contract (0 = all valid, 1 = failures found) through plugin entry point
5. Restructured dry-mode to use per-file DB transaction wrapping with rollback for zero side effects
6. Added 18 dry-mode tests (8 user, 10 preprint) covering all scenarios including multi-locale/version

**Archive:** `.planning/milestones/v1.0-ROADMAP.md`, `.planning/milestones/v1.0-REQUIREMENTS.md`

---
