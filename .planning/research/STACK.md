# Stack Research

**Domain:** OPS 3.5.x Import/Export Plugin — Web GUI additions
**Researched:** 2026-04-03
**Confidence:** HIGH (all findings verified directly from OPS 3.5.0 source code)

---

## What Was Researched

This research covers ONLY the **new capabilities** needed for the v2.0 Web GUI milestone. Existing CLI stack (PHP 8.2, OPS Repo facade, PHPUnit 11.x, Guzzle, SplFileObject, Laravel DB/Mail) is already validated and excluded.

---

## Recommended Stack (New Capabilities Only)

### Template / Server-Side Rendering

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Smarty 4.x | bundled with OPS 3.5.0 | Server-side HTML rendering for the plugin `index.tpl` page | OPS's native template engine. `PKPTemplateManager` extends `Smarty` directly. Every existing importexport plugin uses `.tpl` files. No alternative exists within the OPS framework. |

**Integration point:** Extend `{extends file="layouts/backend.tpl"}` with a `{block name="page"}` block. Register template resources via `Plugin::getTemplateResource()` (already wired in the plugin system). The `display()` method on `ImportExportPlugin` calls `TemplateManager::getManager($request)->display($this->getTemplateResource('index.tpl'))`.

**Smarty tags used by OPS plugins:**
- `{plugin_url path="action"}` — generates CSRF-safe plugin URLs
- `{csrf}` — injects CSRF token hidden input into forms
- `{translate key="..."}` — i18n via `locale/en/locale.po`
- `{fbvFormArea}`, `{fbvFormSection}`, `{fbvFormButtons}` — PKP form layout helpers
- `{include file="controllers/fileUploadContainer.tpl"}` — legacy plupload widget

---

### JavaScript / Frontend Layer

OPS 3.5.0 runs a **dual-layer** JS architecture. Understanding both is required to choose the right approach.

#### Layer 1: Legacy jQuery-pkp (for form submission, tab management)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| jQuery | 3.7.1 | DOM manipulation, AJAX, event handling | Bundled by OPS; all `pkpHandler` patterns depend on it |
| jQuery UI | 1.14.1 | Tabs (`#importExportTabs`), dialog positioning | Used by existing importexport plugin tab pattern |
| pkp.controllers.TabHandler | bundled | Multi-tab layout for the plugin page | The `$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler')` pattern is used verbatim in the native importexport plugin |
| pkp.controllers.form.FileUploadFormHandler | bundled | Wires legacy plupload to form submission | Used in `native` and `users` importexport plugins; handles `temporaryFileId` round-trip |
| pkp.controllers.form.AjaxFormHandler | bundled | AJAX form submission, `importBounce` → `addTab` event | Required for the `getBounceTab()` → `setEvent('addTab', ...)` → results tab pattern |

**The "bounce tab" pattern** (used by both native and users importexport plugins):
1. User submits form → PHP returns `JSONMessage` with `setEvent('addTab', ['title' => ..., 'url' => ...])`.
2. jQuery handler opens a new tab, loads that URL via AJAX.
3. The second URL does the actual processing and returns HTML wrapped in a `JSONMessage`.

This pattern **does not require any new JS**. It is entirely driven by existing pkp.controllers infrastructure.

#### Layer 2: Vue 3 + PKP ui-library (for modern components)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Vue 3 | 3.5.13 | Component framework for modal, progress, file upload UI | OPS bundles Vue 3; all ui-library components use it. Available globally as `pkp.modules.vue`. |
| Pinia | 2.3.1 | State management; `useModal()` composable accesses `modalStore` | Required for `openDialog()` / `openSideModal()` |
| dropzone-vue3 | 1.0.2 | Drag-and-drop file upload with progress feedback | Used by `PkpFileUploader` component. Already registered globally as `PkpFileUploader`. |
| ofetch | 1.4.1 | Fetch abstraction used by `useFetch` composable | OPS's standard HTTP client for Vue components; handles CSRF tokens automatically |
| @vueuse/core | 10.11 | Utility composables (debounce, etc.) | Bundled dependency |

