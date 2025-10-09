# OPS CSV Import Plugin (CLI)

This plugin allows administrators to import users and preprints with their associated metadata in CSV format into OPS 3.4.X. This plugin operates exclusively via command-line interface (CLI).

## Table of Contents
- [Usage](#usage)
  - [Importing Users](#importing-users)
  - [Importing Preprints](#importing-preprints)
  - [Multi-Version Preprints](#multi-version-preprints)
- [CSV File Format](#csv-file-format)
  - [Users CSV Format](#users-csv-format)
  - [Preprints CSV Format](#preprints-csv-format)
- [Troubleshooting](#troubleshooting)


## Command Line Usage

### Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToCsvFile] [sendWelcomeEmail]
```

Parameters:
- `username`: The username of an administrator who will be associated with the import
- `pathToCsvFile`: Path to the CSV file containing user data. Can be absolute or relative to the OPS root directory.
- `sendWelcomeEmail`: (Optional) Set to `true` to send welcome emails to imported users

Example:
```bash
php tools/importExport.php CSVImportExportPlugin users admin /path/to/folder_with_csv_user_files true
```

### Importing Preprints

To import preprints from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin preprints [username] [pathToCsvFile]
```

Parameters:
- `username`: The username of an administrator who will be associated with the import
- `pathToCsvFile`: Path to the CSV file containing preprint data. Can be absolute or relative to the OPS root directory.

Example:
```bash
php tools/importExport.php CSVImportExportPlugin preprints admin /path/to/folder_with_csv_preprint_files
```

### Multi-Version Preprints

The plugin supports importing multiple versions of the same preprint. This allows you to track the evolution of a preprint over time with different versions.

#### How Multi-Version Import Works:

1. **Version Identification**: Use `versionIdentifier` and `version` columns to link versions of the same preprint
2. **First Version**: Must include all required fields (serverPath, locale, preprintTitle, authors, datePosted)
3. **Subsequent Versions** (version > 1): Only require `versionIdentifier` and `version` fields
4. **Data Cloning**: When creating version 2+, the system automatically clones all data from the previous version
5. **Selective Updates**: Only the fields you fill in the CSV will be updated; empty fields retain values from the previous version

### Important Notes:
- The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
- The script must be executed from the OPS installation directory
- Ensure you have proper permissions to execute PHP scripts and access the files
- On single-version preprints should leave `versionIdentifier` and `version` columns are optional.

## CSV File Format

## Data Structure Reference

### Authors Format

The `authors` field in the issues CSV must contain author information in the following format:

```
GivenName,FamilyName,Email,Affiliation;GivenName2,FamilyName2,Email2,Affiliation2
```

- Fields are separated by commas within each author
- Multiple authors are separated by semicolons
- All fields except GivenName are optional and can be left empty
- If email is empty, the primary contact email will be used

Examples:
```
"John,Doe,john@example.com,University of Example; Jane,Smith,,Another University"
"Maria,Silva,maria@example.com,"
"Carlos,,carlos@example.com,Example Corp"
```

### Keywords, Subjects, and Categories

These fields use a simple semicolon-separated format:

- **Keywords**: `keyword1; keyword two; another keyword`
- **Subjects**: `subject1; subject two; another subject`
- **Categories**: `Category1; Category Two; Another Category`

Notes:
- Leading/trailing spaces are automatically trimmed
- Empty values are ignored
- Categories will be created if they don't exist

### User Interests

User interests in the users CSV use a semicolon-separated format:

```
interest one; interest two; another interest
```

- Leading/trailing spaces are automatically trimmed
- Empty values are ignored
- Each interest will be associated with the user's profile

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

**Notes:**
- *Required for first version or single-version preprints
- **For version > 1: Only `versionIdentifier` and `version` are required; all other fields are optional and will be cloned from the previous version if left empty

### Example: Users CSV

You can take a look at the example we provide on the [User CSV file](./examples/users/users_example.csv).

### Example: Preprints CSV (Single Version)

You can take a look at the example we provide on the [Single Version Preprints CSV file](./examples/preprints/single_version_preprints.csv).

### Example: Preprints CSV (Multi-Version)

You can take a look at the example we provide on the [Multi Version Preprints CSV file](./examples/preprints/multiversion_preprints.csv).

### Example: Preprints CSV (Multi-Version)

You can take a look at the example we provide on the [Mixed Preprints CSV file](./examples/preprints/mixed_preprints.csv).

## File Structure for Import

When importing preprints, the following file structure is recommended:

```
import_directory/
├── users.csv
├── preprints.csv
├── paper.pdf
├── paper_v2.pdf
├── paper_v3.pdf
├── presentation.pptx
├── supplement.pdf
├── data.xlsx
├── supplementary_data.csv
├── cover_v1.png
├── cover_v2.png
```

### Multi-Version File Naming Convention

For multi-version preprints, it's recommended to include version indicators in filenames:
- `paper_v1.pdf`, `paper_v2.pdf`, `paper_v3.pdf`
- `cover_v1.png`, `cover_v2.png`
- `supplement_v1.pdf`, `supplement_v2.pdf`

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

#### General Troubleshooting Tips
- Always back up your database before running imports
- Test with a small CSV file first (2-3 preprints)
- Check the OPS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- When importing multi-version preprints, start with a simple 2-version example to verify the workflow
- Check the `invalid_[filename].csv` file generated after import for any failed rows
