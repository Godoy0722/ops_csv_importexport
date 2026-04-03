# Architecture Research

**Domain:** OPS ImportExportPlugin — Web GUI integration for existing CLI-only CSV import plugin
**Researched:** 2026-04-03
**Confidence:** HIGH (all patterns sourced from live OPS 3.5.0 codebase)

## Standard Architecture

### How OPS Routes Web Requests to Import/Export Plugins

URL pattern: `/management/importexport/plugin/CSVImportExportPlugin/{op}[/{subArgs}]`

The routing chain (HIGH confidence — verified from source):

```
Browser
    ↓ HTTP request to /management/importexport/plugin/CSVImportExportPlugin/{op}
PKPToolsHandler::importexport()   [lib/pkp/pages/management/PKPToolsHandler.php]
    ↓ args = ['plugin', 'CSVImportExportPlugin', '{op}', ...]
    → PluginRegistry::getPlugin('importexport', 'CSVImportExportPlugin')
    → $plugin->display($args, $request)   ← THIS is the web entry point
CSVImportExportPlugin::display($args, $request)
    ↓ $op = array_shift($args)
    → switch ($op): case 'index': / case 'uploadZip': / case 'import': / etc.
```

The `display()` method is the sole web handler. It receives the remaining path segments after the plugin name as `$args`. Each logical operation is an `$op` string: `index`, `uploadZip`, `importBounce`, `import`, `downloadInvalidCsv`.

The base `ImportExportPlugin::display()` sets up breadcrumbs and a Smarty `plugin_url()` function. The plugin calls `parent::display()` first, then handles ops via `switch`.

### Request/Response Patterns (HIGH confidence)

**Page render:** Call `$templateMgr->display($this->getTemplateResource('index.tpl'))` from within `display()`. Returns nothing; Smarty writes to output directly.

**AJAX / JSON response:** Set `$this->result = $json->getString()` and `$this->isResultManaged = true`, then return. The base class or caller uses the result. Alternatively, set `header('Content-Type: application/json')` and `echo $json->getString()` directly.

**File download:** Set response headers and stream bytes directly. Use `FileManager::downloadByPath()`.

**CSRF protection:** Call `$request->checkCSRF()` before any state-mutating op. Throws `Exception` on mismatch, consistent with native plugin pattern.

### System Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│                        BROWSER                                        │
│  Upload form (Smarty/TPL)   AJAX polling   File download link         │
└──────────┬───────────────────────┬──────────────────┬───────────────┘
           │ POST multipart/form   │ GET/POST JSON     │ GET file
           ↓                       ↓                   ↓
┌──────────────────────────────────────────────────────────────────────┐
│                  CSVImportExportPlugin::display()                      │
│  op=index         op=uploadZip    op=import      op=downloadResult    │
│  (render page)    (upload ZIP)    (run import)   (serve CSV file)     │
└──────────┬───────────────────────┬──────────────────────────────────┘
           │                       │
           ↓                       ↓
┌─────────────────────┐  ┌──────────────────────────────────────────┐
│  TemporaryFileManager│  │         ImportJob (BaseJob)              │
│  handleUpload()      │  │  handle(): ZipExtractor → PreprintCommand│
│  returns tempFileId  │  │           or UserCommand → ob_get_clean  │
└─────────────────────┘  └──────────────────────────────────────────┘
                                      │
           ┌──────────────────────────┼──────────────────────────┐
           ↓                          ↓                          ↓
  PreprintCommand             UserCommand              CsvFileHandler
  (existing, unchanged)       (existing, unchanged)    (existing, unchanged)
  Processors, Validators,     Processors, Validators,  writes invalid_*.csv
  CachedEntities              CachedEntities
