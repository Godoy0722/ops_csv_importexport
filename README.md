# OPS CSV Import Plugin (CLI)

This plugin allows administrators to import users and preprints with their associated metadata in CSV format into OPS 3.5.X. This plugin operates exclusively via command-line interface (CLI).

## Table of Contents
- [OPS CSV Import Plugin (CLI)](#ops-csv-import-plugin-cli)
  - [Table of Contents](#table-of-contents)
  - [CLI Usage](#cli-usage)
    - [Importing Users](#importing-users)
    - [Importing Preprints](#importing-preprints)
  - [CSV General Rules](#csv-general-rules)
    - [Users CSV Format](#users-csv-format)
      - [Users CSV Example](#users-csv-example)
    - [Preprints CSV Format](#preprints-csv-format)
      - [Preprints CSV Example](#preprints-csv-example)
      - [Import File Structure](#import-file-structure)
  - [Preprint Versions](#preprint-versions)
    - [How It Works](#how-it-works)
    - [Version Management Rules](#version-management-rules)
    - [Practical Examples](#practical-examples)
      - [Example 1: Single Preprint Without Versions](#example-1-single-preprint-without-versions)
      - [Example 2: Multi Version Preprints](#example-2-multi-version-preprints)
      - [Example 3: Mixed Preprints](#example-3-mixed-preprints)
    - [Important Notes](#important-notes)
  - [Multi-Locale Support](#multi-locale-support)
    - [How Multi-Locale Works](#how-multi-locale-works)
    - [Multi-Locale Management Rules](#multi-locale-management-rules)
    - [Multi-Locale Best Practices](#multi-locale-best-practices)
    - [Important Multi-Locale Notes](#important-multi-locale-notes)
    - [ORCiD in Multi-Locale and Multi-Version](#orcid-in-multi-locale-and-multi-version)
  - [Troubleshooting](#troubleshooting)
    - [Common Issues and Solutions](#common-issues-and-solutions)
      - [File and Path Issues](#file-and-path-issues)
      - [CSV Format Issues](#csv-format-issues)
      - [User Import Issues](#user-import-issues)
      - [Preprint Import Issues](#preprint-import-issues)
      - [General Troubleshooting Tips](#general-troubleshooting-tips)


## CLI Usage

### Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToFolderWithCsvFiles] [sendWelcomeEmail]
```

Parameters:
- `username`: The username of a valid Preprint Manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the CSV file containing user data. Can be absolute or relative to the OPS root directory.
- `sendWelcomeEmail`: (Optional) Set to `true` to send welcome emails to imported users. If set to true, the sender email will be the user retrieved by the username on the CLI command.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin users admin /path/to/folder_with_csv_user_files true
```

### Importing Preprints

To import preprints from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin preprints [username] [pathToFolderWithCsvFiles]
```

Parameters:
- `username`: The username of an administrator who will be associated with the import. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the CSV file containing preprint data. Can be absolute or relative to the OPS root directory.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin preprints admin /path/to/folder_with_csv_preprint_files
```

> **Notes**:
>
>  - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Preprint Manager and editor user accounts.
>  - The last CLI attribute must be the path to the CSV file, and not directly the CSV file itself.
>  - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
>  - The script must be executed from the OPS installation directory
>  - Ensure you have proper permissions to execute PHP scripts and access the files

## CSV General Rules

### Users CSV Format

| Column | Required | Description | Example |
|--------|----------|-------------|---------|
| serverPath | Yes | Path of the server | leo |
| firstname | Yes | User's first name | Homer |
| lastname | Yes | User's last name | Simpson |
| email | Yes | User's email address | homer@example.com |
| affiliation | No | User's affiliation | University of British Columbia |
| country | No | Two-letter country code | CA |
| username | Yes | Username for login | hsimpson |
| tempPassword | Yes | Temporary password | temppassword123 |
| roles | No | Semicolon-separated list of roles | Reader;Author |
| reviewInterests | No | Semicolon-separated interests | interest one;interest two |
| subscriptionType | No | Subscription type ID | 1 |
| start_date | If subscriptionType is set | Subscription start date (YYYY-MM-DD) | 2023-01-01 |
| end_date | If subscriptionType is set | Subscription end date (YYYY-MM-DD) | 2023-12-31 |

> **User Interests:** User interests in the users CSV use a semicolon-separated format:
>
> ```
> interest one; interest two; another interest
> ```
>
>  - Leading/trailing spaces are automatically trimmed
>  - Empty values are ignored
>  - Each interest will be associated with the created user's profile
>

#### Users CSV Example

You can take a look at the example we provide on the [User CSV file](./examples/users/users_example.csv).

### Preprints CSV Format

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| serverPath | Yes* | Path of the target server | liv | Must exist in the system |
| locale | Yes* | Preprint locale | en | Must be enabled in the server |
| versionIdentifier | No** | Unique identifier for versioning | ML-CLIMATE-2024 | Required for multi-version preprints |
| version | No** | Version number | 1 | Required for multi-version preprints |
| preprintPrefix | No | Preprint prefix | ML | Optional abbreviation |
| preprintTitle | Yes* | Preprint title | My Research Preprint | |
| preprintSubtitle | No | Preprint subtitle | A Study of... | Optional |
| preprintAbstract | Yes* | Preprint abstract | This paper examines... | |
| authors | Yes* | Author information | See [Authors Format](#authors-format) | |
| keywords | No | Semicolon-separated keywords | science;research | Optional |
| subjects | No | Semicolon-separated subjects | Biology;Ecology | Optional |
| coverage | No | Coverage information | Global study | Optional |
| categories | No | Semicolon-separated categories | Research Article | Will be created if needed |
| doi | No | Digital Object Identifier | 10.1234/abc123 | Optional |
| coverImageFilename | No | Cover image filename | cover.png | Must be in same directory |
| coverImageAltText | No | Alt text for cover | Preprint Cover | Optional |
| galleyFilenames | No | Semicolon-separated galley files | paper.pdf;slides.pptx | Optional |
| galleyLabels | No | Labels for galleys | PDF;SLIDES | Must match galleyFilenames count |
| suppFilenames | No | Semicolon-separated supplementary files | supplement.pdf;data.csv | Optional |
| suppLabels | No | Labels for supplementary files | Supplement;Dataset | Must match suppFilenames count |
| sectionTitle | No | Section name | Preprints | Will be created if needed |
| sectionAbbrev | No | Section abbreviation | PRE | Used if section is created |
| datePosted | Yes* | Posting date | 2024-01-15 | Format: YYYY-MM-DD |
| dateSubmitted | No | Submission date | 2024-01-10 | Format: YYYY-MM-DD, optional |
| copyrightYear | No | Copyright year | 2024 | Defaults to system setting if not provided |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | Defaults to system setting if not provided |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | Defaults to system setting if not provided |
| references | No | Path to references file (.txt) | references.txt | Optional file containing article references |

> **Notes:**
>  - *Required for first version or single-version preprints
>  - **For version > 1: Only `versionIdentifier` and `version` are required; all other fields are optional and will be cloned from the previous version if left empty

> **Authors Format**
> The `authors` field in the preprints CSV must contain author information in the following format:
>
> ```
> GivenName,FamilyName,Email,ORCiD,Affiliation;GivenName2,FamilyName2,Email2,ORCiD2,Affiliation2
> ```
>
>  - Fields are separated by commas within each author
>  - Multiple authors are separated by semicolons
>  - All fields except `GivenName` are optional and can be left empty
>  - If `Email` is empty, the primary contact email of the server will be used
>  - `ORCiD` must be the author identifier and is optional; see input options below
>
> Examples:
>
> ```
> "John,Doe,john@example.com,0000-0002-1825-0097,University of Example; Jane,Smith,,https://orcid.org/0000-0002-1694-233X,Another University"
> "Maria,Silva,maria@example.com,0000000218250097,"
> "Carlos,,carlos@example.com,,Example Corp"
> ```

> **ORCiD Input Options**
> You may provide the ORCiD in any of the following forms:
>  - Full URL: `https://orcid.org/0000-0002-1825-0097`
>  - Hyphenated ID: `0000-0002-1825-0097`
>  - Digits only: `0000000218250097`
>
> Notes:
>  - The system normalizes the value to the canonical URL form `https://orcid.org/0000-0000-0000-0000`
>  - The last character may be `X` (checksum), e.g., `0000-0002-1694-233X`
>  - Invalid formats are ignored without blocking the import

> **Keywords, Subjects, and Categories**
> These fields use a simple semicolon-separated format:
>  - **Keywords**: `keyword1; keyword two; another keyword`
>  - **Subjects**: `subject1; subject two; another subject`
>  - **Categories**: `Category1; Category Two; Another Category`
>
> Notes:
>  - Leading/trailing spaces are automatically trimmed
>  - Empty values are ignored
>  - Categories will be created if they don't exist

> **References File**
> The `references` field in the issues CSV can contain the path to a TXT file with article references:
>  - The file must be in TXT format
>  - The file must be located in the same directory as the CSV file
>  - Each reference should be on a separate line or separated by a blank line
>  - The content of the file will be imported as the publication's citations
>
> Example references.txt content:
> ```
> Smith, J. (2023). "The Impact of Technology on Modern Publishing", Journal of Academic Publishing, 45(2), 123-145.
>
> Johnson, M., & Williams, K. (2022). Digital Transformation in Scholarly Communication. Academic Press.
>
> Brown, A. et al. (2021). "Open Access and the Future of Research Dissemination", International Journal of Scholarly Research, 12(4), 567-589.
> ```

#### Preprints CSV Example

You can take a look at the example we provide on the [Preprint CSV file](./examples/preprints/preprints_example.csv).

#### Import File Structure

When importing preprints, it's important to keep all preprint assets in the same directory as the CSV file, so you just need to pass the asset names instead of a path for the asset. Here's an example of the recommended structure:

```
import_directory/
├── preprints.csv
├── preprint.pdf
├── preprint2.pdf
├── presentation.pptx
├── supplement.pdf
├── data.xlsx
├── supplementary_data.csv
├── cover.jpg
```

## Preprint Versions

The plugin supports importing multiple versions of the same preprint. This allows you to track the evolution of a preprint over time with different versions.

### How It Works

The multiversion system uses two key fields to manage preprint versions:

- **versionIdentifier**: A unique string that links multiple versions of the same preprint together
- **version**: A positive integer indicating the version number (1, 2, 3, etc.)

When you provide these fields in your CSV:
1. Preprints with the same `versionIdentifier` are treated as different versions of the same submission
2. Each version can have updated content, metadata, or files
3. The system automatically sets the highest version number as the current published version
4. All versions remain accessible in the system's version history

### Version Management Rules

1. **Version Identifiers**:
   - Can be any unique string (e.g., "preprint-001", "ml-paper-2024", "climate-study")
   - Leave empty for single-version preprints
   - Must be unique across different preprints (don't reuse identifiers)

2. **Version Numbers**:
   - Must be positive integers (1, 2, 3, ...)
   - Required when `versionIdentifier` is provided
   - Must be unique for each version of the same preprint
   - Version 1 is always the initial/base version

3. **Required Fields**:
   - **Version 1** must include ALL required fields: `serverPath`, `locale`, `preprintTitle`, `authors`, `datePosted`
   - **Versions > 1** only require `versionIdentifier`, `version`, and `locale` (you can include other fields you want to update)
   - Fields not provided in higher versions inherit values from the previous version

4. **Automatic Current Version**:
   - After import, the system automatically sets the highest version as "current"
   - All other versions remain in the system as historical versions
   - Readers will see the highest version by default

### Practical Examples

#### Example 1: Single Preprint Without Versions

For preprints that don't need version tracking, simply leave `versionIdentifier` and `version` empty. You can take a look at the [single version CSV file](./examples/preprints/single_version_preprints.csv).


#### Example 2: Multi Version Preprints

For preprint with multiple versions, you'll need to set the `versionIdentifier` and `version` fields. The `versionIdentifier` tracks the same preprint and the `version` handles with the preprint different verisons. See [multi version CSV file](./examples/preprints/multiversion_preprints.csv) example.

#### Example 3: Mixed Preprints

You can mix single-version and multi-version preprints in the same CSV file. Take a look at [the default CSV file](./examples/preprints/preprints_example.csv).

### Important Notes

- All versions of a preprint share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Readers can access previous versions through the preprint's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

## Multi-Locale Support

The plugin supports importing preprints with content in multiple languages. This allows you to provide translations of your preprints by importing the same preprint in different locales.

### How Multi-Locale Works

The multi-locale system uses three key fields to manage preprint translations:

- **versionIdentifier**: Links all versions of a preprint together
- **version**: Indicates the version number
- **locale**: Specifies the language/locale of the content (e.g., `en`, `pt_BR`, `fr_CA`)

When you provide multiple CSV rows with:
- Same `versionIdentifier`
- Same `version`
- Different `locale`

The system will:
1. Detect that you're adding a translation to an existing publication
2. Update the existing publication with the new locale data
3. Preserve all existing data in other locales

### Multi-Locale Management Rules

1. **Locale Codes**:
   - Must match locales enabled in your server settings
   - Common examples: `en` (English), `pt_BR` (Brazilian Portuguese), `fr_CA` (Canadian French)
   - Must be validated by the server before import

2. **Required Fields for Multi-Locale**:
   - First locale import (base): Requires ALL mandatory fields (`serverPath`, `locale`, `preprintTitle`, `authors`, `datePosted`)
   - Additional locale imports: Only require `versionIdentifier`, `version`, and `locale` (you can include other fields you want to translate)
   - Fields not provided will remain empty for that locale (they won't inherit from other locales), with the exception of the coverImage, which if not passed on a second locale but present on the first one, will inherit it from the first one.

3. **Localized Fields**:
   The following fields support multi-locale data:
   - `preprintTitle`
   - `preprintSubtitle`
   - `preprintAbstract`
   - `preprintPrefix`
   - `coverage`
   - `copyrightHolder`
   - `keywords`
   - `subjects`
   - `categories` (category titles)
   - Author names (`givenName`, `familyName`)
   - Author affiliations

4. **Non-Localized Fields**:
   These fields are shared across all locales:
   - `copyrightYear`
   - `licenseUrl`
   - `doi`
   - `datePosted`
   - `dateSubmitted`
   - File attachments (galleys and supplementary files)

### Multi-Locale Best Practices

1. **Import Order**:
   - Always import the primary/default locale first
   - Then add additional locales in subsequent rows
   - You can import all locales in a single CSV file

2. **Consistency**:
   - Keep `versionIdentifier` and `version` consistent across locales

4. **Validation**:
   - The system validates that `identifier` + `version` + `locale` is unique
   - Duplicate combinations will be rejected with error message
   - Check the `invalid_[filename].csv` file for any failed rows

### Important Multi-Locale Notes

- All locales for a version share the same publication ID
- Readers can switch between available locales in the frontend
- Categories can have different titles per locale
- Author names and affiliations can be provided in multiple locales
- Keywords and subjects are stored per locale
- Non-localized fields (DOI, dates, etc.) remain the same across all locales
- Files (galleys, supplementary) are shared across all locales

### ORCiD in Multi-Locale and Multi-Version

- Multi-Locale: ORCiD is non-localized. When importing another locale for the same version, if an ORCiD is provided in that row, it updates the existing author matched by email. If omitted, the existing value is preserved.
- Multi-Version: If the `authors` field is empty for a new version, authors (including ORCiD) are cloned from the previous version. If authors are provided, the ORCiD is read per author (as above) and saved for that version.

## Troubleshooting

### Common Issues and Solutions

#### File and Path Issues
1. **File Not Found**
   - Error: `Could not read file: [file]. Error: [error]`
   - Solution:
     - Verify the file exists and the path is correct
     - Use absolute paths for reliability
     - For relative paths, they are resolved from the OPS root directory
     - Check file permissions (must be readable by the user running the CLI script)
     - Ensure the file is not empty

2. **Invalid Source Directory**
   - Error: `Invalid source dir: [dir]`
   - Solution:
     - Verify the directory exists and is accessible
     - Check for typos in the path
     - Ensure the web server user has read permissions

#### CSV Format Issues
3. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields` or `Verify the required fields for this row`
   - Solution:
     - Check that all required columns are present in the CSV header
     - Ensure all rows have the same number of fields as the header
     - Verify there are no empty lines in the CSV file
     - Check for proper CSV escaping of fields containing commas or quotes

4. **Invalid Date Formats**
   - Error: `The subscription start/end date is not valid. Format required: YYYY-MM-DD`
   - Solution:
     - Ensure all dates are in YYYY-MM-DD format
     - Verify dates are valid (e.g., no February 30)
     - Check that end dates are after start dates

#### User Import Issues
5. **User Already Exists**
   - Error: `User already exists with email/username [value]`
   - Solution:
     - Update existing users instead of creating new ones
     - Ensure usernames and emails are unique across the system
     - Check for case sensitivity in usernames/emails

6. **Role or Subscription Issues**
   - Error: `Role "[role]" doesn't exist` or `Invalid subscription type with ID [id]`
   - Solution:
     - Verify role names exactly match those in the system
     - Check that subscription type IDs exist in the database
     - Ensure required subscription fields (start_date, end_date) are provided

#### Preprint Import Issues
7. **Server or Locale Issues**
   - Error: `Unknown server with path [path]` or `Unknown locale [locale]`
   - Solution:
     - Verify the server path in the CSV matches exactly
     - Check that the specified locale is enabled in the server
     - Ensure the server exists and is accessible to the importing user

8. **File Validation Errors**
   - Error: `Invalid [preprint/cover/galley] file for this submission`
   - Solution:
     - Verify all referenced files exist in the specified location
     - Check file permissions and formats
     - Ensure cover images are in a supported format (JPG, PNG, GIF, WEBP)
     - Verify galley files match the specified labels

9. **Author and Metadata Issues**
   - Error: `There is no default author group in the server`
   - Solution:
     - Ensure the server has at least one author group configured
     - Verify author information follows the required format
     - Check that required author fields (given name) are provided

10. **Multi-Version Import Issues**
    - Error: `Version is required when versionIdentifier is provided`
    - Solution:
      - Ensure both `versionIdentifier` and `version` are filled when using versioning
      - Verify version numbers are positive integers (1, 2, 3, etc.)

    - Error: `Duplicate preprint version found`
    - Solution:
      - Check that you don't have duplicate version numbers for the same `versionIdentifier`
      - Ensure each version number is unique within the same preprint identifier

    - **Best Practices for Multi-Version Imports:**
      - Always import versions in sequential order (1, 2, 3...)
      - Keep all versions of the same preprint in the same CSV file
      - For version 2+, you can leave most fields empty to clone from previous version
      - Only fill in the fields you want to update in newer versions

11. **Multi-Locale Import Issues**
    - Error: `Unknown locale or locale not supported by this server: [locale]`
    - Solution:
      - Verify the locale is enabled in your server settings
      - Check that the locale code is correct (e.g., `en`, `pt_BR`, not `pt-BR`)
      - Enable required locales in the server configuration before importing

    - Error: `Duplicate preprint version and locale found`
    - Solution:
      - Check that you don't have duplicate rows with same `versionIdentifier`, `version`, AND `locale`
      - Each combination of identifier + version + locale must be unique
      - Review your CSV for accidental duplicate rows

    - **Best Practices for Multi-Locale Imports:**
      - Import the primary/default locale first
      - Ensure all locales are enabled in server settings before importing
      - Keep author email addresses consistent across locales for proper matching
      - Translate all user-facing fields (title, abstract, keywords, etc.)
      - Remember that files (galleys) are shared across all locales
      - Test with a simple two-locale example before large imports

12. **References File Issues**
   - Error: `Invalid references file: [filename]`
   - Solution:
     - Verify the references file exists in the same directory as the CSV file
     - Check file permissions (must be readable by the user running the CLI script)
     - Ensure the filename is spelled correctly in the CSV

   - Error: `Invalid references file extension`
   - Solution:
     - Ensure the references file has a .txt extension
     - References files must be in plain text format

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first (2-3 preprints)
- Check the OPS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- When importing multi-version preprints, start with a simple 2-version example to verify the workflow
- Check the `invalid_[filename].csv` file generated after import for any failed rows
