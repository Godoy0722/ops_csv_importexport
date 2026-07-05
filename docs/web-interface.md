# Web Interface Usage

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)

The plugin supports importing through the OPS web interface:

1. Navigate to **Tools → Import/Export → CSV Import Plugin**
2. Select the import type (**Preprints** or **Users**)
3. Upload your CSV file or a ZIP archive containing CSV files and associated assets
4. Optionally enable **Dry Mode** to validate without persisting changes
5. Optionally enable **Send Welcome Email** (visible only when importing Users)
6. Click **Import**

When using a ZIP file for preprint imports, place your CSV files along with any referenced assets (cover images, PDF files, supplementary files) either at the root of the archive or inside a single folder. If the ZIP contains exactly one folder and no CSV files at the root, that folder will be used automatically.

After the import completes, the interface displays a results modal with:

- **Summary badges** — files processed, total rows, successful rows, and failed rows
- **Per-file breakdown** — a table listing each failed row with its row number and error reason
- **Download links** — for each file that produced errors, a link to download the `invalid_*.csv` file containing the failed rows with error details

The modal border is green when all rows succeed and pink when any rows fail. Closing the modal automatically cleans up temporary files on the server.

> **Notes:**
> - The web interface uses the currently logged-in user as the importing user
> - All the same validation rules, multi-locale support, multi-version support, and error handling described in this documentation apply to web imports
> - For very large imports (thousands of rows), the CLI may be more appropriate as it avoids browser timeout concerns

[README](../README.md) | [Next: CLI Usage →](cli-usage.md)
