# Troubleshooting

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)

## Handling Failed Imports and Re-runs

When an import encounters errors, the plugin automatically creates `invalid_*.csv` files containing the failed rows with error messages. Understanding how to handle these files is crucial for successful re-imports.

### How Invalid Files Work

1. **Automatic Creation**: When the import script encounters validation errors, failed rows are automatically written to `invalid_[original_filename].csv` in the same directory
2. **Error Column**: Each `invalid_*.csv` file includes all original columns plus an `error` column explaining why the row failed
3. **Automatic Deletion**: If all rows import successfully (zero failures), the invalid file is automatically deleted
4. **Automatic Skip**: On re-runs, the plugin automatically skips files starting with `invalid_` to prevent accidental re-import

### Re-importing Failed Rows

**Recommended Workflow:**

```bash
# Initial import
php tools/importExport.php CSVImportExportPlugin preprints admin /path/to/import_folder/

# Output shows:
# Process for file "preprints.csv" finished. 50 rows processed. 3 rows with error.
# => Creates: /path/to/import_folder/invalid_preprints.csv
```

**Option 1: Fix and Create New Import Folder (Recommended)**

```bash
# 1. Create a new folder for the retry
mkdir /path/to/import_retry/

# 2. Copy the invalid file to the new folder and rename it
cp /path/to/import_folder/invalid_preprints.csv /path/to/import_retry/preprints_retry.csv

# 3. Fix the errors in the new file:
#    - Open preprints_retry.csv
#    - Fix the issues described in the 'error' column
#    - Remove the 'error' column before re-importing
#    - Copy all the assets present on the rows that you will run again to the new folder

# 4. Run the import on the new folder
php tools/importExport.php CSVImportExportPlugin preprints admin /path/to/import_retry/
```

**Option 2: Fix In Place**

```bash
# 1. Rename the invalid file (removes 'invalid_' prefix)
mv /path/to/import_folder/invalid_preprints.csv /path/to/import_folder/preprints_fixed.csv

# 2. Remove or rename the original file to avoid duplicates
mv /path/to/import_folder/preprints.csv /path/to/import_folder/preprints_completed.csv

# 3. Fix errors in preprints_fixed.csv (remove the 'error' column)

# 4. Re-run the import (will only process preprints_fixed.csv)
php tools/importExport.php CSVImportExportPlugin preprints admin /path/to/import_folder/
```

### Avoiding Duplicates

- The plugin **does not** check for duplicate submissions across re-runs
- If you re-import successfully imported rows, they will be created again
- Always ensure you only re-import the fixed rows from `invalid_*.csv` files
- Move or rename successfully imported CSV files before re-running

### Best Practices

1. **Keep originals**: Archive successfully imported files before re-runs
2. **Separate directories**: Use different folders for original imports and retries
3. **Remove error column**: Always remove the `error` column from fixed `invalid_*.csv` files before re-importing
4. **Incremental approach**: Fix and re-import failed rows in small batches
5. **Backup database**: Always backup your database before large imports

## Common Issues and Solutions

### File and Path Issues

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
     - Ensure the user running the CLI command has read permissions

### CSV Format Issues

3. **Missing or Invalid Fields**
   - Error: `Row doesn't contain all fields` or `Verify the required fields for this row`
   - Solution:
     - Check that all required columns are present in the CSV header
     - Ensure all rows have the same number of fields as the header
     - Verify there are no empty lines in the CSV file
     - Check for proper CSV escaping of fields containing commas or quotes

4. **Invalid Date Formats**
   - Error: `The date is not valid. Format required: YYYY-MM-DD`
   - Solution:
     - Ensure all dates are in YYYY-MM-DD format
     - Verify dates are valid (e.g., no February 30)

### User Import Issues

5. **User Already Exists**
   - Error: `User already exists with email/username [value]`
   - Solution:
     - Update existing users instead of creating new ones
     - Ensure usernames and emails are unique across the system
     - Check for case sensitivity in usernames/emails

6. **Role Issues**
   - Error: `Role "[role]" doesn't exist`
   - Solution:
     - Verify role names exactly match those in the system

7. **ORCID Issues**
   - Error: `Invalid ORCID format: [orcid]`
   - Solution:
     - Ensure the ORCID uses one of the accepted formats: full URL, dashed, or numeric
     - Check that the ORCID has exactly 16 digits (plus dashes or URL prefix)
     - Verify there are no extra spaces or characters

   - Error: `Invalid ORCID checksum for: [orcid]`
   - Solution:
     - The ORCID checksum validation failed, meaning the ORCID is malformed
     - Double-check the ORCID against the official ORCID record

### Preprint Import Issues

8. **Server or Locale Issues**
   - Error: `Unknown server with path [path]` or `Unknown locale [locale]`
   - Solution:
     - Verify the server path in the CSV matches exactly
     - Check that the specified locale is enabled in the server
     - Ensure the server exists and is accessible to the importing user

