# Pitfalls Research

**Domain:** Adding a web GUI to an existing CLI-only PHP import plugin (OPS 3.5.x)
**Researched:** 2026-04-03
**Confidence:** HIGH (based on direct OPS codebase inspection + verified external sources)

---

## Critical Pitfalls

### Pitfall 1: ZIP Path Traversal (Zip Slip)

**What goes wrong:**
A malicious ZIP file contains entries with names like `../../config.inc.php` or `../../../etc/passwd`. When the server calls `ZipArchive::extractTo($tempDir)` without sanitizing entry names, PHP writes the file outside `$tempDir`, potentially overwriting OPS configuration files, executing code, or reading sensitive data.

**Why it happens:**
`ZipArchive::extractTo()` has a documented history of directory traversal CVEs (CVE-2008-5498, plus Windows-specific bypass in PHP 7.3/7.4/8.0). Developers assume the library is safe by default. It is not. The Central Directory in a ZIP can name entries with `../` prefixes, and PHP will follow them on extraction.

**How to avoid:**
Before extracting, iterate all entries with `ZipArchive::getNameIndex()` and reject any ZIP whose entry name: (a) contains `..`, (b) is an absolute path (`/` prefix), or (c) resolves via `realpath()` to a path outside the intended temp directory. Fail the entire upload if any entry is suspect — do not skip and continue.

```php
$zip = new ZipArchive();
$zip->open($uploadedPath);
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    // Reject traversal sequences and absolute paths
    if (str_contains($name, '..') || str_starts_with($name, '/')) {
        throw new RowValidationException('ZIP contains unsafe path: ' . $name);
    }
    // Confirm resolved path stays inside temp dir
    $resolved = realpath($tempDir . '/' . $name);
    if ($resolved !== false && !str_starts_with($resolved, realpath($tempDir))) {
        throw new RowValidationException('ZIP path escapes temp dir: ' . $name);
    }
}
$zip->extractTo($tempDir);
```

**Warning signs:**
- No pre-extraction loop over ZIP entries
- `extractTo()` called directly after `open()`
- Symlink entries not checked (`ZipArchive::OPSYS_UNIX` + mode bits)

**Phase to address:**
ZIP upload handler implementation phase (Phase 1 or Phase 2, whichever introduces ZipArchive extraction).

---

### Pitfall 2: ZIP Bomb / Decompression Bomb

**What goes wrong:**
A ZIP file is 1 MB compressed but expands to 10 GB uncompressed. Extracting it fills the server's disk or exhausts memory, taking down OPS for all users.

**Why it happens:**
PHP's `ZipArchive` does not limit decompressed output size. Operators uploading legitimate large imports (hundreds of PDFs) make developers hesitant to add size checks. Without a limit, a crafted archive can be weaponized by any user with access to the import GUI (manager role).

**How to avoid:**
Before extracting, sum `ZipArchive::statIndex()['size']` (uncompressed size) for all entries. Reject if total exceeds a configured threshold (recommended: 2–5 GB, configurable in plugin settings). Also reject if any single entry's compressed-to-uncompressed ratio exceeds 100:1 (a hallmark of bomb construction). These checks run before any disk I/O occurs.

```php
$totalUncompressed = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $totalUncompressed += $stat['size'];
    if ($stat['comp_size'] > 0 && ($stat['size'] / $stat['comp_size']) > 100) {
        throw new \Exception('Suspicious compression ratio in entry: ' . $stat['name']);
    }
}
if ($totalUncompressed > 5 * 1024 * 1024 * 1024) { // 5 GB
    throw new \Exception('ZIP uncompressed size exceeds limit.');
}
```

**Warning signs:**
- No `statIndex()` loop before extraction
- Only checking the uploaded file size (compressed), not the declared uncompressed size

**Phase to address:**
ZIP upload handler implementation phase.

---

### Pitfall 3: PHP Upload and Execution Time Limits Kill Large Imports Mid-Way

**What goes wrong:**
The web server enforces `upload_max_filesize`, `post_max_size`, and `max_execution_time`. A ZIP with 500 PDFs may exceed the default 8 MB upload limit, silently truncate with no error, or have PHP killed mid-import after 30–300 seconds. The import leaves partial records in the database (or worse, leaves a half-extracted temp directory).

