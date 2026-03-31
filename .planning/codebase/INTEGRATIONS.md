# External Integrations

**Analysis Date:** 2026-03-31

## APIs & External Services

**ORCID Validation:**
- Service: orcid.org / sandbox.orcid.org
- Purpose: Validate that ORCID identifiers exist via HTTP HEAD request
- Client: Guzzle HTTP (via `Application::get()->getHttpClient()`)
- Auth: None (public endpoint)
- Implementation: `classes/validations/InvalidRowValidations.php` method `validateOrcidExists()`
- Timeout: 5 seconds connect timeout
- Failure mode: Returns `false` on any exception; validation throws `RowValidationException` if ORCID not found
- Accepted formats: Full URL (`https://orcid.org/0000-0002-1825-0097`), bare ID (`0000-0002-1825-0097`), unhyphenated digits

**SMTP / Mail Transport:**
- Service: Configured mail transport in OPS (Symfony Mailer)
- Purpose: Send welcome emails to newly imported users
- Client: `Illuminate\Support\Facades\Mail` via `Symfony\Component\Mailer\Exception\TransportException`
- Implementation: `classes/handlers/WelcomeEmailHandler.php`
- Trigger: Only when `--sendWelcomeEmail` CLI flag is passed to user import
- Failure mode: Catches `TransportException`, logs error via `NotificationManager`, prints message to stdout

## Data Sources

**CSV Files (primary input):**
- Location: User-specified directory passed as CLI argument (`$sourceDir`)
- Format: Standard CSV parsed by `SplFileObject` with `READ_CSV` flag
- Security: Path traversal prevention via `InvalidRowValidations::validatePathWithinSourceDir()` in `classes/validations/InvalidRowValidations.php`
- Preprint CSV schema: 36 columns defined in `classes/validations/RequiredPreprintHeaders.php` (`$preprintHeaders`)
- User CSV schema: 11 columns defined in `classes/validations/RequiredUserHeaders.php` (`$userHeaders`)
- Invalid rows output: Written to `invalid_{filename}.csv` in the same source directory

**Galley/Supplementary Files:**
- Location: Same source directory as CSV files
- Types: PDF, DOC, and other document formats referenced by filename in CSV columns `galleyFilenames`, `suppFilenames`
- Processing: Uploaded via OPS file management system (`SubmissionFileProcessor` at `classes/processors/SubmissionFileProcessor.php`)

**Cover Images:**
- Location: Same source directory
- Allowed types: gif, jpg, png, webp (enforced in `classes/validations/InvalidRowValidations.php`, `$coverImageAllowedTypes`)
- Processing: Copied to OPS public files directory via `PublicFileManager` in `classes/processors/PublicationProcessor.php`

## Data Storage

**Databases:**
- Type: MySQL/MariaDB or PostgreSQL (configured by OPS host)
- Connection: Managed entirely by OPS/Laravel; no direct DSN in plugin
- Client: OPS `Repo` facade for all entity CRUD, Laravel `DB` facade for direct table inserts

**Direct DB Table Access (bypassing Repo facade):**
- `metrics_submission` table: Direct inserts via `DB::table()` in `classes/processors/StatisticsProcessor.php` for usage statistics (abstract views, galley views)
- `publication_settings` table: Direct queries via `DB::table()` in `classes/commands/PreprintCommand.php` for cover image synchronization across locales

**File Storage:**
- OPS managed file system for submission files and cover images
- No external blob storage (S3, etc.) unless configured at OPS level

**Caching:**
- In-process static property cache via `classes/cachedAttributes/CachedEntities.php`
- Caches: servers, genres, user groups, sections, categories
- Stores `null` for failed lookups to prevent repeated DB queries
- Naturally garbage-collected when CLI process exits

## Internal Integrations (with OPS Host Application)

**Plugin System:**
- Extends `PKP\plugins\ImportExportPlugin` base class
- Entry point: `CSVImportExportPlugin.php`
- Registration: OPS plugin registry, category `importexport`
- CLI invocation: `php tools/runScheduledTasks.php plugins.importexport.csv.CSVImportExportPlugin {command} {username} {sourceDir}`

**Repo Facade (primary OPS integration):**
- `Repo::submission()` - Create/delete submissions (`classes/processors/SubmissionProcessor.php`)
- `Repo::publication()` - Create/edit publications (`classes/processors/PublicationProcessor.php`)
- `Repo::author()` - Create/edit authors (`classes/processors/AuthorsProcessor.php`)
- `Repo::galley()` - Create galleys (`classes/processors/GalleyProcessor.php`)
- `Repo::submissionFile()` - Create/edit submission files (`classes/processors/SubmissionFileProcessor.php`)
- `Repo::user()` - Create/lookup users (`classes/processors/UsersProcessor.php`)
- `Repo::section()` - Create sections (`classes/processors/SectionsProcessor.php`)
- `Repo::emailTemplate()` - Fetch email templates for welcome emails (`classes/handlers/WelcomeEmailHandler.php`)

**DAO Layer (legacy access):**
- `PKP\db\DAORegistry` - Used for `ServerDAO` lookup in `classes/processors/PublicationProcessor.php`
- `FunderDAO` / `FunderAwardDAO` - Used via Funding plugin in `classes/processors/FundersProcessor.php`

**Funding Plugin (optional OPS plugin):**
- Plugin: `generic/funding` (FundingPlugin)
- Integration: `classes/processors/FundersProcessor.php` loads it via `PluginRegistry::loadPlugin()`
- Classes used: `Funder`, `FunderAward`, `FunderDAO`, `FunderAwardDAO`
- Graceful degradation: If plugin not installed, funder data in CSV is not processed

**Authentication:**
- User validation: `Repo::user()->getByUsername()` in `CSVImportExportPlugin.php`
- Password hashing: `PKP\security\Validation::encryptCredentials()` in `classes/processors/UsersProcessor.php` and `classes/commands/UserCommand.php`

**Notification System:**
- `APP\notification\NotificationManager` - Creates error notifications on email send failure in `classes/handlers/WelcomeEmailHandler.php`

## Webhooks & Callbacks

**Incoming:**
- None - Plugin is CLI-only, no HTTP endpoints

**Outgoing:**
- None - No webhook dispatching

## CI/CD & Deployment

**Hosting:**
- Deployed as a directory within OPS installation at `plugins/importexport/csv/`
- No standalone deployment pipeline

**CI Pipeline:**
- Claude Code review pipeline hooks at `.claude/hooks/review_pipeline.py` (4-phase automated review on stop events)
- No GitHub Actions or other CI configuration detected within the plugin

---

*Integration audit: 2026-03-31*