```

### Component Responsibilities

| Component | Responsibility | New or Existing |
|-----------|----------------|-----------------|
| `CSVImportExportPlugin::display()` | Route web ops, coordinate upload/extract/dispatch | **Modified** — add `display()` |
| `ZipExtractor` | Receive ZIP temp file ID, extract to scoped temp dir, return dir path | **New class** |
| `ImportJob` | Laravel queue job — wraps PreprintCommand/UserCommand, captures stdout, stores result | **New class** |
| `ImportResultStore` | Persist job result (output text + invalid CSV paths) keyed by jobId for polling | **New class** |
| `DryModeReporter` | stdout output (existing) | **Unchanged** — output captured by ImportJob |
| `PreprintCommand` / `UserCommand` | Import orchestration | **Unchanged** |
| `templates/index.tpl` | Upload form, import type/options toggles, progress indicator, result display | **New file** |

## Recommended Project Structure

```
plugins/importexport/csv/
├── CSVImportExportPlugin.php         # Modified: add display() method
├── classes/
│   ├── commands/                     # Unchanged
│   ├── handlers/
│   │   ├── CsvFileHandler.php        # Unchanged
│   │   ├── DryModeReporter.php       # Unchanged
│   │   ├── WelcomeEmailHandler.php   # Unchanged
│   │   └── ZipExtractor.php          # NEW — extract ZIP to temp dir
│   ├── jobs/
│   │   └── ImportJob.php             # NEW — async job, wraps commands
│   ├── store/
│   │   └── ImportResultStore.php     # NEW — persist job output for polling
│   ├── cachedAttributes/             # Unchanged
│   ├── exceptions/                   # Unchanged
│   ├── processors/                   # Unchanged
│   └── validations/                  # Unchanged
├── templates/
│   └── index.tpl                     # NEW — main GUI page
└── locale/en/locale.po               # Modified: add GUI strings
```

### Structure Rationale

- **`classes/jobs/`:** Follows OPS convention (`lib/pkp/jobs/{category}/JobName.php`). Keeps the job separate from command orchestration.
- **`classes/store/`:** ImportResultStore is a cross-request state carrier — separate namespace signals it is infrastructure, not domain logic.
- **`classes/handlers/ZipExtractor.php`:** Fits alongside `CsvFileHandler` as another file I/O handler. Keeps extraction logic out of the plugin entry point.
- **templates/index.tpl:** Required location for `$this->getTemplateResource('index.tpl')` to resolve correctly.

## Architectural Patterns

### Pattern 1: display() as HTTP Router with Op-Based Dispatch

**What:** `display($args, $request)` receives path segments after the plugin name. The first segment is the "op" — the operation to perform. A `switch` statement dispatches to per-op logic.

**When to use:** Always — this is the only entry point OPS provides to import/export plugins for web requests.

**Trade-offs:** All web operations in one method becomes long. Mitigate by delegating each op to a private method or separate handler class.

**Example (from PKPNativeImportExportPlugin):**
```php
public function display($args, $request)
{
    parent::display($args, $request);
    $op = array_shift($args);
    switch ($op) {
        case 'index':
        case '':
            $templateMgr->display($this->getTemplateResource('index.tpl'));
            $this->isResultManaged = true;
            break;
        case 'uploadZip':
            // handle file upload, return JSON with temporaryFileId
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
            $json = $temporaryFile
                ? (new JSONMessage(true))->setAdditionalAttributes(['temporaryFileId' => $temporaryFile->getId()])
                : new JSONMessage(false, __('common.uploadFailed'));
            header('Content-Type: application/json');
            $this->result = $json->getString();
            $this->isResultManaged = true;
            break;
        case 'import':
            if (!$request->checkCSRF()) { throw new Exception('CSRF mismatch!'); }
            // dispatch ImportJob, return jobId
            break;
    }
}
```

**URL generation in templates:** Use the Smarty `{plugin_url path="opName"}` function registered by `parent::display()`. This generates the correct URL for any op.

### Pattern 2: TemporaryFileManager for ZIP Upload

**What:** OPS provides `TemporaryFileManager::handleUpload($fieldName, $userId)` to accept a multipart upload, store it in `files/temp/`, and return a `TemporaryFile` record with an ID. The ID is sent back to the browser and used in subsequent requests.

**When to use:** For the ZIP upload op. The two-step pattern (upload → get tempFileId → submit tempFileId with options) decouples file upload from processing. This is the canonical OPS pattern — used by native import, plugin upload, and the REST API (`/api/v1/temporaryFiles`).

**Trade-offs:** Temporary files have a size limit from `upload_max_filesize`/`post_max_size`. Files expire on periodic cleanup. For large imports, this is fine because processing starts immediately after the ID is received.

**Confidence:** HIGH — `TemporaryFileManager::handleUpload()` is used verbatim in `PKPNativeImportExportPlugin::display()` case `uploadImportXML`.

### Pattern 3: Laravel Queue Jobs for Long-Running Imports

**What:** Extend `PKP\jobs\BaseJob`, implement `handle()`. Dispatch with `ImportJob::dispatch(...$args)`. The job runs asynchronously (database queue) or synchronously if OPS is under maintenance (handled automatically by `BaseJob::defaultConnection()`).

**When to use:** For the import execution step, which can run for minutes on large CSVs. Dispatching the job returns a jobId immediately; the browser polls a status endpoint.

**Trade-offs:** Requires OPS queue worker to be running for true async execution. If no worker runs, jobs process synchronously during the next web request via `JobRunner`. For import GUI, this means: if no worker, the import will block the HTTP response. This is a real constraint — see Pitfalls.

**Confidence:** HIGH — `PKP\jobs\BaseJob`, `Illuminate\Bus\Dispatchable`, and `::dispatch()` are established in `lib/pkp/jobs/`.

```php
namespace APP\plugins\importexport\csv\classes\jobs;

