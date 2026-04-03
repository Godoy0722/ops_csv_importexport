# Project Research Summary

**Project:** OPS CSV Import Plugin — Web GUI (v2.0)
**Domain:** Admin tooling / bulk data import web interface layered on top of an existing CLI plugin (OPS 3.5.x)
**Researched:** 2026-04-03
**Confidence:** HIGH

## Executive Summary

The v2.0 milestone adds a web GUI to an existing, well-tested CLI-only PHP import plugin. The plugin already handles all import logic (preprints, users, multi-locale, multi-version, dry mode) via `PreprintCommand` and `UserCommand`. The GUI is purely a delivery mechanism — it accepts a ZIP file containing CSVs and referenced assets, extracts it server-side, invokes the existing commands, captures their stdout output, and returns results to the operator's browser. The core challenge is not building import logic (that is done) but integrating correctly with OPS's web framework layers: the `ImportExportPlugin::display()` op router, OPS's `TemporaryFileManager` for file upload, PHP `ZipArchive` for extraction, and the OPS queue system for long-running jobs.

The recommended approach follows patterns established by OPS's own native and users importexport plugins. The GUI page lives at `management/importexport/plugin/CSVImportExportPlugin/` and is rendered by a Smarty `index.tpl` template. A Vue 3 component (bundled as an IIFE via Vite, following the funding plugin pattern) handles the frontend: file upload via `PkpFileUploader` (dropzone-vue3 wrapper), form options (import type, dry-mode, welcome email toggle), loading state, and results display via `openSideModal()`. All server-side ops route through `display()` as `case` branches. The import itself runs inside a `BaseJob` to avoid blocking the HTTP connection, with results stored in a temp file keyed by a UUID that the browser polls.

The primary risks are security-related (ZIP path traversal, ZIP bombs, missing CSRF, unauthorized download access) and operational (PHP upload/execution limits, temp directory leaks, cross-request static cache pollution from `CachedEntities`). All of these have concrete mitigations documented in PITFALLS.md, and every mitigation is a small, isolated addition to the new code — the existing CLI path is entirely untouched. The decision to use `ob_start()` / `ob_get_clean()` to capture command output rather than refactoring commands is the correct tradeoff for v2.0: it keeps the tested CLI path unchanged while enabling the web context.

---

## Key Findings

### Recommended Stack

The existing CLI stack (PHP 8.2, OPS Repo facade, SplFileObject, Laravel DB/Mail, Guzzle) requires no changes. The new GUI layer uses only technologies that are already bundled with OPS 3.5.0, honoring the constraint of no new dependencies.

For server-side rendering, Smarty 4.x (bundled) is the only option — OPS does not use Twig or Blade. Templates extend `layouts/backend.tpl` using `{block name="page"}`. For frontend interactivity, OPS's dual-layer JS architecture must be understood: the legacy jQuery-pkp layer handles tab management and form submission patterns; the modern Vue 3 layer (available as `pkp.modules.vue`) handles components like `PkpFileUploader`, `PkpProgressBar`, and `openSideModal()`. For new work, the Vue path is preferred. The file upload widget is `PkpFileUploader` (already registered globally). The results panel is a `SideModal` opened via `useModal().openSideModal()`. Vue components are compiled to IIFE format via Vite (following the funding plugin's `vite.config.js`) and registered via `pkp.registry.registerComponent()`.

**Core technologies (new only):**
- Smarty 4.x (bundled): server-side `.tpl` rendering — no alternative in OPS
- Vue 3.5.13 (bundled as `pkp.modules.vue`): frontend components — use pre-registered OPS components at zero cost
- `PkpFileUploader` (bundled, dropzone-vue3): drag-and-drop ZIP upload with progress
- `openSideModal()` / `useModal()` (bundled): results display panel
- `PKP\file\TemporaryFileManager::handleUpload()` (bundled): standard OPS two-step upload pattern
- PHP `ZipArchive` (PHP native, confirmed available via `FileArchive::zipFunctional()`): ZIP extraction — no library needed
- `PKP\jobs\BaseJob` (bundled): async job wrapper for long-running imports
- Vite IIFE build (OPS dev dep, funding plugin pattern): packages Vue SFC for script-tag loading

