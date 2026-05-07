# Preprint Versions

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Supplementary Files Descriptions →](supplementary-files.md)

The CSV import plugin supports creating multiple versions of the same preprint in a single import operation. This feature allows you to track revisions, corrections, and updates to posted preprints while maintaining a complete version history.

## How It Works

The multiversion system uses two key fields to manage preprint versions:

- **versionIdentifier**: A unique string that links multiple versions of the same preprint together
- **version**: A positive integer indicating the version number (1, 2, 3, etc.)

When you provide these fields in your CSV:
1. Preprints with the same `versionIdentifier` are treated as different versions of the same submission
2. Each version can have updated content, metadata, or files
3. The system automatically sets the highest version number as the current published version
4. All versions remain accessible in the system's version history

## Version Management Rules

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

## Practical Examples

### Example 1: Single Preprint Without Versions

For preprints that don't need version tracking, simply leave `versionIdentifier` and `version` empty. You can take a look at the [single version CSV file](../examples/preprints/single_version_single_locale_preprints.csv).

### Example 2: Multi Version Preprints

For preprints with multiple versions, you'll need to set the `versionIdentifier` and `version` fields. The `versionIdentifier` tracks the same preprint and the `version` handles the different versions. See the [multi version CSV file](../examples/preprints/multi_version_single_locale_preprints.csv) example.

### Example 3: Mixed Preprints

You can mix single-version and multi-version preprints in the same CSV file. Take a look at the [comprehensive example CSV file](../examples/preprints/comprehensive_multilocale_preprints.csv).

## Important Notes

- All versions of a preprint share the same submission ID but have different publication IDs
- Each version can have its own DOI if needed
- Readers can access previous versions through the preprint's version history
- The import process validates that no duplicate versions exist (same identifier + version number)
- Versions must be imported in sequence within a single CSV file (version 1 before version 2, etc.)

[← Prev: Multi-Locale Support](multi-locale.md) | [README](../README.md) | [Next: Supplementary Files Descriptions →](supplementary-files.md)