**Pre-registered Vue components** (available in any backend page at zero cost):
- `PkpFileUploader` — drag-and-drop upload with progress; uses dropzone-vue3 internally
- `PkpFileUploadProgress` — progress bar + cancel for an in-progress upload
- `PkpProgressBar` — indeterminate/determinate progress bar
- `PkpModal` — base modal
- `PkpFormModal` — modal with form layout
- `Dialog` (via `openDialog()` from `useModal()`) — confirmation/results dialog with actions
- `SideModal` (via `openSideModal()` from `useModal()`) — slide-in panel with component slot
- `PkpButton` — OPS-styled button

**Access from Smarty templates:**
```js
// In a <script> tag inside a .tpl block
pkp.registry.init('myPageId', 'MyPageComponent', {state});
// Or use pkp.eventBus.$emit for cross-component events
```

---

### File Upload — ZIP Handling

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `PKP\file\TemporaryFileManager` | OPS-bundled | Stores uploaded file to `files/temp/`, returns `TemporaryFile` DB record | Standard OPS upload handler; used by both native and users importexport plugins. `handleUpload('uploadedFile', $userId)` is the integration point. |
| PHP `ZipArchive` | PHP native (requires `ext-zip`) | Extract ZIP to temp dir on server | Already used in OPS (`PKP\file\FileArchive`, `UpdateRorRegistryDataset`). `ZipArchive::extractTo()` is the standard pattern. No external library needed. |
| PHP `sys_get_temp_dir()` + `tempnam()` | PHP native | Create unique temp directory for extracted files | Same approach as `TemporaryFileManager::getBasePath()` + `tempnam()`. |
| `PKP\file\FileManager::deleteByPath()` | OPS-bundled | Cleanup extracted temp dir after import | Already in use throughout the plugin. |

**Upload flow for ZIP:**
1. `TemporaryFileManager::handleUpload('uploadedFile', $userId)` → stores zip in `files/temp/`, returns `TemporaryFile`.
2. On import action: retrieve temp file path via `TemporaryFileDAO::getTemporaryFile($id, $userId)`.
3. `$zip = new ZipArchive(); $zip->open($tempFilePath); $zip->extractTo($extractDir);` — PHP native.
4. Pass `$extractDir` as `$sourceDir` to `PreprintCommand` or `UserCommand`.
5. Cleanup: `$fileManager->deleteByPath($extractDir)` (recursive) after import completes or fails.

---

### Form Handling and CSRF

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `{csrf}` Smarty tag | OPS-bundled | Injects Laravel CSRF token into forms | Required for all POST forms in OPS backend. `$request->checkCSRF()` verifies it. |
| `PKPRequest::checkCSRF()` | OPS-bundled | Server-side CSRF validation | Called in every `display()` action handler before processing. Standard OPS security pattern. |
| `PKPRequest::getUserVar($key)` | OPS-bundled | Reads POST/GET parameters | Standard OPS pattern; avoids direct `$_POST` access. |

**Form pattern (from native importexport plugin):**
```php
// In display():
case 'uploadZip':
    $temporaryFile = (new TemporaryFileManager())->handleUpload('uploadedFile', $user->getId());
    $json = new JSONMessage($temporaryFile !== false);
    $json->setAdditionalAttributes(['temporaryFileId' => $temporaryFile->getId()]);
    header('Content-Type: application/json');
    return $json->getString();

case 'importBounce':
    // Returns addTab event; FileUploadFormHandler auto-wires temporaryFileId
    return $this->getBounceTab($request, 'Results', 'import', ['temporaryFileId' => $request->getUserVar('temporaryFileId')]);

case 'import':
    if (!$request->checkCSRF()) throw new Exception('CSRF mismatch!');
    // ... do work ...
    $json = new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('results.tpl')));
    header('Content-Type: application/json');
    return $json->getString();
```

---

### Modal / Dialog Components

OPS 3.5.0 offers two paths for modals. Use the Vue path for v2.0.

| Component | Layer | When to Use | How to Open |
|-----------|-------|-------------|-------------|
| `Dialog` (`PkpModal` + reka-ui `DialogRoot`) | Vue 3 | Results display, confirmations, error details | `pkp.modules.useModal.useModal().openDialog({title, message, actions})` from Vue; or `pkp.eventBus.$emit('open-modal-vue', {component, ...})` from jQuery layer |
| `SideModal` | Vue 3 | Full import wizard, dry-mode results panel | `pkp.modules.useModal.useModal().openSideModal(MyComponent, props)` |
| `VueModalHandler` (legacy bridge) | jQuery → Vue | Open a Vue component from a legacy jQuery-pkp context | `pkpHandler('$.pkp.controllers.modal.VueModalHandler', {component: 'ComponentName', ...})` |
| `AjaxModalHandler` (legacy) | jQuery | Open a URL-loaded modal | Avoid for new work; use Vue modal instead |

