# Roadmap: OPS CSV Import Plugin

## Milestones

- **v1.0 Dry-Run Mode** — Phases 1-3 (shipped 2026-04-03)
- **v2.0 Web GUI** — Phases 4-7 (in progress)

## Phases

<details>
<summary>v1.0 Dry-Run Mode (Phases 1-3) — SHIPPED 2026-04-03</summary>

- [x] **Phase 1: Flag Wiring and Write Suppression** — completed
- [x] **Phase 2: Reporting and Exit Codes** — completed
- [x] **Phase 2.1: Transaction-Based Dry-Mode Validation (INSERTED)** — completed
- [x] **Phase 3: Test Coverage** — completed

</details>

### v2.0 Web GUI (In Progress)

**Milestone Goal:** Add a web-based GUI so operators without server access can import preprints and users through the OPS admin interface.

- [ ] **Phase 4: ZIP Extraction Infrastructure** - Build ZipExtractor and ImportResultStore as isolated, testable PHP classes with full path traversal and bomb protection
- [ ] **Phase 5: Import Job Wiring** - Wrap existing commands in a BaseJob with output buffering, CachedEntities reset, and import locking
- [ ] **Phase 6: HTTP Op Router and Settings Tab** - Wire all web ops through display() and register the settings tab with role-based authorization and CSRF protection
- [ ] **Phase 7: Frontend (Template and Vue Component)** - Deliver the complete operator-facing UI: file upload, form options, loading state, results modal, and invalid CSV download

## Phase Details

### Phase 4: ZIP Extraction Infrastructure
**Goal**: Server can safely receive, extract, and store a ZIP file upload with full protection against path traversal and decompression bomb attacks
**Depends on**: Nothing (first v2.0 phase; existing CLI path untouched)
**Requirements**: UPLOAD-02, SEC-02
**Success Criteria** (what must be TRUE):
  1. A ZIP file containing CSVs and referenced assets can be extracted to a scoped temporary directory
  2. A ZIP entry with a path traversal sequence (e.g., `../../etc/passwd`) causes the entire upload to be rejected before any file is written
  3. A ZIP whose uncompressed total exceeds the configured limit is rejected before extraction begins
  4. The extraction result store (ImportResultStore) can persist, retrieve, and delete a JSON result blob keyed by UUID
**Plans**: TBD

### Phase 5: Import Job Wiring
**Goal**: A web-triggered import runs the existing PreprintCommand or UserCommand in full isolation — captured output, reset static cache, no echo leakage to the HTTP response, and no orphaned temp directories
**Depends on**: Phase 4
**Requirements**: EXEC-03, UPLOAD-03, SEC-01
**Success Criteria** (what must be TRUE):
  1. A dispatched import job runs the correct command (preprints or users) based on the selected import type
  2. All echo output from commands, processors, and reporters is captured and stored — none reaches the HTTP response body
  3. CachedEntities static state is reset at the start of every web-triggered import, preventing cross-request cache pollution
  4. The temporary directory is removed after import completes, and also on PHP fatal errors via shutdown function
  5. Concurrent import attempts from the same server context are serialized via an import lock
**Plans**: TBD

### Phase 6: HTTP Op Router and Settings Tab
**Goal**: Operators can reach the import form through the OPS admin interface, and all web operations are secured with CSRF validation and role-based access control
**Depends on**: Phase 5
**Requirements**: GUI-01, GUI-02, CFG-01, CFG-02, CFG-03, CFG-04, CFG-05, SEC-01, SEC-03
**Success Criteria** (what must be TRUE):
  1. A logged-in site admin or server manager sees a CSV Import tab under Settings > Website
  2. A user without manager or site-admin role receives an authorization error when attempting to access the import page
  3. A POST request without a valid CSRF token to any state-mutating op (upload, import, download) is rejected
  4. The import executes using the logged-in user as the import actor — no username field is required
  5. The invalid CSV download endpoint only serves files that belong to the requesting user's import session
**Plans**: TBD
**UI hint**: yes

### Phase 7: Frontend (Template and Vue Component)
**Goal**: Operators can complete the full import workflow entirely through the browser: upload a ZIP, configure options, trigger import, see results, and download invalid CSVs
**Depends on**: Phase 6
**Requirements**: UPLOAD-01, EXEC-01, EXEC-02, RES-01, RES-02, RES-03
**Success Criteria** (what must be TRUE):
  1. Operator can upload a ZIP file via drag-and-drop or a file picker on the import settings tab
  2. Submit button is disabled and a loading spinner is shown while the import is processing; the operator cannot trigger a second submission
  3. Dry-run results appear in a side modal showing per-file pass/fail status and error reasons matching the CLI output format
  4. Real import results appear inline after completion showing row count and failure count
  5. When failures exist in either dry-run or real mode, invalid CSV files are automatically downloaded to the operator's browser
**Plans**: TBD
**UI hint**: yes

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Flag Wiring and Write Suppression | v1.0 | direct | Complete | 2026-04-02 |
| 2. Reporting and Exit Codes | v1.0 | direct | Complete | 2026-04-02 |
| 2.1. Transaction-Based Validation | v1.0 | direct | Complete | 2026-04-02 |
| 3. Test Coverage | v1.0 | 2/2 | Complete | 2026-04-02 |
| 4. ZIP Extraction Infrastructure | v2.0 | 0/? | Not started | - |
| 5. Import Job Wiring | v2.0 | 0/? | Not started | - |
| 6. HTTP Op Router and Settings Tab | v2.0 | 0/? | Not started | - |
| 7. Frontend (Template and Vue Component) | v2.0 | 0/? | Not started | - |