### Expected Features

The MVP (v1 of the GUI milestone) is well-defined. All table-stakes features map directly to existing CLI capabilities or OPS framework primitives already confirmed in the codebase.

**Must have (table stakes — P1):**
- Import type selector (preprints / users) — routes to the correct command; without this, the form cannot function
- ZIP file upload via `PkpFileUploader` — core input mechanism; ZIP bundles CSVs + referenced files
- Server-side ZIP extraction + command execution with logged-in user — core execution path
- Dry-run mode toggle wired to `$dryMode` flag — CLI parity; operators must preview before committing data
- Results display (row count, failure count, error list) — operators need confirmation of what happened
- Invalid CSV auto-download when failures exist — operators need the file to fix and re-import
- Welcome email toggle (visible only for users import) — CLI parity with `--sendWelcomeEmail`
- Loading state (spinner + disabled submit) during processing — basic usability requirement
- Settings tab registration via `Template::Settings::website` hook — specified integration point

**Should have (competitive — P2, add after validation):**
- Dry-run results in side modal (vs. inline display) — improves UX when the report is long
- Per-file named invalid CSV download links — improves multi-file ZIP workflows

**Defer (v2+):**
- Import history / audit log — requires DB table, list panel, pagination; out of proportion to import workflow
- Multi-server context selector — relevant only for site-admin multi-context installs
- Import type auto-detection from ZIP contents — nice UX but adds complexity without high demand
- Real-time row-by-row progress bar — anti-feature: OPS has no SSE/WebSocket infrastructure; a bar that jumps to 100% is worse than a spinner
- Async background job visible in a job queue UI — OPS job queue requires a persistent worker; most installs lack one

### Architecture Approach

The web GUI is a thin orchestration layer over the untouched existing command layer. `CSVImportExportPlugin::display()` is the sole web entry point — it receives ops as path segments and dispatches to per-op logic. Three new classes handle the new responsibilities: `ZipExtractor` (extract uploaded ZIP to a scoped temp directory with path traversal protection), `ImportJob` (a `BaseJob` that runs the import in a separate process, captures stdout via `ob_start()`, and stores results), and `ImportResultStore` (a file-based temp store keyed by UUID for polling). The browser polls `op=pollResult&key={jobResultKey}` until the job completes, then displays results in a `SideModal`.

**Major components (new):**
1. `CSVImportExportPlugin::display()` — HTTP op router: `index` / `uploadZip` / `import` / `pollResult` / `downloadInvalidCsv`
2. `classes/handlers/ZipExtractor` — ZIP extraction with path traversal + bomb protection; returns `$sourceDir`
3. `classes/jobs/ImportJob` — `BaseJob` wrapping `PreprintCommand`/`UserCommand` inside `ob_start()` block; writes result to `ImportResultStore`
4. `classes/store/ImportResultStore` — file-based result store at `files/temp/{jobResultKey}.json`; keyed by UUID; cleaned up after download
5. `templates/index.tpl` — Smarty page extending `layouts/backend.tpl`; initializes Vue component via `pkp.registry.init()`
6. Vue component (IIFE build) — handles file upload, form options, polling, results display via `openSideModal()`

**Existing components (unchanged):**
- `PreprintCommand` / `UserCommand` — invoked via `run()` (not `executeCLI()`), wrapped in `ob_start()`
- `CsvFileHandler`, `DryModeReporter`, all processors, validators, CachedEntities

### Critical Pitfalls

Security and operational risks are significant. All have concrete one-time mitigations.

1. **ZIP path traversal (Zip Slip)** — validate every ZIP entry name before calling `extractTo()`: reject entries with `..`, absolute paths, or `realpath()` escaping the temp dir. Fail the entire upload on any violation. This is a hard security requirement — not optional.