**For the dry-mode results modal**, use `openSideModal()` with a custom Vue component loaded as the plugin's built JS. This matches how the funding plugin registers its `FundingGrid` component.

---

### Progress Feedback

OPS 3.5.0 does **not** use SSE, WebSockets, or long-polling anywhere in the codebase. The correct approach for v2.0 is:

| Approach | Verdict | Rationale |
|----------|---------|-----------|
| **Indeterminate spinner + single POST** | RECOMMENDED | Import runs as a single synchronous HTTP request. `PkpSpinnerFullScreen` (already in `backend.tpl`) shows automatically when `isLoading` is true in the Vue app state. Use `PkpProgressBar` (no `value`, shows animation) while awaiting response. |
| **Client-side polling** | ACCEPTABLE for large imports | jQuery `setInterval` polls a status endpoint. OPS has no existing status store pattern, so this requires new PHP state (session or temp file). Adds complexity. |
| **Server-Sent Events** | DO NOT USE | Not used anywhere in OPS. No infrastructure support. PHP import runs synchronously; no streaming architecture exists. |
| **WebSocket** | DO NOT USE | Not used anywhere in OPS. No server-side support. |

**Recommended progress feedback implementation:**
1. Vue component sets `isLoading = true` before POST.
2. PHP `display()` handler runs the full import synchronously (same as CLI).
3. Response returns JSON with results summary.
4. Vue component receives response, sets `isLoading = false`, opens `Dialog` or `SideModal` with results.

For very large imports where PHP max_execution_time is a concern: set `@set_time_limit(0)` in the handler (same as `PKP\classes\install\Installer`).

---

### Plugin JavaScript Registration Pattern

The funding plugin establishes the canonical pattern for plugins that ship Vue components.

| Step | What | Where |
|------|------|-------|
| 1 | Write Vue SFC components in `resources/js/Components/` | e.g., `CsvImportPage.vue` |
| 2 | Register components in `resources/js/main.js` | `pkp.registry.registerComponent('CsvImportPage', CsvImportPage)` |
| 3 | Build with Vite IIFE format | `vite.config.js` → `formats: ['iife']`, output to `public/build/build.iife.js` |
| 4 | Load in plugin `register()` | `$templateMgr->addJavaScript('csvImport', $baseUrl . '/' . $this->getPluginPath() . '/public/build/build.iife.js', ['contexts' => ['backend'], 'priority' => TemplateManager::STYLE_SEQUENCE_LAST])` |
| 5 | Init in Smarty template | `pkp.registry.init('app', 'CsvImportPage', {$state|json_encode})` |

**Externalize Vue** in `vite.config.js` (same as funding plugin):
```js
external: ['vue'],
output: { globals: { vue: 'pkp.modules.vue' } }
```

---

### Locale / i18n

