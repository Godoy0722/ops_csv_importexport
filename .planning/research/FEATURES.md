# Feature Research

**Domain:** Bulk-import GUI for an OPS 3.5.x importexport plugin
**Researched:** 2026-04-03
**Confidence:** HIGH (all findings verified against live OPS 3.5.0 codebase)

---

## GUI Integration Approach — Decision Context

OPS 3.5.x offers two distinct integration points for an importexport plugin GUI:

**Route A — Settings tab (Shariff-style)**
Hook into `Template::Settings::website` (or a sub-hook like `::appearance`, `::setup`) to inject a `<tab>` element into the Settings > Website page. The form is a Vue `FormComponent` subclass rendered by `<pkp-form>`. This approach stores state in the context schema via `Schema::get::context` and submits via the Context REST API (`PUT /api/v1/contexts/{id}`). It is well-suited for persistent configuration that belongs to the site's context record.

**Route B — Tools > Import/Export page**
The `management/importexport` handler calls `ImportExportPlugin::display($args, $request)` for the active plugin, which has full control over the rendered page. This is the canonical path for all importexport plugins: `management/importexport/plugin/CSVImportExportPlugin/...`. The base `getActions()` method in `ImportExportPlugin.php` already generates this link. This approach supports arbitrary op routing (upload, process, download), is not coupled to the context schema, and can return JSON for Ajax interactions. It is the correct home for a stateless, action-oriented workflow like "upload and run an import."

**Verdict:** The PROJECT.md specifies a "Shariff-style vertical tab under Settings > Website." That is Route A. However, the import action itself (upload ZIP, trigger import, return results) is inherently a Tools-style operation. The practical implementation is: inject the import form tab via the website settings hook, but handle all async actions (upload, process, download) through the `display()` op router — returning JSON responses. This hybrid is consistent with how complex OPS features work (e.g., Shariff redirects its settings link to the website tab via `getActions()`).

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features that any import GUI for an admin tool must have. Absent = product feels broken or untrustworthy.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Import type selector (preprints / users) | Core data separation; importing the wrong type causes bad data | LOW | `<select>` or radio; wires to PreprintCommand or UserCommand |
| ZIP file upload with drag-and-drop | ZIP bundles CSVs + referenced files (galleys, images); any file-import tool expects this | MEDIUM | OPS provides `FieldUpload` (Vue) backed by Dropzone.js via `POST /api/v1/temporaryFiles`. Accept `.zip` only. |
| Dry-run mode toggle | Feature parity with CLI `--dry-mode`; operators must preview before committing | LOW | Checkbox/toggle; wires to existing `$dryMode` flag |
| Submit button that triggers import | Obvious action | LOW | Must disable during processing to prevent double-submission |
| Results display after import | Operators need to know what happened (rows imported, failures) | MEDIUM | Dry-run: modal with report text + auto-download of invalid CSVs. Real import: inline result section. |
| Invalid CSV auto-download | Operators need the invalid rows file to fix and re-import | MEDIUM | Triggered automatically on completion when failures exist; uses `FileManager::downloadByPath()` or a download endpoint |
| Welcome email toggle (users import) | CLI parity with `--sendWelcomeEmail`; visible/hidden based on import type | LOW | Show only when import type = users |
| CSRF protection | Standard for all OPS form submissions | LOW | `{csrf}` in template or `$request->checkCSRF()` in handler |
| Loading/progress indicator | Long imports can take seconds to minutes; no feedback = operator thinks it crashed | LOW | OPS provides `<Spinner>` and `<SpinnerFullScreen>` Vue components; or a simple disabled-button + spinner state |
| Uses logged-in user as import actor | Operators should not be required to enter a username; use session user | LOW | `$request->getUser()` — already available in `display()` |
| Error feedback on upload failure | ZIP too large, wrong file type, server error | LOW | Dropzone.js handles client-side size errors; server returns JSON error messages |

### Differentiators (Competitive Advantage)

