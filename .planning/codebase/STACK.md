# Technology Stack

**Analysis Date:** 2026-03-31

## Languages

**Primary:**
- PHP ^8.2 (runtime 8.3.30 on dev machine) - All plugin source code

**Secondary:**
- Python 3.6+ - Claude Code review pipeline hooks (`.claude/hooks/`)

## Runtime

**Environment:**
- PHP CLI - Plugin runs exclusively via command line (`php tools/runScheduledTasks.php`)
- No web UI or HTTP endpoints within the plugin itself

**Package Manager:**
- Composer (managed at OPS level: `/root/codes/PKP/OPS/3_5/ops/lib/pkp/composer.json`)
- Plugin has no `composer.json` of its own; it relies on the host OPS application's vendor dependencies
- Lockfile: managed by OPS host, vendor at `lib/pkp/lib/vendor/`

## Frameworks

**Core:**
- OPS (Open Preprint Systems) 3.5.0 - Host application providing `Repo` facade, DAOs, services, CLI entry point
- Laravel 11.x (via OPS) - Provides `Illuminate\Support\Facades\DB`, `Illuminate\Support\Facades\Mail`, Eloquent underpinnings

**Testing:**
- PHPUnit 11.5.11 - Test runner, config at `phpunit.xml`
- Mockery 1.6.12 - Mock object generation alongside PHPUnit MockObject

**Build/Dev:**
- Xdebug - Code coverage reporting (requires `XDEBUG_MODE=coverage`)
- No build step - Plugin is pure PHP, no compilation or transpilation

## Key Dependencies

**Critical (from OPS host, used directly by plugin):**
- `guzzlehttp/guzzle` 7.9.2 - ORCID validation via HTTP HEAD requests to `orcid.org` / `sandbox.orcid.org` (accessed via `Application::get()->getHttpClient()` in `classes/validations/InvalidRowValidations.php`)
- `laravel/framework` ^11.0 - `DB` facade for direct metrics inserts (`classes/processors/StatisticsProcessor.php`, `classes/commands/PreprintCommand.php`), `Mail` facade for welcome emails (`classes/handlers/WelcomeEmailHandler.php`)
- `symfony/mailer` ^7.0 - Underlying mail transport, `TransportException` caught in `classes/handlers/WelcomeEmailHandler.php`
- `phpmailer/phpmailer` 6.x - OPS email infrastructure

**Infrastructure (OPS facades used throughout):**
- `APP\facades\Repo` - All database CRUD operations (submissions, publications, authors, users, galleys, sections, categories, email templates)
- `PKP\db\DAORegistry` - Legacy DAO access for `ServerDAO` in `classes/processors/PublicationProcessor.php`
- `PKP\plugins\PluginRegistry` - Loading the Funding plugin in `classes/processors/FundersProcessor.php`

**PHP Native (no external library):**
- `SplFileObject` - CSV parsing and writing (`classes/handlers/CsvFileHandler.php`)
- `PKP\file\FileManager` / `APP\file\PublicFileManager` - File system operations for cover images and galley files

## Configuration

**Environment:**
- No `.env` file within the plugin; all configuration inherited from OPS host application
- Plugin registered via OPS plugin system (`ImportExportPlugin` base class)
- Locale data: `locale/en/` directory with message translations

**Build:**
- `phpunit.xml` - PHPUnit configuration, bootstraps via `lib/pkp/tests/phpunit-bootstrap.php`
- Coverage output: `tests/coverage/html/`, `tests/coverage/coverage.txt`, `tests/coverage/clover.xml`

## Platform Requirements

**Development:**
- PHP 8.2+ with extensions: bcmath, gd, intl, mbstring, xml, zip
- OPS 3.5.0 fully installed and configured (the plugin lives inside OPS's plugin directory)
- PHPUnit binary at `lib/pkp/lib/vendor/bin/phpunit` (relative to OPS root)

**Production:**
- OPS 3.5.0 installation with CLI access
- Database backend (MySQL/MariaDB or PostgreSQL, managed by OPS)
- Network access to `orcid.org` / `sandbox.orcid.org` for ORCID validation (optional, gracefully fails)
- SMTP or mail transport configured in OPS for welcome emails (optional)

## External Plugin Dependencies

**Funding Plugin (optional):**
- `FundersProcessor` (`classes/processors/FundersProcessor.php`) depends on OPS Funding plugin classes: `APP\plugins\generic\funding\classes\Funder`, `FunderAward`, `FunderDAO`, `FunderAwardDAO`
- Loaded dynamically via `PluginRegistry::loadPlugin('generic', 'funding', $contextId)`
- If not installed, funder processing is skipped

---

*Stack analysis: 2026-03-31*