**Why it happens:**
The existing CLI plugin uses `exit()` and `echo` freely — it was designed to run under a shell with no time limit. The web context has hard limits enforced by the PHP SAPI and web server. Developers copy the `set_time_limit(0)` pattern from `Installer.php` but forget that `upload_max_filesize` is a pre-PHP limit enforced by nginx/Apache before any PHP code runs.

**How to avoid:**
- Add a `[CSV Import Plugin]` section to OPS admin docs documenting required PHP ini values: `upload_max_filesize = 512M`, `post_max_size = 512M`, `max_execution_time = 0` (or a generous value like 3600).
- At runtime, call `@set_time_limit(0)` at the start of the import handler (as OPS Installer does at line 197).
- On the upload side, check `$_SERVER['CONTENT_LENGTH'] > 0 && empty($_FILES)` — this detects the silent truncation that occurs when `post_max_size` is exceeded.
- Display the server's current `upload_max_filesize` / `post_max_size` in the GUI so operators can self-diagnose.

**Warning signs:**
- Upload silently shows "no file received" when a large ZIP is submitted
- Import halts without writing to the invalid CSV
- Server returns 500 or empty response on large uploads

**Phase to address:**
Upload form and settings tab phase; also system requirements documentation.

---

### Pitfall 4: echo Statements in CLI Commands Corrupt JSON Responses

**What goes wrong:**
`PreprintCommand`, `UserCommand`, `DryModeReporter`, `GalleyProcessor`, `StatisticsProcessor`, and `WelcomeEmailHandler` all call `echo` directly. When these commands are invoked from a web request handler that returns a `JSONMessage`, the echoed text is prepended to the JSON output. The browser receives malformed JSON and the import result cannot be parsed.

**Why it happens:**
The commands were built exclusively for CLI. There is no output abstraction layer — all status messages go directly to stdout via `echo`. Adding a web handler that calls the same command objects without capturing their output will immediately corrupt the HTTP response.

**How to avoid:**
Wrap all command invocations inside an `ob_start()` / `ob_get_clean()` block to capture stdout. Surface the captured output as a field in the JSON response for display in the results modal. Do not let echo statements reach the HTTP response body.

```php
ob_start();
$exitCode = (new PreprintCommand($sourceDir, $user, $dryMode))->run();
$cliOutput = ob_get_clean();
return new JSONMessage(true, ['output' => $cliOutput, 'exitCode' => $exitCode]);
```

Alternatively (preferred long-term): refactor commands to accept an injectable output writer so CLI uses `echo` and web uses a string buffer — but output buffering is sufficient for v2.0 and avoids touching the tested CLI path.

**Warning signs:**
- JSON response starting with plain text before `{`
- Browser console shows `SyntaxError: Unexpected token` on import response
- Any `echo` or `print` call in a class that will be called from a web context

**Phase to address:**
First phase that wires the web handler to an existing command.

---

### Pitfall 5: Temp Directory Leaks on Failure

**What goes wrong:**
The web handler extracts a ZIP to a temporary directory, starts the import, and then the PHP process is killed (timeout, fatal error, or user closes the browser). The temp directory with all extracted files (potentially hundreds of MB of PDFs and images) is never cleaned up. Over time, the server disk fills up.

**Why it happens:**
CLI scripts run to completion or are explicitly killed — the operator controls cleanup. Web handlers are terminated by the SAPI without running shutdown logic. `register_shutdown_function()` is the only mechanism that survives a fatal error, but it does not run if the process is hard-killed (OOM killer, `kill -9`).

**How to avoid:**
- Use `register_shutdown_function()` to delete the temp directory even on fatal errors.
- Add a scheduled OPS task (via the existing `ScheduledTaskPlugin` infrastructure) that deletes import temp directories older than N hours.
- Name temp directories with a predictable prefix (e.g., `csv_import_{timestamp}_{userId}`) so the cleanup task can identify them.
- Do not reuse OPS's `TemporaryFileManager` for extracted ZIP contents — it manages individual files tracked in a DB table, not bulk directory extraction.