Features that make the GUI more capable than "just a CLI wrapper."

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Dry-run results in a modal | The modal isolates dry-run output from the page; operator can read the report, then decide to proceed or abort — matches the mental model of "preview before commit" | MEDIUM | Use `openDialog()` or `openSideModal()` from OPS `useModal` composable. Side modal preferred (wider, scrollable). Content = pre-rendered PHP template returned as JSON. |
| Per-file invalid CSV download links | After a real import, list each file that produced failures with a download link for its invalid CSV | HIGH | Requires server to persist invalid CSVs in a temp location and return paths; adds a download endpoint to the op router |
| Import type auto-detection from ZIP contents | Inspect ZIP for presence of user/preprint CSV headers; pre-select the import type | HIGH | Requires server-side ZIP introspection before the full import runs; adds complexity without high operator demand — defer |
| Clear server selector (multi-server OPS installs) | Some OPS installs have multiple servers (contexts); the import should target the correct one | MEDIUM | Current CLI requires a server path in the CSV; GUI can pre-populate from the logged-in context. Not needed for v1 if server is inferred from context. |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Real-time row-by-row progress bar | "I want to see progress during a 10,000-row import" | PHP-over-HTTP is synchronous; streaming progress requires SSE or WebSocket, neither of which OPS supports natively. A progress bar that jumps to 100% on completion is worse than no bar. | Show a spinner during processing; display the final row count in results. |
| Async background import with job queue | "Import large files without blocking the browser" | OPS has a job queue (`BaseJob`, `ShouldQueue`) but jobs run via `php artisan queue:work` which requires a worker process. Most OPS installs do not have a persistent queue worker. `dispatchSync` degrades to synchronous anyway. Adds architecture complexity for marginal gain. | Synchronous import in `display()`; `set_time_limit(0)` for long operations. PHP will hold the connection open. |
| Import history / audit log in the GUI | "Show me all past imports" | Not in scope for v2. Requires persistent storage (DB table or log file), a new UI list panel, and pagination. Significantly increases scope. | CLI already logs to stdout and invalid CSV files; GUI can show the most recent result in session state only. |
| Edit/fix invalid rows inline in the GUI | "Let me correct bad data without downloading the CSV" | An inline CSV editor is a substantial UI component (table with cell editing, validation feedback). Out of proportion to the import workflow. | Auto-download invalid CSV; operator fixes in a spreadsheet and re-imports. |
| Multi-file drag-and-drop (multiple ZIPs) | "Import several ZIP packages at once" | Complicates result display (one result block per ZIP), error association, and cleanup. OPS CLI already processes all CSVs in a directory — but the ZIP model changes that. | Single ZIP per import run; include all CSVs + assets in one ZIP. |

---

## Feature Dependencies

```
[ZIP upload] ──requires──> [TemporaryFileManager::handleUpload()]
                                └──requires──> [POST /api/v1/temporaryFiles endpoint]

[Import execution]
    ──requires──> [ZIP upload complete + temporaryFileId]
    ──requires──> [Import type selected]
    ──requires──> [Server-side ZIP extraction to temp dir]
    ──requires──> [PreprintCommand or UserCommand instantiation with logged-in user]

[Dry-run results modal]
    ──requires──> [Import execution]
    ──requires──> [OPS useModal / openSideModal infrastructure]

[Invalid CSV auto-download]
    ──requires──> [Import execution produced failures]
    ──requires──> [Invalid CSVs written to a temp path accessible by HTTP]
    ──requires──> [Download endpoint in display() op router]

[Welcome email toggle] ──enhances──> [Import execution (users)]
    ──conflicts──> [Welcome email toggle visible when import type = preprints]

[Dry-run toggle] ──enhances──> [Import execution]
    ──conflicts──> [Real import and dry-run running simultaneously]
```

### Dependency Notes

- **ZIP upload requires TemporaryFileManager:** OPS's `POST /api/v1/temporaryFiles` endpoint stores the file and returns a `temporaryFileId`. The display op then retrieves it with `TemporaryFileDAO::getTemporaryFile($id, $userId)`. This is the established OPS pattern (used by NativeImportExportPlugin and UserImportExportPlugin).
- **Invalid CSV download requires a download op:** After import, the server must store invalid CSV paths somewhere (response JSON, session, or temp file listing) and expose a `download` op in `display()` that calls `FileManager::downloadByPath()`.
- **Welcome email toggle conflicts with preprint import type:** Render the toggle conditional on `importType === 'users'` at the Vue layer (v-show / v-if).
- **ZIP extraction has no OPS-provided utility:** `ZipArchive` (PHP native, confirmed available in OPS via `FileArchive::zipFunctional()`) must be used to extract to a temp directory that is cleaned up after import.

---

## MVP Definition

### Launch With (v1 of GUI milestone)

Minimum viable product — what operators need to trust the GUI for real imports.

- [ ] Import type selector (preprints / users) — without this, the form cannot route to the correct command
- [ ] ZIP file upload via Dropzone (FieldUpload or raw Dropzone) to `/api/v1/temporaryFiles` — the core input mechanism
- [ ] Server-side: extract ZIP to temp dir, run PreprintCommand or UserCommand with logged-in user, clean up temp dir — the core execution path
- [ ] Dry-run mode toggle wired to `$dryMode` flag — must ship with dry-mode; operators should not risk data without previewing
- [ ] Results display inline (row count, failure count, error list) after import — without feedback, operators cannot verify success
- [ ] Invalid CSV auto-download when failures exist — without this, operators cannot fix and re-import
- [ ] Welcome email toggle (visible only for users import) — feature parity with CLI
- [ ] Loading state (spinner + disabled submit button) during processing — required for usability; no feedback looks like a broken form
- [ ] Settings tab registration via `Template::Settings::website` hook — the specified integration point per PROJECT.md

### Add After Validation (v1.x)

Features to add once core import flow is confirmed working.

