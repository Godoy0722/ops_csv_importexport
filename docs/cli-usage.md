# CLI Usage

[← Prev: Web Interface Usage](web-interface.md) | [README](../README.md) | [Next: CSV Format →](csv-format.md)

## Importing Users

To import users from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin users [username] [pathToFolderWithCsvFiles] [--sendWelcomeEmail] [--dry-mode]
```

Parameters:
- `username`: The username of a valid Preprint Manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the folder containing the CSV file(s) with user data. Can be absolute or relative to the OPS root directory.
- `--sendWelcomeEmail`: (Optional) If provided, welcome emails will be sent to imported users. The sender email will be the user retrieved by the username on the CLI command.
- `--dry-mode`: (Optional) If provided, runs the full validation pipeline without persisting any data. See [Dry Mode](dry-mode.md).

Example:
```bash
php tools/importExport.php CSVImportExportPlugin users admin /path/to/folder_with_csv_user_files --sendWelcomeEmail
```

## Importing Preprints

To import preprints from a CSV file, use the following command:

```bash
php tools/importExport.php CSVImportExportPlugin preprints [username] [pathToFolderWithCsvFiles] [--dry-mode]
```

Parameters:
- `username`: The username of a valid Preprint Manager. This username is used to validate that the command is running by a valid user as a security step.
- `pathToFolderWithCsvFiles`: Path to the folder containing the CSV file(s) with preprint data. Can be absolute or relative to the OPS root directory.
- `--dry-mode`: (Optional) If provided, runs the full validation pipeline without persisting any data. See [Dry Mode](dry-mode.md).

Example:
```bash
php tools/importExport.php CSVImportExportPlugin preprints admin /path/to/folder_with_csv_preprint_files
```

> **Important Notes**
>
>  - The user obtained through the username will be the same one assigned to the submission files. It's also recommended that a dedicated importUser is created for this purpose with the Author role so that it's separate from existing Preprint Manager and editor user accounts.
>  - The last CLI attribute must be the path to the folder containing the CSV file(s), not directly the CSV file itself.
>  - The CSV file and any referenced files (PDFs, images) must be readable by the user running the CLI script.
>  - The script must be executed from the OPS installation directory.
>  - Ensure you have proper permissions to execute PHP scripts and access the files.

[← Prev: Web Interface Usage](web-interface.md) | [README](../README.md) | [Next: CSV Format →](csv-format.md)
