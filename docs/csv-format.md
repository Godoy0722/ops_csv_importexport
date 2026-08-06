# CSV Format

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)

## Users CSV Format

| Column | Required | Description | Example |
|--------|----------|-------------|---------|
| serverPath | Yes | Path of the server | leo |
| firstname | Yes | User's first name | Homer |
| lastname | No | User's last name | Simpson |
| email | Yes | User's email address | homer@example.com |
| affiliation | No | User's affiliation | University of British Columbia |
| country | No | Two-letter country code | CA |
| username | No | Username for login (auto-derived from email if empty) | hsimpson |
| tempPassword | No | Temporary password (auto-generated if empty; user receives a reset link) | temppassword123 |
| roles | Yes | Semicolon-separated list of roles (must exist on the target server) | Reader;Author |
| reviewInterests | No | Semicolon-separated interests | interest one;interest two |
| orcid | No | User's ORCID identifier | 0000-0002-1825-0097 |

> **User Interests:** User interests in the users CSV use a semicolon-separated format:
>
> ```
> interest one; interest two; another interest
> ```
>
>  - Leading/trailing spaces are automatically trimmed
>  - Empty values are ignored
>  - Each interest will be associated with the created user's profile

> **ORCID:** The ORCID field accepts multiple formats and will be automatically normalized to the standard URL format:
>
> Accepted formats:
>  - **Full URL**: `https://orcid.org/0000-0002-1825-0097` or `https://sandbox.orcid.org/0000-0002-1825-0097`
>  - **Dashed format**: `0000-0002-1825-0097`
>  - **Numeric format**: `0000000218250097`
>
> Notes:
>  - The last character can be a digit (0-9) or the letter X (checksum character)
>  - The ORCID checksum is validated during import
>  - Invalid ORCIDs will cause the row to be rejected
>  - Leave empty if the user doesn't have an ORCID

### Users CSV Example

You can take a look at the example we provide on the [User CSV file](../examples/users/users_example.csv).

Make sure to follow this CSV structure with all headers present, including the non-required ones. It is ok for non-required fields to have no values as long as the header is present.

## Preprints CSV Format