use APP\plugins\importexport\csv\classes\commands\PreprintCommand;
use PKP\jobs\BaseJob;
use PKP\user\User;

class ImportJob extends BaseJob
{
    public function __construct(
        public readonly string $sourceDir,
        public readonly int    $userId,
        public readonly string $importType,  // 'preprints' | 'users'
        public readonly bool   $dryMode,
        public readonly bool   $sendWelcomeEmail,
        public readonly string $jobResultKey,
    ) {
        parent::__construct();
        $this->timeout = 600;  // 10 min ceiling; large imports need this
    }

    public function handle(): void
    {
        $user = Repo::user()->get($this->userId);
        ob_start();
        $exitCode = match ($this->importType) {
            'preprints' => (new PreprintCommand($this->sourceDir, $user, $this->dryMode))->run(),
            'users'     => (new UserCommand($this->sourceDir, $user, $this->sendWelcomeEmail, $this->dryMode))->run(),
        };
        $output = ob_get_clean();
        ImportResultStore::save($this->jobResultKey, $output, $exitCode, $this->sourceDir);
    }
}
```

### Pattern 4: Output Buffering to Capture Command stdout

**What:** Wrap `PreprintCommand::run()` / `UserCommand::run()` in `ob_start()` / `ob_get_clean()`. Commands use `echo` and `DryModeReporter::print*()` internally. The buffered output becomes the web report.

**When to use:** Always within `ImportJob::handle()`. This is the zero-modification path — existing commands are completely unchanged.

**Trade-offs:** Commands must not call `exit()` or `ob_end_flush()` internally. `PreprintCommand::run()` currently returns an int exit code, and `executeCLI()` calls `exit($code)`. The job must call `run()` directly, not `executeCLI()`. Verify that `run()` does not call `exit()`.

**Confidence:** HIGH — this pattern is already used in the test suite (`ob_start()` / `ob_get_clean()` in `UserCommandDryModeTest.php`) to capture command output.

### Pattern 5: ZipExtractor — Extract ZIP to Scoped Temp Directory

**What:** Retrieve the temporary file path via `TemporaryFileDAO`, open it with `ZipArchive::open()`, extract to a dedicated temp directory (unique per job), and return the temp directory path for the command.

**When to use:** The import ZIP must be unpacked before `PreprintCommand`/`UserCommand` can read the CSVs. Extraction happens inside `ImportJob::handle()` via `ZipExtractor`.

**Trade-offs:** The temp directory must be cleaned up after the job completes (or on job failure). Use a `try/finally` block in `ImportJob::handle()`.

**Security:** Validate extracted paths to prevent ZIP path traversal. Each entry's `getNameIndex()` must be checked to ensure it doesn't start with `../` or an absolute path. OPS's `FileArchive` class creates ZIPs but does not extract — this logic is new.

**Confidence:** HIGH — PHP `ZipArchive` is available (OPS `FileArchive::zipFunctional()` confirms extension is loaded). PHP `sys_get_temp_dir()` or `Config::getVar('files', 'files_dir') . '/temp/'` as temp root.

### Pattern 6: Import Result Storage and Polling

**What:** `ImportResultStore` writes job results to a predictable file path (keyed by a UUID job result key). The browser polls an `op=pollResult&key={jobResultKey}` endpoint until results appear.

**When to use:** Stateless result retrieval — the job writes, the polling endpoint reads, the browser downloads `invalid_*.csv` files.

**Implementation options (in order of simplicity):**
1. **File-based store** — write a JSON file to `files/temp/{jobResultKey}.json`. Simple, no DB migration, cleaned up by existing temp cleanup logic.
2. **OPS plugin settings** — use `$this->updateSetting()`. Stores in DB but pollutes plugin settings. Not recommended.
3. **Custom DB table** — requires a migration. Overkill for transient job results.

Use option 1. `ImportResultStore::save($key, $output, $exitCode, $sourceDir)` writes the JSON. `ImportResultStore::load($key)` reads it. `ImportResultStore::delete($key)` cleans up after download.

**Confidence:** MEDIUM — file-based is idiomatic for temp data in OPS (`files/temp/` is the established temp area) but is not a pattern used by other plugins for job results specifically.

## Data Flow

### ZIP Upload Flow

```
Browser
  POST multipart ZIP to {plugin_url path="uploadZip"}
    → display() op=uploadZip
    → TemporaryFileManager::handleUpload('uploadedFile', $userId)
    → JSON response: { "temporaryFileId": 42, "success": true }