2. **ZIP bomb / decompression bomb** — before extraction, sum `ZipArchive::statIndex()['size']` for all entries; reject if total exceeds a configured limit (e.g., 5 GB) or any entry has > 100:1 compression ratio. Prevents disk exhaustion DoS by any manager-role user.

3. **echo statements corrupting JSON responses** — `PreprintCommand`, `UserCommand`, `DryModeReporter`, `GalleyProcessor`, `StatisticsProcessor`, and `WelcomeEmailHandler` all call `echo` directly. Always wrap command invocations in `ob_start()` / `ob_get_clean()` inside `ImportJob::handle()`. Never let echo reach the HTTP response body.

4. **Temp directory leaks on failure** — register a `register_shutdown_function()` before ZIP extraction to delete the temp dir even on fatal errors. Also add a scheduled cleanup task for dirs older than 24h using OPS's `ScheduledTaskPlugin` infrastructure, with a predictable prefix (e.g., `csv_import_{timestamp}_{userId}`).

5. **CachedEntities cross-request static cache pollution** — `CachedEntities` uses static properties which PHP-FPM workers may retain across requests. Call `CachedEntities::reset()` at the start of every web-triggered import. Also add a per-context import lock (via `flock()`) to prevent concurrent imports from the same context from creating duplicate submissions.

6. **Missing CSRF and role authorization** — call `$request->checkCSRF()` on every POST op in `display()`. Enforce `ROLE_ID_MANAGER` / `ROLE_ID_SITE_ADMIN` via `PluginAccessPolicy::ACCESS_MODE_MANAGE` in `authorize()`. Invalid CSV download endpoints must verify the `$userId` matches the temp file owner.

---

## Implications for Roadmap

Based on research, the build order follows technical dependencies: security-critical file I/O utilities must exist before the job that uses them; the job must exist before the HTTP op router that dispatches it; the op router must exist before the template that calls it.

### Phase 1: ZIP Extraction Infrastructure

**Rationale:** `ZipExtractor` and `ImportResultStore` have zero dependencies on other new components and must exist before any other new component can function. Building them first also forces early resolution of the two hardest security requirements (path traversal and ZIP bombs) before they are entangled with HTTP or job logic.

**Delivers:** `classes/handlers/ZipExtractor.php` (with full path traversal + bomb protection) and `classes/store/ImportResultStore.php` (file-based result store). Both are fully unit-testable in isolation.

**Addresses:** ZIP upload (prerequisite), invalid CSV result retrieval (prerequisite)

**Avoids:** ZIP path traversal (Pitfall 1), ZIP bomb (Pitfall 2), temp directory leaks (Pitfall 5)

### Phase 2: Import Job Wiring

**Rationale:** `ImportJob` bridges the existing command layer and the new web infrastructure. It depends on `ZipExtractor` (Phase 1) and `ImportResultStore` (Phase 1). Building the job before the HTTP layer allows it to be tested synchronously via `dispatchSync()` before queue complexity is introduced.

**Delivers:** `classes/jobs/ImportJob.php` — wraps `PreprintCommand` / `UserCommand` in `ob_start()` block; stores output in `ImportResultStore`. `CachedEntities` reset and session lock included here.

**Uses:** `BaseJob` (OPS bundled), `ob_start()` pattern (confirmed by existing test suite), `ZipExtractor` (Phase 1), `ImportResultStore` (Phase 1)

**Implements:** Output buffering pattern (Architecture Pattern 4), `CachedEntities` reset (Pitfall 8), import lock (Pitfall 8)

**Avoids:** echo corruption of JSON (Pitfall 4), concurrent import duplicate records (Pitfall 8)

### Phase 3: HTTP Op Router and Settings Tab

**Rationale:** `display()` wires all user-facing web ops together. It depends on `ImportJob` (Phase 2), `TemporaryFileManager` (OPS-bundled), and `ImportResultStore` (Phase 1). The settings tab registration hook is added in the same phase because both are in `CSVImportExportPlugin.php`.

