# Requirements: OPS CSV Import Plugin

**Defined:** 2026-04-03
**Core Value:** Operators must be able to bulk-import CSV data reliably — with the confidence that comes from validating everything first without database side effects.

## v2.0 Requirements

Requirements for Web GUI milestone. Each maps to roadmap phases.

### GUI Integration

- [ ] **GUI-01**: Operator can access the CSV import form as a vertical tab under Settings > Website
- [ ] **GUI-02**: Plugin registers its settings tab via the OPS `Template::Settings::website` hook

### File Upload

- [ ] **UPLOAD-01**: Operator can upload a ZIP file containing CSVs and referenced assets via drag-and-drop or file picker
- [ ] **UPLOAD-02**: Server extracts the uploaded ZIP to a temporary directory with path traversal protection
- [ ] **UPLOAD-03**: Server cleans up the temporary directory after import completes (success or failure)

### Import Configuration

- [ ] **CFG-01**: Operator can select the import type (preprints or users)
- [ ] **CFG-02**: Operator can toggle dry-run mode on or off
- [ ] **CFG-03**: Operator can toggle "send welcome emails" when import type is users
- [ ] **CFG-04**: Welcome email toggle is hidden when import type is preprints
- [ ] **CFG-05**: Import uses the logged-in user as the import actor (no username field)

### Import Execution

- [ ] **EXEC-01**: Operator sees a loading spinner while the import is processing
- [ ] **EXEC-02**: Submit button is disabled during processing to prevent double-submission
- [ ] **EXEC-03**: Import executes the existing PreprintCommand or UserCommand based on selected type

### Results Display

- [ ] **RES-01**: Dry-run results are shown in a side modal with the same information as CLI output (per-file pass/fail, error reasons)
- [ ] **RES-02**: Real import results are shown inline after completion (row count, failure count)
- [ ] **RES-03**: Invalid CSV files are automatically downloaded when failures exist (both dry and real modes)

### Security

- [ ] **SEC-01**: All POST actions are protected with CSRF validation
- [ ] **SEC-02**: ZIP extraction validates entry paths to prevent Zip Slip attacks
- [ ] **SEC-03**: Only authorized users (site admin / journal manager) can access the import form

## Future Requirements

Deferred to future release. Tracked but not in current roadmap.

### Enhanced UX

- **UX-01**: Per-file named invalid CSV download links (instead of auto-download)
- **UX-02**: Import history / audit log in the GUI
- **UX-03**: Import type auto-detection from ZIP contents
- **UX-04**: Multi-server context selector for site-admin installs

## Out of Scope

| Feature | Reason |
|---------|--------|
| Real-time row-by-row progress bar | OPS has no SSE/WebSocket infrastructure; synchronous import with spinner is sufficient |
| Async background import via job queue | Most OPS installs lack persistent queue workers; adds complexity for marginal gain |
| Inline CSV row editing | Disproportionate UI complexity for the import workflow |
| Multiple ZIP upload | Complicates result display and error association; one ZIP per import run |
| Machine-readable report format (JSON/XML) | Deferred from v1.0; still not in scope |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| GUI-01 | Phase 6 | Pending |
| GUI-02 | Phase 6 | Pending |
| UPLOAD-01 | Phase 7 | Pending |
| UPLOAD-02 | Phase 4 | Pending |
| UPLOAD-03 | Phase 5 | Pending |
| CFG-01 | Phase 6 | Pending |
| CFG-02 | Phase 6 | Pending |
| CFG-03 | Phase 6 | Pending |
| CFG-04 | Phase 6 | Pending |
| CFG-05 | Phase 6 | Pending |
| EXEC-01 | Phase 7 | Pending |
| EXEC-02 | Phase 7 | Pending |
| EXEC-03 | Phase 5 | Pending |
| RES-01 | Phase 7 | Pending |
| RES-02 | Phase 7 | Pending |
| RES-03 | Phase 7 | Pending |
| SEC-01 | Phase 6 | Pending |
| SEC-02 | Phase 4 | Pending |
| SEC-03 | Phase 6 | Pending |

**Coverage:**
- v2.0 requirements: 19 total
- Mapped to phases: 19
- Unmapped: 0

---
*Requirements defined: 2026-04-03*
*Last updated: 2026-04-03 — traceability mapped to Phases 4-7*