```php
$tempDir = sys_get_temp_dir() . '/csv_import_' . time() . '_' . $user->getId();
mkdir($tempDir, 0700, true);
register_shutdown_function(fn() => $this->rmdirRecursive($tempDir));
```

**Warning signs:**
- Temp directories accumulating in `/tmp` or OPS's files directory
- No cleanup code path for the temp extraction directory in the error/exception branches

**Phase to address:**
ZIP extraction and temp directory management phase.

---

### Pitfall 6: Missing CSRF Protection on Import Endpoints

**What goes wrong:**
A malicious page tricks a logged-in manager into submitting an import form cross-site, importing attacker-controlled data into the server.

**Why it happens:**
OPS provides `$request->checkCSRF()` (which compares `getUserVar('csrfToken')` to `$request->getSession()->token()`) but it must be called explicitly. The existing `CSVImportExportPlugin` never calls it because it is CLI-only. When adding a web handler inside `display()`, developers may pattern-match against simpler plugins that lack CSRF checks.

The native import plugin (`PKPNativeImportExportPlugin`) shows the correct pattern at line 202: `if (!$request->checkCSRF()) { throw new Exception('CSRF mismatch!'); }`. Use the same guard on every POST endpoint.

**How to avoid:**
Call `$request->checkCSRF()` at the top of every POST operation handler inside `display()`. The OPS UI framework already includes the CSRF token in all forms rendered via `TemplateManager` — it is included in AJAX requests by `$.pkp.classes.Handler`. No extra frontend work is needed if OPS's standard form infrastructure is used.

**Warning signs:**
- POST handler inside `display()` with no `checkCSRF()` call
- Custom HTML form not using OPS's Smarty form helpers (which auto-include the token)

**Phase to address:**
Import endpoint wiring phase (same phase that adds the POST handler).

---

### Pitfall 7: HTTP Timeout / Browser Timeout During Long Imports

**What goes wrong:**
A large preprint import (thousands of rows, hundreds of PDFs) takes 10–30 minutes. The browser times out waiting for the HTTP response. The import may still be running on the server (if `set_time_limit(0)` was set), but the operator sees a network error and has no way to know whether the import completed or failed.

**Why it happens:**
The existing CLI tool outputs progress in real time via `echo`. HTTP does not work this way — by default, the browser waits for the full response. Even with chunked transfer encoding, PHP's output buffering layers and nginx's `proxy_buffering` can swallow all output and deliver it in one burst at the end.

**How to avoid:**
Two viable approaches for OPS (in order of preference):

1. **Async job via OPS Queue**: Dispatch the import as an OPS `BaseJob` (the infrastructure exists — see `/lib/pkp/jobs/`). The HTTP response returns immediately with a job ID. The UI polls a status endpoint. This is the correct long-term pattern and fits OPS 3.5's existing job runner. The tradeoff is that jobs require the OPS queue worker to be running.

2. **Server-Sent Events (SSE)**: The handler writes `text/event-stream` with periodic flushes. The browser displays progress in real time. PHP-level output buffering must be disabled (`ob_end_flush()`, `ob_implicit_flush(true)`). Nginx must have `proxy_buffering off` for the SSE endpoint. This approach has infrastructure portability problems (many shared hosts buffer responses regardless).

**Do not**: Return a synchronous 200 response after a 15-minute PHP execution. Nginx's default `proxy_read_timeout` is 60 seconds — it will close the connection before the PHP script finishes on most shared hosting environments.

**Warning signs:**
- Import handler that calls `run()` synchronously and returns a `JSONMessage` at the end
- No periodic output or heartbeat during execution
- No separation between "submission accepted" and "import completed" states in the UI

**Phase to address:**
Progress reporting phase. This is the highest-complexity phase and should be scoped and researched separately.

---

### Pitfall 8: Concurrent Import Attempts

**What goes wrong:**
Two managers submit imports simultaneously. Both extract ZIPs to different temp dirs, both call `PreprintCommand::run()`. The `CachedEntities` static cache is populated by the first request and stale values may be read by the second (if they share the same PHP process — e.g., under PHP-FPM with persistent workers). More critically, two imports for the same preprint identifier can create duplicate submissions if both check `$processedPreprints` and neither sees the other's in-progress work (the array is per-request, not shared).

