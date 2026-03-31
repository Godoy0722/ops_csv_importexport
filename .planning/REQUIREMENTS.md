# Requirements: OPS CSV Import Plugin — Dry-Run Mode

**Defined:** 2026-03-31
**Core Value:** Operators must be able to validate an entire CSV import — seeing every pass, failure, and reason — without any database side effects.

## v1 Requirements

### CLI Integration

- [ ] **CLI-01**: Operator can pass `--dry-mode` flag to the plugin CLI for both preprints and users commands
- [ ] **CLI-02**: Plugin entry point parses `--dry-mode` and propagates the flag to the appropriate command
- [ ] **CLI-03**: Existing import behavior is completely unaffected when `--dry-mode` is not passed

### Validation

- [ ] **VAL-01**: Dry-mode runs the same structural validation (header checks) as a real import
- [ ] **VAL-02**: Dry-mode runs the same semantic validation (row data checks) as a real import
- [ ] **VAL-03**: Dry-mode performs read-only DB lookups (sections, categories, servers, genres, users) via CachedEntities and Repo reads
- [ ] **VAL-04**: Dry-mode performs zero database writes (no submissions, publications, authors, files, or any other entities created)

### Reporting

- [ ] **RPT-01**: Dry-mode prints a per-file console report to stdout showing pass/fail status for every row
- [ ] **RPT-02**: Each failed row in the report includes the specific validation error reason
- [ ] **RPT-03**: Report is formatted in a user-friendly, readable way suitable for CLI operators
- [ ] **RPT-04**: Per-file `invalid_{filename}.csv` is generated with failed rows and error reasons (same as normal mode)

### Exit Behavior

- [ ] **EXIT-01**: Dry-mode exits with code 0 when all rows across all files pass validation
- [ ] **EXIT-02**: Dry-mode exits with code 1 when any row in any file fails validation

### Testing

- [ ] **TEST-01**: Dry-mode functionality is covered by unit tests following existing BaseTestCase/CsvTestDataBuilder patterns
- [ ] **TEST-02**: Tests verify that no database writes occur during dry-mode execution

## v2 Requirements

### Enhanced Reporting

- **RPT-05**: Report output to file in addition to console
- **RPT-06**: Combined multi-file summary report
- **RPT-07**: Machine-readable report format (JSON/XML) for programmatic consumption

### Extended Validation

- **VAL-05**: Dry-mode detects duplicate rows within the same CSV
- **VAL-06**: Dry-mode warns about potential conflicts with existing database records

## Out of Scope

| Feature | Reason |
|---------|--------|
| Web UI for dry-mode | Plugin is CLI-only by design |
| Extra validations beyond real import | User chose same validations for consistency |
| Combined multi-file report | User chose per-file reports |
| Report to file output | User chose console stdout only |
| Progress bars or interactive output | CLI tool, keep it simple |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CLI-01 | Phase 1 | Pending |
| CLI-02 | Phase 1 | Pending |
| CLI-03 | Phase 1 | Pending |
| VAL-01 | Phase 1 | Pending |
| VAL-02 | Phase 1 | Pending |
| VAL-03 | Phase 1 | Pending |
| VAL-04 | Phase 1 | Pending |
| RPT-01 | Phase 2 | Pending |
| RPT-02 | Phase 2 | Pending |
| RPT-03 | Phase 2 | Pending |
| RPT-04 | Phase 2 | Pending |
| EXIT-01 | Phase 2 | Pending |
| EXIT-02 | Phase 2 | Pending |
| TEST-01 | Phase 3 | Pending |
| TEST-02 | Phase 3 | Pending |

**Coverage:**
- v1 requirements: 15 total
- Mapped to phases: 15
- Unmapped: 0

---
*Requirements defined: 2026-03-31*
*Last updated: 2026-03-31 after roadmap creation (traceability complete)*