| Column | Required | Description | Example | Notes |
|--------|----------|-------------|---------|-------|
| serverPath | Yes* | Path of the target server | liv | Must exist in the system |
| locale | Yes* | Preprint locale | en | Must be enabled in the server |
| versionIdentifier | No** | Unique identifier for versioning | ML-CLIMATE-2024 | Required for multi-version preprints |
| version | No** | Version number | 1 | Required for multi-version preprints |
| preprintPrefix | No | Preprint prefix | ML | Optional abbreviation |
| preprintTitle | Yes* | Preprint title | My Research Preprint | |
| preprintSubtitle | No | Preprint subtitle | A Study of... | Optional |
| preprintAbstract | No | Preprint abstract | This paper examines... | Multi-line supported via `\n` or HTML |
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
| galleyViews | No | Semicolon-separated view counts per galley | 150;42 | Must match galleyFilenames count. See [Usage Statistics](#usage-statistics) |
| suppFilenames | No | Semicolon-separated supplementary files | supplement.pdf;data.csv | Optional |
| suppLabels | No | Labels for supplementary files | Supplement;Dataset | Must match suppFilenames count |
| suppDescriptions | No | Semicolon-separated descriptions for supplementary files | Supplementary analysis;Raw dataset (CSV) | Optional; if provided must match suppFilenames and suppLabels count |
| sectionTitle | No* | Section name | Preprints | Will be created if needed. *If either sectionTitle or sectionAbbrev is provided, both are required |
| sectionAbbrev | No* | Section abbreviation | PRE | Used if section is created. *Required when sectionTitle is provided |
| datePosted | Yes* | Posting date | 2024-01-15 | Format: YYYY-MM-DD |
| dateSubmitted | No | Submission date | 2024-01-10 | Format: YYYY-MM-DD, optional |
| copyrightYear | No | Copyright year | 2024 | Defaults to system setting if not provided |
| copyrightHolder | No | Copyright holder | Public Knowledge Project | Defaults to system setting if not provided |
| licenseUrl | No | License URL | https://creativecommons.org/licenses/by/4.0 | Defaults to system setting if not provided |
| references | No | Path to references file (.txt) | references.txt | Optional file containing preprint references |
| vorDoi | No | Version of Record DOI URL | https://doi.org/10.1234/vor-abc123 | See [Version of Record](#version-of-record-vor) |
| supportingAgencies | No | Semicolon-separated funding sources | NIH;NSF;Wellcome Trust | See [Supporting Agencies](#supporting-agencies) |
| username | No | Username of associated user | jsmith | See [Associated User](#associated-user) |
| funders | No | Structured funder information | See [Funders Support](#funders-support) | Requires Funding plugin |
| preprintViews | No | Total abstract/page view count | 523 | See [Usage Statistics](#usage-statistics) |

> **Notes:**
>  - *Required for first version or single-version preprints
>  - **For version > 1: Only `versionIdentifier` and `version` are required; all other fields are optional and will be cloned from the previous version if left empty

> **Multi-Line Abstracts**
> The `preprintAbstract` field supports multi-line content. Each line is automatically wrapped in `<p>` tags for proper display. You can write multi-line abstracts in two ways:
>
> **Option 1: Using `\n` (recommended for most editors)**
>
> Insert a literal `\n` where you want a paragraph break:
> ```
> This is the first paragraph.\nThis is the second paragraph.\nThis is the third paragraph.
> ```
>
> **Option 2: Using HTML tags**
>
> You can also provide the abstract with `<p>` or `<br>` tags directly. When HTML tags are detected, the content is stored as-is without any transformation:
> ```
> <p>This is the first paragraph.</p><p>This is the second paragraph.</p>
> ```

### Authors Format

The `authors` field in the preprints CSV must contain author information in the following format:

```
GivenName,FamilyName,Email,ORCiD,Affiliation,Biography;GivenName2,FamilyName2,Email2,ORCiD2,Affiliation2,Biography2
```

- Fields are separated by commas within each author
- Multiple authors are separated by semicolons
- All fields except `GivenName` are optional and can be left empty
- If `Email` is empty, the primary contact email of the server will be used
- `ORCiD` must be the author identifier and is optional; see input options below
- `Biography` is optional free text; it is stored per locale, so you can provide different biographies in multi-locale imports

Examples:

```
"John,Doe,john@example.com,0000-0002-1825-0097,University of Example,Dr. John Doe is a researcher in quantum physics; Jane,Smith,,https://orcid.org/0000-0002-1694-233X,Another University,"
"Maria,Silva,maria@example.com,0000000218250097,,"
"Carlos,,carlos@example.com,,Example Corp,Senior engineer at Example Corp"
```

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

### Keywords, Subjects, and Categories

These fields use a simple semicolon-separated format:
- **Keywords**: `keyword1; keyword two; another keyword`
- **Subjects**: `subject1; subject two; another subject`
- **Categories**: `Category1; Category Two; Another Category`

Notes:
- Leading/trailing spaces are automatically trimmed
- Empty values are ignored
- Categories will be created if they don't exist

### References File

The `references` field can contain the path to a TXT file with preprint references:
- The file must be in TXT format
- The file must be located in the same directory as the CSV file
- Each reference should be on a separate line or separated by a blank line
- The content of the file will be imported as the publication's citations

Example references.txt content:
```
Smith, J. (2023). "The Impact of Technology on Modern Publishing", Journal of Academic Publishing, 45(2), 123-145.

Johnson, M., & Williams, K. (2022). Digital Transformation in Scholarly Communication. Academic Press.

Brown, A. et al. (2021). "Open Access and the Future of Research Dissemination", International Journal of Scholarly Research, 12(4), 567-589.
```

### Version of Record (VOR)

The `vorDoi` field allows you to specify the DOI of the published Version of Record for a preprint:
- When provided, automatically sets the preprint's relation status to "Published"
- This indicates that the preprint has been formally published in a peer-reviewed venue
- Leave empty if the preprint has not been published elsewhere

Accepted formats:
- **Full URL**: `https://doi.org/10.1234/example` or `http://dx.doi.org/10.1234/example`
- **DOI identifier**: `10.1234/example`
- **With doi: prefix**: `doi:10.1234/example`

Notes:
- All formats are automatically normalized to `https://doi.org/...` before storing
- The DOI must start with `10.` followed by a registrant code (4+ digits)

Examples:
```
https://doi.org/10.1016/j.example.2024.123456
10.1038/s41586-024-07890-x
doi:10.1126/science.abc1234
```

### Supporting Agencies

The `supportingAgencies` field allows you to specify funding sources or institutional support for the research:
- Use semicolon-separated values for multiple agencies
- Leading/trailing spaces are automatically trimmed
- This field is multilingual — provide translations in multi-locale imports

Examples:
```
National Institutes of Health;National Science Foundation
Wellcome Trust
FAPESP;CNPq;CAPES
```

### Associated User

The `username` field allows you to associate a preprint with a specific existing user in the system:
- Provide the username of an existing user in OPS
- This user will be recorded as the uploader of the submission files
- The user is also added as a **contributor (author)** of the preprint and set as the primary contact. The contributor's name and affiliation are taken from the user's profile
- In multi-locale imports, the contributor's locale-specific name data is updated from the user's profile for each locale
- The user from `username` is added first, before the authors from the `authors` field. If the same person appears in both, the duplicate is automatically skipped
- If the username is not found, the system will use the default CLI user (the one specified in the command) and print a notice. In this case, the user is **not** added as a contributor
- Leave empty to use the default CLI user (as file uploader only, without adding as contributor)

Behavior when username is not found:
```
Notice: Username "unknownuser" not found. Submission ID 123 was added using default user "admin".
```

Notes:
- The user lookup is case-insensitive
- Disabled users are also matched

### Preprints CSV Example

You can take a look at the example we provide on the [Preprint CSV file](../examples/preprints/comprehensive_multilocale_preprints.csv).

Make sure to follow this CSV structure with all headers present, including the non-required ones. It is ok for non-required fields to have no values as long as the header is present.

### Import File Structure

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

## Funders Support

The CSV import plugin supports importing structured funder information for preprints using the `funders` column. This feature integrates with the **Funding plugin** to provide rich funding metadata that can be exported to Crossref, DataCite, and OpenAIRE.

### Prerequisites

Before using the `funders` column, you must install and enable the **Funding plugin** for your server:

1. **Install the Funding Plugin**: Download and install the Funding plugin from the official repository:
   - GitHub: [https://github.com/ajnyga/funding](https://github.com/ajnyga/funding)
   - Follow the installation instructions in the plugin's README

2. **Enable the Plugin**: Go to **Settings → Website → Plugins** and enable the "Funding Plugin" for your server.

> **Important**: If you include funders data in your CSV but the Funding plugin is not installed or not enabled for the target server, the row will be rejected and added to the invalid CSV file with an error message.

### Funders Data Structure

The `funders` column uses a structured format with multiple separators to represent complex funding information:

```
FunderName,FunderIdentification,Award1|Award2;FunderName2,FunderIdentification2,Award3
```

**Separators:**
| Separator | Purpose | Example |
|-----------|---------|---------|
| `;` (semicolon) | Separates multiple funders | `Funder1,...;Funder2,...` |
| `,` (comma) | Separates fields within a single funder | `Name,DOI,Awards` |
| `\|` (pipe) | Separates multiple awards for the same funder | `Award1\|Award2\|Award3` |

**Funder Fields (comma-separated):**
| Position | Field | Required | Description | Example |
|----------|-------|----------|-------------|---------|
| 1 | Funder Name | Yes | Name of the funding organization | National Science Foundation |
| 2 | Funder Identification | No* | Crossref Funder Registry DOI | http://dx.doi.org/10.13039/100000001 |
| 3 | Awards | No | Grant/award numbers (pipe-separated) | NSF-2024-001\|NSF-2024-002 |

> *The Funder Identification becomes required when Crossref validation is enabled in the plugin settings. See [Crossref Registry Validation](#crossref-registry-validation).

### Funders Examples

**Single funder with one award:**
```
National Science Foundation,http://dx.doi.org/10.13039/100000001,NSF-2024-001
```

**Single funder with multiple awards:**
```
National Institutes of Health,http://dx.doi.org/10.13039/100000002,R01-AI-123456|R21-AI-789012
```

**Multiple funders with mixed awards:**
```
National Science Foundation,http://dx.doi.org/10.13039/100000001,QC-2024-001|QC-2024-002;Department of Energy,http://dx.doi.org/10.13039/100000015,DE-SC0021234
```

**Funder without awards:**
```
Wellcome Trust,http://dx.doi.org/10.13039/100010269,
```

### Crossref Registry Validation

The Funding plugin has an optional setting called **"Enable Grant ID Validation"** (`enableGrantIdValidation`). When this setting is enabled:

1. **All funders must have a valid Crossref Funder Registry DOI** in their identification field
2. Valid Crossref DOIs follow the pattern: `http://dx.doi.org/10.13039/...` or `https://doi.org/10.13039/...`
3. Rows with funders missing valid Crossref DOIs will be rejected

**Finding Crossref Funder DOIs:**
You can search for funders and their DOIs at: [https://search.crossref.org/search/funders](https://search.crossref.org/search/funders)

### Important Funders Notes

1. **Submission-Level Data**: Funders are linked to the submission, not individual publications. All versions of a preprint share the same funding information.
2. **Multi-Locale Support**: Funders data is not localized. Provide funders in one row per submission (the first/primary locale row).
3. **Multi-Version Support**:
   - For version 1: Provide full funders data
   - For versions > 1: If empty, existing funders are preserved (not cloned since they're at submission level)
   - If funders data is provided in a later version row, it will be ignored if funders already exist for that submission
4. **Duplicate Prevention**: The system checks if funders already exist for a submission before adding new ones.
5. **Plugin Integration**: Uses the same business logic as the Funding plugin's web interface (`FunderForm::execute()`).
6. **Data Export**: Imported funders will be included in Crossref XML, DataCite XML, and OpenAIRE metadata exports.

## Usage Statistics

The plugin supports importing usage statistics (view counts) for preprints and their galley files. This is useful when migrating from another system and you want to preserve historical view data.

### Preprint Views

The `preprintViews` column allows you to import the total number of abstract/page views for a preprint:
- Must be a positive integer
- The views are recorded against the current date at the time of import
- Leave empty or set to 0 to skip

Example:
```
523
```

### Galley Views

The `galleyViews` column allows you to import download/view counts for each galley file individually:
- Uses semicolon-separated values, one per galley file
- Must have the same number of values as `galleyFilenames` and `galleyLabels`
- Each value must be a non-negative integer (use 0 or leave empty to skip a galley)
- Views are recorded against the current date at the time of import

Example (matching three galleys):
```
galleyFilenames:  paper.pdf;paper.html;paper.epub
galleyLabels:     PDF;HTML;EPUB
galleyViews:      150;;42
```

In this example, the PDF galley gets 150 views, HTML gets none, and EPUB gets 42 views.

### Important Statistics Notes

- Statistics are inserted directly into the `metrics_submission` table
- Views are associated with the current date at the time of import
- `galleyViews` requires `galleyFilenames` to be present; providing galley views without galleys will cause the row to be rejected
- For multi-version preprints, provide views only on the row where the galley files are defined
- View counts are not localized — provide them once, on the primary locale row

[← Prev: CLI Usage](cli-usage.md) | [README](../README.md) | [Next: Multi-Locale Support →](multi-locale.md)