**Why it happens:**
`CachedEntities` uses static properties. PHP-FPM reuses workers across requests, meaning static state from one request can persist into the next within the same worker. The existing design is safe for CLI (one process per invocation) but not for concurrent web requests in the same worker.

**How to avoid:**
- In the web handler, call `CachedEntities::reset()` (or restore/reset via the existing `BaseTestCase` backup pattern) at the start of each import to prevent cross-request static cache pollution.
- Add a per-user or per-context import lock using `flock()` on a sentinel file: prevent a second import from the same context while one is in progress. Return a `JSONMessage(false, 'Import already in progress')` if the lock cannot be acquired.
- The duplicate-submission race condition is inherently hard to fix without a database-level unique constraint on `(server_id, identifier, version, locale)`. Flag this as a known limitation and document that concurrent imports for overlapping datasets are not supported.

**Warning signs:**
- No `CachedEntities` reset at the start of web-invoked imports
- No guard against a second import while one is running

**Phase to address:**
Import handler wiring phase. The lock is simple; the cache reset is a one-liner. The duplicate-record race condition is a known limitation to document rather than fully solve in v2.0.

---

### Pitfall 9: Invalid CSV Files Served Without Authorization Check

**What goes wrong:**
After an import, the handler stores `invalid_{filename}.csv` in the temp directory and returns a download URL. If the URL is predictable (e.g., based on filename and timestamp) and the download endpoint does not re-verify that the requesting user is the same user who ran the import, any logged-in user (or a guessing attacker) can download another manager's invalid CSV, which may contain sensitive user data (emails, ORCID IDs, etc).

**Why it happens:**
Developers focus on generating the invalid CSV correctly and add a straightforward "here's the path" download link. Authorization on the download endpoint is easy to forget when the form and the download are on the same page.

**How to avoid:**
- Store invalid CSV files under OPS's existing `TemporaryFileManager` path (keyed to the user ID) rather than a predictable temp directory path. This scopes files to the uploading user automatically.
- Alternatively, store the file path in the user session and only serve it to the session owner.
- The download endpoint must verify that the `temporaryFileId` (or whatever token is used) belongs to the requesting user — same pattern used by `TemporaryFileManager::getFile($fileId, $userId)`.

**Warning signs:**
- Download URL contains a filesystem path or a guessable timestamp
- Download handler does not check `$userId` ownership