- [ ] Dry-run results in a side modal (vs. inline) — improves UX when the report is long; can be added after basic inline display is confirmed useful
- [ ] Per-file invalid CSV download links (named, linkable) — improves multi-file ZIP workflows; basic auto-download suffices for v1

### Future Consideration (v2+)

Features to defer until the GUI is validated in operator use.

- [ ] Import history / audit log — requires DB storage and a list panel
- [ ] Multi-server context selector — relevant only for site-admin multi-context installs
- [ ] Import type auto-detection from ZIP contents — nice UX but adds complexity

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Import type selector | HIGH | LOW | P1 |
| ZIP upload (Dropzone to temporaryFiles API) | HIGH | MEDIUM | P1 |
| ZIP extraction + command execution server-side | HIGH | MEDIUM | P1 |
| Dry-run toggle | HIGH | LOW | P1 |
| Results display (inline, post-import) | HIGH | MEDIUM | P1 |
| Invalid CSV auto-download | HIGH | MEDIUM | P1 |
| Loading state / spinner | HIGH | LOW | P1 |
| Welcome email toggle | MEDIUM | LOW | P1 |
| Settings tab registration | HIGH | LOW | P1 |
| Dry-run results in side modal | MEDIUM | MEDIUM | P2 |
| Per-file named invalid CSV download links | MEDIUM | MEDIUM | P2 |
| Import history / audit log | LOW | HIGH | P3 |
| Real-time progress bar | LOW | HIGH | P3 (anti-feature) |
| Async background job | LOW | HIGH | P3 (anti-feature) |

**Priority key:**
- P1: Must have for launch
- P2: Should have, add when possible
- P3: Nice to have / future consideration

---

## OPS Framework Constraints (Dependencies)

All features are constrained by the OPS 3.5.0 framework. The following OPS-provided primitives are load-bearing for the GUI:

| OPS Primitive | What It Provides | Feature It Enables |
|---------------|------------------|--------------------|
| `Template::Settings::website` hook | Injects `<tab>` into Settings > Website | Settings tab registration |
| `FormComponent` subclass | PHP-side form config serialized to Vue state | Form field definitions |
| `FieldUpload` (`field-upload` Vue component) | Dropzone.js wrapper wired to temporaryFiles API | ZIP upload |
| `POST /api/v1/temporaryFiles` | Stores uploaded file, returns `temporaryFileId` | ZIP upload persistence |
| `TemporaryFileDAO::getTemporaryFile()` | Retrieves uploaded file by ID | Server-side ZIP retrieval |
| `ZipArchive` (PHP native, available via `FileArchive::zipFunctional()`) | ZIP extraction | ZIP expansion to temp dir |
| `FileManager::downloadByPath()` | Forces browser file download with `Content-Disposition: attachment` | Invalid CSV auto-download |
| `ImportExportPlugin::display()` op router | Handles POST actions (uploadZip, import, download) | All server-side actions |
| `Spinner.vue` / `SpinnerFullScreen.vue` | Loading indicator component | Progress feedback |
| `useModal().openSideModal()` | Opens a scrollable side panel | Dry-run results modal |
| `$request->checkCSRF()` | CSRF validation | Form security |
| `$request->getUser()` | Logged-in user | Import actor (no username field needed) |

---

## Sources

- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/shariff/ShariffPlugin.php` — confirmed `Template::Settings::website::appearance` hook pattern
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/shariff/templates/settingsForm.tpl` — confirmed `<tab id="...">` + `<pkp-form>` template structure
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/templates/management/website.tpl` — confirmed all hook names: `Template::Settings::website`, `::appearance`, `::setup`, `::plugins`
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/api/v1/temporaryFiles/PKPTemporaryFilesController.php` — confirmed `POST /api/v1/temporaryFiles` endpoint and TemporaryFileManager usage
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/components/forms/FieldUpload.php` — confirmed `field-upload` component backed by Dropzone.js
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/plugins/importexport/users/PKPUserImportExportPlugin.php` — confirmed `display()` op routing pattern: uploadImportXML, importBounce, import, export
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/pages/management/PKPToolsHandler.php` — confirmed `management/importexport/plugin/CSVImportExportPlugin/` routing
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/file/FileArchive.php` — confirmed `ZipArchive` availability and `zipFunctional()` check
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/file/FileManager.php` — confirmed `downloadByPath()` for forced file downloads
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/composables/useModal.js` — confirmed `openSideModal()` composable API
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/components/Spinner/Spinner.vue` — confirmed Spinner component exists
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/jobs/BaseJob.php` — confirmed queue system exists but uses `database` connection by default; `dispatchSync` degrades to sync; rationale for anti-feature decision
- `/root/codes/PKP/OPS/3_5/ops/plugins/importexport/csv/CSVImportExportPlugin.php` — confirmed current plugin has no `display()` method; adding one is the extension point

---

*Feature research for: OPS CSV Import Plugin — Web GUI milestone (v2.0)*
*Researched: 2026-04-03*