Browser
  Stores temporaryFileId in JS for next step
```

### Import Execution Flow

```
Browser
  POST to {plugin_url path="import"}
  Body: temporaryFileId=42, importType=preprints, dryMode=1, csrfToken=...
    → display() op=import
    → checkCSRF()
    → ZipExtractor::extract($temporaryFileId, $userId) → $sourceDir
    → $jobResultKey = uniqid('import_', true)
    → ImportJob::dispatch($sourceDir, $userId, $importType, $dryMode, $sendWelcomeEmail, $jobResultKey)
    → JSON response: { "jobResultKey": "import_abc123", "success": true }
Browser
  Polls {plugin_url path="pollResult"}&key=import_abc123 every 2 seconds
    → display() op=pollResult
    → ImportResultStore::load($key)
    → If found: JSON { "done": true, "output": "...", "invalidFiles": [...] }
    → If not:   JSON { "done": false }
Browser
  Shows report modal; offers download links for invalid CSVs
```

### Invalid CSV Download Flow

```
Browser
  GET {plugin_url path="downloadInvalidCsv"}&key=import_abc123&filename=invalid_preprints.csv
    → display() op=downloadInvalidCsv
    → checkCSRF() or verify signed URL
    → FileManager::downloadByPath($sourceDir . '/' . $filename)
    → Cleanup temp dir after last file downloaded (or schedule cleanup)