| Technology | Purpose | Integration |
|-----------|---------|-------------|
| `locale/en/locale.po` | All user-visible strings | Add new keys here; `{translate key="..."}` in `.tpl`, `__('...')` in PHP, `t('...')` / `useLocalize()` in Vue via `pkp.modules.useLocalize` |

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| New Composer dependencies | Constraint: no new dependencies (PROJECT.md) | PHP built-ins: `ZipArchive` (native), `SplFileObject` (already used) |
| `npm install` new packages | Vite build externalizes Vue; no new deps needed | Use pre-registered OPS ui-library components |
| Custom CSS framework | OPS uses Tailwind (from ui-library) + LESS | Write utility classes with Tailwind tokens already available |
| Raw `<input type="file">` + XMLHttpRequest | Legacy approach; no progress events built-in | `PkpFileUploader` wraps dropzone-vue3 with progress events |
| Twig templates | OPS does not use Twig | Smarty `.tpl` files only |
| REST API endpoints | Importexport plugins use `display()` action routing, not REST | `display()` with `case 'uploadZip': / 'import': / 'importBounce':` |
| Server-Sent Events | No OPS infrastructure; synchronous PHP import doesn't support streaming | Indeterminate progress + single POST |
| jQuery `$.ajax()` directly | Works but bypasses Vue composable pattern | `useFetch` from `pkp.modules.useFetch` in Vue components, or the legacy `AjaxFormHandler` for Smarty-only pages |

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not Alternative |
|----------|-------------|-------------|---------------------|
| File upload widget | `PkpFileUploader` (Vue, dropzone-vue3) | Legacy `fileUploadContainer.tpl` + plupload | plupload is legacy Flash-era tech; still functional but PkpFileUploader is the modern path. Use legacy only if staying 100% in Smarty/jQuery layer. |
| Results display | `openSideModal(MyResultsComponent)` | Bounce-tab pattern (`getBounceTab`) | Bounce-tab adds a persistent tab to the management page which cannot be removed by the user. A modal is more appropriate for transient import results. |
| Progress feedback | Indeterminate spinner (built-in) | Client polling | Polling requires PHP session state tracking across requests; adds a status endpoint and DB/session writes. Over-engineered for typical import sizes. |
| Vue component build | Vite IIFE (funding plugin pattern) | Webpack / no build | Vite is the OPS project standard (vite.config.js in OPS root); IIFE format is required for script-tag loading without module bundler. |
| ZIP extraction | `ZipArchive::extractTo()` (PHP native) | `PharData` | PharData is used for plugin installation from `.tar.gz`; ZipArchive is used in OPS for ZIP files specifically. Both are already present in the codebase. |

---

## Version Compatibility

| Component | OPS 3.5.0 Version | Notes |
|-----------|-------------------|-------|
| Smarty | 4.x (bundled) | Never instantiate Smarty directly; always use `TemplateManager::getManager($request)` |
| Vue | 3.5.13 | Available as `pkp.modules.vue` global; externalize in Vite config |
| jQuery | 3.7.1 (bundled) | Available as global `$`; do not import separately |
| plupload | bundled (legacy) | Still functional in OPS 3.5.0; avoid for new code |
| dropzone-vue3 | 1.0.2 (bundled via ui-library) | Used internally by `PkpFileUploader`; do not import directly |
| ZipArchive | PHP native (requires ext-zip) | Already a PHP requirement for OPS; safe to use |
| Vite | 7.1.0 (OPS dev dep) | Funding plugin demonstrates IIFE build pattern |

---

## Sources

- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/plugins/importexport/users/PKPUserImportExportPlugin.php` — upload/importBounce/import action pattern (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/plugins/importexport/native/PKPNativeImportExportPlugin.php` — uploadImportXML/importBounce/import pattern (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/plugins/importexport/native/templates/index.tpl` — canonical tab + FileUploadFormHandler integration (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/plugins/ImportExportPlugin.php` — `getBounceTab()`, `display()` base, `pluginUrl()` (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/templates/layouts/backend.tpl` — `<modal-manager>`, Vue app initialization, `pkp.registry.init()` pattern (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/components/FileUploader/FileUploader.vue` — dropzone-vue3 wrapper, `PkpFileUploader` API (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/components/ProgressBar/ProgressBar.vue` — `PkpProgressBar` component (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/components/FileUploadProgress/FileUploadProgress.vue` — `PkpFileUploadProgress` component (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/components/Modal/Dialog.vue` — Dialog modal component (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/composables/useModal.js` — `openDialog()`, `openSideModal()` composable (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/ui-library/src/composables/useFetch.js` — `ofetch` wrapper, CSRF handling (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/js/load.js` — `PkpFileUploader`, `PkpModal`, `PkpProgressBar` global registration (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/js/classes/VueRegistry.js` — `registerComponent()`, `init()` (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/file/TemporaryFileManager.php` — `handleUpload()` pattern (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/file/FileArchive.php` — `ZipArchive` usage in OPS (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/funding/FundingPlugin.php` — `addJavaScript()` plugin registration with priority (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/funding/vite.config.js` — IIFE Vite build pattern for plugins (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/plugins/generic/funding/resources/js/main.js` — `pkp.registry.registerComponent()` pattern (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/package.json` — full Vue 3/Pinia/Vite/dropzone-vue3 version matrix (HIGH confidence)
- `/root/codes/PKP/OPS/3_5/ops/lib/pkp/classes/install/Installer.php` — `set_time_limit(0)` precedent for long-running synchronous actions (HIGH confidence)

---

*Stack research for: OPS 3.5.x Import/Export Plugin — Web GUI additions*
*Researched: 2026-04-03*