**Delivers:** `CSVImportExportPlugin::display()` with ops: `uploadZip`, `import`, `pollResult`, `downloadInvalidCsv`. Settings tab injected via `Template::Settings::website` hook. CSRF protection and role authorization on all state-mutating ops.

**Uses:** `TemporaryFileManager::handleUpload()`, `JSONMessage`, `FileManager::downloadByPath()`, `$request->checkCSRF()`, `PluginAccessPolicy`

**Avoids:** Missing CSRF (Pitfall 6), unauthorized invalid CSV download (Pitfall 9), calling `executeCLI()` from web context (Architecture Anti-Pattern 1)

### Phase 4: Frontend (Template + Vue Component)

**Rationale:** The template and Vue component depend on all server ops being defined and stable (Phases 1-3). Building the frontend last means the API contract (op URLs, JSON shapes) is known before the first line of Vue is written.

**Delivers:** `templates/index.tpl`, Vue component (IIFE build via Vite), locale strings in `locale/en/locale.po`. Full operator workflow: upload ZIP, configure options, trigger import, poll for results, view results in side modal, download invalid CSVs.

**Uses:** Smarty 4.x, Vue 3 (`pkp.modules.vue`), `PkpFileUploader`, `openSideModal()` / `useModal()`, `PkpProgressBar`/Spinner, Vite IIFE build (funding plugin pattern), `pkp.registry.init()` / `registerComponent()`

**Implements:** Dual-layer JS architecture (Stack research), `{plugin_url path="opName"}` pattern, `{csrf}` Smarty tag injection

**Avoids:** Real-time progress bar anti-feature (synchronous import + spinner is the correct pattern), SSE/WebSocket (not in OPS), bounce-tab pattern (modal is more appropriate for transient results)

### Phase Ordering Rationale

- **Security-first:** The two hardest security requirements (ZIP traversal and bomb protection) are isolated in Phase 1's `ZipExtractor` where they can be unit-tested without HTTP or job complexity.
- **Bottom-up dependency chain:** `ZipExtractor` → `ImportResultStore` → `ImportJob` → `display()` → template. Each phase depends only on the previous.
- **Zero CLI regression risk:** Phases 1-3 add new classes only; Phase 3 adds a new method to the plugin entry point. The existing `executeCLI()` path and all existing commands are never modified.
- **Testability at each step:** Phase 1 and 2 produce classes that are fully unit-testable before any browser is involved. Phase 3 can be exercised via curl before Phase 4's frontend exists.

### Research Flags

Phases likely needing `/gsd:research-phase` during planning:

- **Phase 3 (HTTP Op Router):** The interaction between `PluginAccessPolicy`, `display()`, and the `authorize()` hook is not fully charted in the research. The exact method override needed in `CSVImportExportPlugin` to enforce role-based access on web requests (vs. CLI) needs verification against the PKPToolsHandler role check that already gates `management/importexport/`.
- **Phase 4 (Vue Component):** The exact Vite configuration and `vite.config.js` merge strategy for a plugin that lives inside the OPS directory tree (vs. the funding plugin which is at the OPS root) needs verification. The `external: ['vue']` + `globals: { vue: 'pkp.modules.vue' }` pattern is confirmed, but the build output path and how it interacts with OPS asset versioning (cache-busting) is not confirmed.

Phases with standard, well-documented patterns (skip additional research):