```

### Key Data Flows

1. **temporaryFileId handoff:** Upload op returns an ID; the subsequent import op takes that ID, resolves the file path via `TemporaryFileDAO`, extracts the ZIP, then discards the temporary file reference.
2. **jobResultKey handoff:** Import op dispatches the job and returns a key; polling op uses the key to read job results without coupling to job internals.
3. **sourceDir lifecycle:** Created by `ZipExtractor` in the import op; used by `ImportJob`; cleaned up in `ImportJob::handle()` finally block after `ImportResultStore::save()`.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Single operator | Synchronous execution inside HTTP request is acceptable |
| Multiple concurrent operators | Queue worker needed; without it, imports block web server threads |
| Very large CSVs (10k+ rows) | `ImportJob::$timeout` must exceed import duration; PHP `max_execution_time` must not kill the job process |

### Scaling Priorities

1. **First bottleneck:** PHP `max_execution_time` kills sync imports over ~30s. Mitigation: ensure the queue worker is running so `ImportJob` executes in a separate process.
2. **Second bottleneck:** Temp disk space. Large ZIPs + extracted files + invalid CSVs accumulate. Mitigation: `ZipExtractor` cleans up after job completion.

## Anti-Patterns

### Anti-Pattern 1: Calling executeCLI() from the Web Context

**What people do:** Call `$this->executeCLI($scriptName, $args)` directly from `display()` to reuse existing CLI parsing.

**Why it's wrong:** `executeCLI()` calls `exit()` at the end (`exit($exitCode)`). This terminates the PHP process, killing the HTTP response. The method also echoes output directly — no way to capture it.

**Do this instead:** Call `PreprintCommand::run()` or `UserCommand::run()` directly. These return an int exit code and produce output via `echo`. Capture with `ob_start()` / `ob_get_clean()`.

### Anti-Pattern 2: Running the Import Synchronously in display()

**What people do:** Call `run()` directly inside `display()` op=import to avoid the complexity of queue jobs.

**Why it's wrong:** Large imports take minutes. The HTTP connection times out. Web server kills the request. The import may be half-finished with no cleanup.

**Do this instead:** Dispatch an `ImportJob`. For development simplicity, start with `ImportJob::dispatchSync()` (forces synchronous execution regardless of queue config) if queue workers are not yet set up, then switch to `::dispatch()` for production.

### Anti-Pattern 3: Storing Import Output in Plugin Settings

**What people do:** Save job output via `$this->updateSetting('lastImportResult', $output)` to avoid creating new infrastructure.

**Why it's wrong:** Plugin settings are per-context, shared state. Concurrent imports from different operators overwrite each other. Settings have no expiry mechanism.

**Do this instead:** Key results by a UUID (`jobResultKey`) stored in a per-job temp file. Each operator's job has its own key.

### Anti-Pattern 4: Path Traversal in ZIP Extraction

**What people do:** Extract ZIP entries naively with `$zip->extractTo($dir)`, trusting the ZIP file's entry names.

**Why it's wrong:** A crafted ZIP can include entries like `../../config.inc.php` that overwrite OPS configuration files outside the temp dir.

**Do this instead:** Extract entries one by one, validating that `realpath(dirname($targetPath))` starts with `realpath($extractDir)` before writing each file.

### Anti-Pattern 5: Modifying PreprintCommand/UserCommand for Web Context

**What people do:** Add a `$captureOutput` flag or `OutputHandler` parameter to commands to support both CLI echo and web capture.

**Why it's wrong:** Commands were designed as stateless CLI utilities. Adding web context awareness violates single-domain responsibility and requires updating tests.

**Do this instead:** Use `ob_start()` / `ob_get_clean()` in `ImportJob::handle()`. Commands stay completely unchanged.

## Integration Points

### New Component ↔ Existing Component Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `display()` ↔ `TemporaryFileManager` | Direct call: `handleUpload($field, $userId)` | Returns `TemporaryFile` object; use its `getId()` for the response |
| `display()` ↔ `ImportJob` | `ImportJob::dispatch(...)` | Laravel Dispatchable trait; returns immediately |
| `ImportJob` ↔ `ZipExtractor` | Direct call: `ZipExtractor::extract($tempFileId, $userId)` | Returns extracted dir path |
| `ImportJob` ↔ `PreprintCommand` / `UserCommand` | Direct instantiation + `run()` inside `ob_start()` block | **No changes to commands required** |
| `ImportJob` ↔ `ImportResultStore` | `ImportResultStore::save($key, $output, $exitCode, $sourceDir)` | Writes JSON to `files/temp/` |
| `display()` ↔ `ImportResultStore` | `ImportResultStore::load($key)` in polling op | Returns null if job not yet complete |
| `display()` ↔ `FileManager` | `downloadByPath($path)` in download op | Streams file to browser; call `deleteByPath()` after last file |
| `display()` ↔ `TemporaryFileDAO` | `getTemporaryFile($tempFileId, $userId)` | Needed in ZipExtractor to resolve temp file path |

### External OPS Framework Dependencies

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| `PKPToolsHandler::importexport()` | Routes to `display()` automatically | No change needed — plugin name registration is sufficient |
| `TemplateManager` | `$templateMgr->display($this->getTemplateResource('index.tpl'))` | Template resource resolved from `templates/` dir in plugin root |
| `JSONMessage` | `new JSONMessage(true, $data)` → `$json->getString()` | Used for AJAX responses |
| `Request::checkCSRF()` | Must be called on all state-mutating ops | `getBounceTab()` shows the canonical pattern |
| `TemporaryFileManager` | `handleUpload()` handles multipart upload + DB record | Returns false on failure |
| `BaseJob` + `Dispatchable` | `ImportJob extends BaseJob`, dispatched via `::dispatch()` | OPS manages queue worker via `tools/runScheduledTasks.php` |

## Suggested Build Order

Based on dependencies:

1. **`ZipExtractor`** — Pure file I/O, no dependencies on other new components. Can be built and tested in isolation.
2. **`ImportResultStore`** — Pure file I/O, no dependencies on other new components.
3. **`ImportJob`** — Depends on `ZipExtractor`, `ImportResultStore`, and existing commands. Once those are ready, the job wires them together.
4. **`CSVImportExportPlugin::display()`** — Depends on `ImportJob`, `ZipExtractor` (indirectly via job), `ImportResultStore` (polling op), `TemporaryFileManager` (upload op). Implement ops in order: `index` → `uploadZip` → `import` → `pollResult` → `downloadInvalidCsv`.
5. **`templates/index.tpl`** — Can be developed alongside or after `display()`. Use the native plugin `index.tpl` as structural reference.
6. **`locale/en/locale.po`** — Add GUI strings as each op and template element is created.

## Sources

- `lib/pkp/classes/plugins/ImportExportPlugin.php` — `display()` contract, `pluginUrl()`, CSRF, `getBounceTab()`, `getImportedFilePath()`
- `lib/pkp/plugins/importexport/native/PKPNativeImportExportPlugin.php` — Reference implementation of all op types
- `plugins/importexport/native/templates/index.tpl` — Reference template with `FileUploadFormHandler` + `AjaxFormHandler`
- `lib/pkp/pages/management/PKPToolsHandler.php` — How `display()` is invoked; role requirements (MANAGER, SITE_ADMIN)
- `lib/pkp/classes/file/TemporaryFileManager.php` — `handleUpload()` signature and behavior
- `lib/pkp/api/v1/temporaryFiles/PKPTemporaryFilesController.php` — REST API pattern for file upload, alternative reference
- `lib/pkp/jobs/BaseJob.php` — Queue job base class, timeout/tries properties
- `lib/pkp/jobs/email/ReviewReminder.php` — Concrete job example showing constructor + `handle()` pattern
- `lib/pkp/classes/file/FileArchive.php` — Confirms `ZipArchive` extension usage in OPS
- `tests/Unit/Commands/UserCommandDryModeTest.php` — Confirms `ob_start()` / `ob_get_clean()` works for capturing command output
- `classes/commands/PreprintCommand.php` — `run()` returns int; `executeCLI()` calls `exit()` — confirms split at `run()` level

---
*Architecture research for: OPS CSV Import Plugin — Web GUI integration*
*Researched: 2026-04-03*