9. **File Validation Errors**
   - Error: `Invalid [preprint/cover/galley] file for this submission`
   - Solution:
     - Verify all referenced files exist in the specified location
     - Check file permissions and formats
     - Ensure cover images are in a supported format (JPG, PNG, GIF, WEBP)
     - Verify galley files match the specified labels

10. **Author and Metadata Issues**
    - Error: `There is no default author group in the server`
    - Solution:
      - Ensure the server has at least one author group configured
      - Verify author information follows the required format
      - Check that required author fields (given name) are provided

    - Error: `Both sectionTitle and sectionAbbrev must be provided together, or both left empty.`
    - Solution: Either fill both `sectionTitle` and `sectionAbbrev`, or leave both empty

11. **Multi-Version Import Issues**
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

12. **Multi-Locale Import Issues**
    - Error: `Unknown locale or locale not supported by this server: [locale]`
    - Solution:
      - Verify the locale is enabled in your server settings
      - Check that the locale code is correct (e.g., `en`, `pt_BR`, not `pt-BR`)
      - Enable required locales in the server configuration before importing

    - Error: `Duplicate preprint version and locale found`
    - Solution:
      - Check for duplicate rows with same `versionIdentifier`, `version`, AND `locale`
      - Each combination of identifier + version + locale must be unique

    - **Best Practices for Multi-Locale Imports:**
      - Import the primary/default locale first
      - Ensure all locales are enabled in server settings before importing
      - Keep author email addresses consistent across locales for proper matching
      - Translate all user-facing fields (title, abstract, keywords, etc.)
      - Files (galleys) are shared across all locales
      - Test with a simple two-locale example before large imports

13. **References File Issues**
    - Error: `Invalid references file: [filename]` or `Invalid references file extension`
    - Solution:
      - Verify the references file exists in the same directory as the CSV file
      - Check file permissions
      - Ensure the file has a `.txt` extension and is in plain text format

14. **VOR DOI Issues**
    - Error: `Invalid VOR DOI format: [vorDoi]`
    - Solution:
      - Ensure the VOR DOI uses one of the accepted formats:
        - Full URL: `https://doi.org/10.1234/example`
        - DOI identifier: `10.1234/example`
        - With prefix: `doi:10.1234/example`
      - The DOI must start with `10.` followed by a registrant code (4+ digits)

15. **Associated User Issues**
    - Notice: `Username "[username]" not found. Submission ID [id] was added using default user "[defaultUsername]".`
    - This is a notice, not an error — the import will continue
    - Solution if you want to use the specific user:
      - Verify the username exists in OPS
      - Check for typos in the username
      - Ensure the user account is not deleted (disabled users are still matched)

16. **Funders Issues**
    - Error: `The Funding plugin is not installed or not enabled for this server.`
    - Solution: Install/enable the Funding plugin from [https://github.com/ajnyga/funding](https://github.com/ajnyga/funding)

    - Error: `Funder "[name]" at position [n] is not from the Crossref Funder Registry.`
    - Solution:
      - Provide a valid Crossref Funder Registry DOI (e.g., `http://dx.doi.org/10.13039/100000001`)
      - Search at [https://search.crossref.org/search/funders](https://search.crossref.org/search/funders)
      - Or disable "Enable Grant ID Validation" in the Funding plugin settings

    - Error: `Invalid funder format at position [n].`
    - Solution:
      - Each funder must have at least a name (first field)
      - Use correct separators: `;` between funders, `,` between fields, `|` between awards

### Statistics Import Issues

17. **Preprint Views Issues**
    - Error: `Invalid preprintViews value`
    - Solution:
      - Ensure the value is a positive integer (no decimals, no negative numbers)
      - Leave empty if you don't want to import views

18. **Galley Views Issues**
    - Error: `Galley views provided without galley files`
    - Solution: The `galleyViews` column requires `galleyFilenames` to also be provided

    - Error: `Number of galley views does not match number of galley files`
    - Solution: Ensure `galleyViews` has the same number of entries as `galleyFilenames` and `galleyLabels` (use empty values like `150;;42` to skip galleys)

### General Troubleshooting Tips

- Always back up your database before running imports
- Test with a small CSV file first (2-3 preprints)
- Check the OPS error log for detailed error messages
- Ensure your CSV file is saved with UTF-8 encoding
- On Linux systems, check file permissions with `ls -l` and adjust with `chmod` if needed
- For large imports, monitor server resources as the process may be memory-intensive
- When importing multi-version preprints, start with a simple 2-version example to verify the workflow
- Check the `invalid_[filename].csv` file generated after import for any failed rows

[← Prev: Dry Mode](dry-mode.md) | [README](../README.md)