- **Phase 1 (ZipExtractor, ImportResultStore):** Pure PHP file I/O. `ZipArchive` API is standard PHP. The path traversal mitigation pattern is fully documented in PITFALLS.md with working code. File-based temp store is straightforward.
- **Phase 2 (ImportJob):** `BaseJob` extension pattern is well-established in `lib/pkp/jobs/`. `ob_start()` / `ob_get_clean()` pattern is already used in the test suite. No unknowns.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies verified by reading live OPS 3.5.0 source: exact versions, integration points, and API call signatures confirmed |
| Features | HIGH | Every OPS primitive that a feature depends on was traced to a concrete source file and method. Feature scope is well-bounded by CLI parity |
| Architecture | HIGH | Three reference implementations in the OPS codebase (native import, users import, funding plugin) provide concrete patterns for every new component. The `ob_start()` approach is already validated by the test suite |
| Pitfalls | HIGH | Security pitfalls (ZIP Slip, ZIP bomb) are backed by CVE records and real-world fix PRs. OPS-specific pitfalls (echo corruption, CachedEntities pollution) are confirmed by direct code inspection |

**Overall confidence:** HIGH

### Gaps to Address

- **Queue worker availability:** `ImportJob` dispatched via `::dispatch()` requires `php artisan queue:work` to be running for true async execution. Most OPS installs may not have a persistent queue worker. The roadmap should include a decision point: use `dispatchSync()` for v2.0 (simpler, synchronous, known timeout risk) or `dispatch()` (async, requires worker, no timeout risk). Research confirms `dispatchSync()` is the safer starting point with a documented path to `dispatch()`. Flag for project owner decision.

- **PHP upload limits:** `upload_max_filesize` and `post_max_size` are pre-PHP limits enforced by nginx/Apache. The GUI cannot control these. The system requirements documentation must be updated to specify minimum values (recommended: 512M). The GUI should display the current server limits so operators can self-diagnose upload failures. This is an operational gap, not a code gap — but it must be in scope for Phase 3 or Phase 4.

- **Import lock granularity:** The per-context `flock()` lock prevents concurrent imports from the same context but not from different users in the same context. Whether that distinction matters depends on the target operator's environment (single-admin vs. multi-manager). Treat as a known limitation and document it.

---

## Sources

### Primary (HIGH confidence)

- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/plugins/importexport/users/PKPUserImportExportPlugin.php` — upload/importBounce/import op pattern
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/plugins/importexport/native/PKPNativeImportExportPlugin.php` — reference implementation for all op types
- `/root/codes/PKP/OPS/3_5/ops/plugins/importexport/native/templates/index.tpl` — canonical tab + FileUploadFormHandler integration
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/plugins/ImportExportPlugin.php` — `display()` contract, `getBounceTab()`, `pluginUrl()`
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/composables/useModal.js` — `openDialog()`, `openSideModal()` API
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/components/FileUploader/FileUploader.vue` — `PkpFileUploader` API
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/funding/FundingPlugin.php` — Vue IIFE registration pattern
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/funding/vite.config.js` — Vite IIFE build for plugins
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/jobs/BaseJob.php` — queue job base class
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/file/TemporaryFileManager.php` — `handleUpload()` pattern
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/file/FileArchive.php` — `ZipArchive` availability confirmed via `zipFunctional()`
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/install/Installer.php` — `set_time_limit(0)` precedent
- `/root/codes/PKP/OPS/3_5/ops/tests/Unit/Commands/UserCommandDryModeTest.php` — confirms `ob_start()` / `ob_get_clean()` for command output capture
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/templates/management/website.tpl` — all `Template::Settings::website` hook names confirmed
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/shariff/ShariffPlugin.php` — settings tab injection hook pattern

### Secondary (MEDIUM confidence)

- [ZIP Slip vulnerability overview — Snyk](https://github.com/snyk/zip-slip-vulnerability) — path traversal CVE history and mitigation patterns
- [changedetection.io security advisory GHSA-25g8-2mcf-fcx9](https://github.com/dgtlmoon/changedetection.io/security/advisories/GHSA-25g8-2mcf-fcx9) — real-world ZIP traversal + bomb fix in one PR
- [SSE for long-running HTTP tasks — medium.com](https://medium.com/@jyotsna.a.choudhary/dealing-with-long-running-tasks-in-web-apps-the-sse-approach-ba8607638335) — rationale for SSE anti-feature decision (verified against MDN)

---
*Research completed: 2026-04-03*
*Ready for roadmap: yes*