**Phase to address:**
Invalid CSV download feature phase.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| `ob_start()` around entire command instead of injecting an output writer | No refactoring of command layer needed | Commands remain untestable in non-CLI contexts; output capturing is fragile (nested buffers) | v2.0 MVP; refactor in v3.0 |
| Synchronous HTTP import (no job queue) | No queue infrastructure dependency | Hard timeout ceiling; browser must stay open; no retry on failure | Only for small imports (< 1000 rows) |
| Hard-coded ZIP size limits | Simple to implement | Operators with legitimately large datasets hit the limit with no override | Never — make the limit configurable in plugin settings |
| Re-using the same temp dir across retries | Less disk I/O | Stale files from a failed previous import contaminate the retry | Never — always create a fresh UUID-named temp dir |
| Skipping path traversal check on upload from "trusted" managers | Fewer lines of code | Manager accounts can be compromised; ZIP files can be crafted offline | Never |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| OPS `checkCSRF()` | Forgetting it on the file upload AJAX endpoint (upload is typically a separate POST from the import trigger) | Call `checkCSRF()` on every state-mutating POST, including the upload step |
| OPS `TemporaryFileManager` | Using it to store extracted ZIP contents (it tracks individual files in a DB table) | Use it only for the initial ZIP upload; manage the extracted directory separately with manual cleanup |
| OPS `PluginAccessPolicy` | Not restricting the import endpoint to `ROLE_ID_MANAGER` or `ROLE_ID_SITE_ADMIN` | Add `PluginAccessPolicy` with `ACCESS_MODE_MANAGE` in `authorize()` — same pattern as `PluginGridHandler` |
| PHP `ZipArchive` | Calling `extractTo()` and trusting it to be safe | Always iterate entries and validate names before extraction |
| `CachedEntities` static state | Assuming it is clean at the start of a web request | Explicitly reset static caches before each web-triggered import |
| OPS job queue | Dispatching a job and assuming the worker is running | Document the requirement for `php artisan queue:work` in installation notes; provide a fallback for synchronous execution in dev environments |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| PHP memory limit exhausted reading large CSV | Fatal `Allowed memory size exhausted` mid-import, temp dir not cleaned up | Set `memory_limit = 512M` in docs; `ini_set('memory_limit', '512M')` in handler | CSVs with > ~50,000 rows or rows with large embedded data |
| Unextracted ZIP retained in memory after `ZipArchive::close()` | Memory stays high throughout import | Call `$zip->close()` immediately after extraction, before starting the import | Archives with hundreds of entries |
| `CachedEntities` growing unbounded in a long web request | Increasing memory per processed file | Already mitigated by static-cache pattern; ensure reset between runs | > 100 CSV files per single import batch |
| Session lock held during import | Other browser tabs for the same user freeze while import runs | Call `session_write_close()` before starting the long import operation | Any import lasting > a few seconds with session writes enabled |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Not validating ZIP entry names before extraction | Arbitrary file write (Zip Slip), potential RCE | Validate each entry name: no `..`, no absolute paths, `realpath()` check |
| Not checking compression ratios | DoS via ZIP bomb filling disk | Sum `statIndex()['size']` before extraction; reject if ratio > 100:1 |
| Predictable invalid CSV download URL | Data leak (operator CSV data visible to other users) | Scope downloads to `TemporaryFileManager` entries keyed by user ID |
| No role check on import endpoint | Any authenticated user (even authors) can trigger bulk imports | Enforce `ROLE_ID_MANAGER` or `ROLE_ID_SITE_ADMIN` via `PluginAccessPolicy` |
| No MIME type validation on upload | Non-ZIP files processed as ZIPs, causing unpredictable extraction failures | Validate `$_FILES['zip']['type']` and also check magic bytes (`PK\x03\x04`) |
| Temp dir world-readable | Other users on the server can read extracted CSVs/files during import | Create temp dir with `mkdir($path, 0700)` — owner-only permissions |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| No progress feedback during long import | Operator cannot tell if import is running or hung; may refresh and double-import | Show "Import in progress..." state immediately on submit; use polling or SSE for progress |
| Dry-mode results only shown in a modal | Operator cannot review 500 invalid rows in a small modal | Offer auto-download of the invalid CSV; the CLI already generates it — wire the download link |
| No pre-upload size feedback | Operator uploads 400 MB ZIP, gets opaque "upload failed" error from nginx | Display the server's `upload_max_filesize` limit in the form as a hint |
| Form submission with no confirmation on navigate-away | Operator accidentally closes the tab mid-import, leaving the server in an unknown state | Implement `beforeunload` warning while import is running |
| Import success with no record of what was imported | Operator cannot audit what a previous import did | Log import summary (file names, row counts, errors) to a persistent plugin setting or OPS notification |

---

## "Looks Done But Isn't" Checklist

- [ ] **ZIP extraction:** Entry names validated before `extractTo()` — verify path traversal test exists
- [ ] **ZIP bomb:** `statIndex()` sum checked before extraction — verify test with crafted high-ratio ZIP
- [ ] **CSRF:** `$request->checkCSRF()` called on every POST handler in `display()` — verify both upload and import operations
- [ ] **Temp cleanup:** `register_shutdown_function()` registered before extraction — verify cleanup happens on simulated fatal error
- [ ] **Scheduled cleanup:** Stale temp dirs older than 24h are removed by a scheduled task — verify cleanup runs
- [ ] **echo capture:** `ob_start()` / `ob_get_clean()` wraps all command invocations — verify JSON response is valid when import produces echoed output
- [ ] **Role guard:** Import endpoint enforces manager/site-admin role — verify author-role user cannot reach the endpoint
- [ ] **Invalid CSV download auth:** Download endpoint checks `$userId` matches temp file owner — verify cross-user access is rejected
- [ ] **Session lock:** `session_write_close()` called before long import — verify other tabs are not frozen during import
- [ ] **CachedEntities reset:** Static cache is cleared at the start of each web-triggered import — verify second import in same PHP worker reads fresh DB data

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Temp dir not cleaned up after failure | LOW | Add a one-off cleanup script; add scheduled task going forward |
| Partial import (PHP killed mid-run) | HIGH | Identify submissions created since the import started (by `date_submitted`); review against source CSV; delete orphaned records manually via Repo facade |
| ZIP Slip file overwrite | HIGH | Restore overwritten files from backup; audit file system for unexpected writes; rotate credentials if config was overwritten |
| Duplicate submissions from concurrent imports | MEDIUM | Identify duplicates via `identifier` collision; delete one set of submissions via admin UI; add per-context import lock going forward |
| JSON corruption from uncaptured echo | LOW | Add `ob_start()` wrapper; no data loss |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| ZIP path traversal | ZIP upload + extraction phase | Unit test: ZIP with `../` entry is rejected before extraction |
| ZIP bomb | ZIP upload + extraction phase | Unit test: ZIP with > 100:1 ratio entry is rejected; integration test with crafted archive |
| PHP upload/time limits | Upload form + settings phase | Manual test: upload a file > `upload_max_filesize`; verify error message is user-friendly |
| echo corruption of JSON | Import handler wiring phase | Integration test: import that produces echo output returns valid JSON |
| Temp dir leak | ZIP extraction + cleanup phase | Test: simulate fatal error; verify temp dir is removed by shutdown function |
| CSRF missing | Import endpoint phase | Security test: POST without csrfToken returns error; not a 200 |
| HTTP timeout | Progress reporting phase | Manual test: import of 5000 rows completes with progress feedback visible |
| Concurrent imports | Import handler wiring phase | Concurrent test: two simultaneous imports; verify no duplicate submissions |
| Invalid CSV download auth | Download feature phase | Test: user B cannot download user A's invalid CSV by guessing the ID |
| CachedEntities cross-request pollution | Import handler wiring phase | Test: second import in same process sees fresh server/section data |

---

## Sources

- OPS codebase: `lib/pkp/plugins/importexport/native/PKPNativeImportExportPlugin.php` — reference implementation for `checkCSRF()`, `TemporaryFileManager::handleUpload()`, and `importBounce` pattern
- OPS codebase: `lib/pkp/classes/install/Installer.php` line 197 — `@set_time_limit(0)` precedent for long-running web operations
- OPS codebase: `lib/pkp/classes/core/PKPRequest.php` line 431 — `checkCSRF()` implementation
- OPS codebase: `lib/pkp/classes/file/FileArchive.php` — OPS's own ZIP creation (does not validate on extraction)
- OPS codebase: `plugins/importexport/csv/classes/` — all `echo` call sites surveyed: `PreprintCommand`, `UserCommand`, `DryModeReporter`, `GalleyProcessor`, `StatisticsProcessor`, `WelcomeEmailHandler`, `InvalidRowValidations`
- [ZIP Slip vulnerability overview (Snyk)](https://github.com/snyk/zip-slip-vulnerability) — HIGH confidence (official vulnerability database)
- [PHP ZipArchive::extractTo() directory traversal CVE](https://www.cvedetails.com/bugtraq-bid/32625/PHP-ZipArchive-extractTo-.zip-Files-Directory-Traversal.html) — HIGH confidence (CVE record)
- [ZIP bomb + path traversal fix reference (changedetection.io)](https://github.com/dgtlmoon/changedetection.io/security/advisories/GHSA-25g8-2mcf-fcx9) — MEDIUM confidence (real-world fix showing both patterns in one PR)
- [SSE for long-running HTTP tasks](https://medium.com/@jyotsna.a.choudhary/dealing-with-long-running-tasks-in-web-apps-the-sse-approach-ba8607638335) — MEDIUM confidence (community article, verified against MDN)

---
*Pitfalls research for: OPS CSV Import Plugin — Web GUI milestone*
*Researched: 2026-04-03*
