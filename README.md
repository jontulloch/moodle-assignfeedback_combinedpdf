# Combined feedback PDF

**Frankenstyle component:** `assignfeedback_combinedpdf`  
**Plugin type:** Assignment feedback plugin  
**Release:** 1.0.0  
**Plugin version:** 2026100804  
**Supported Moodle branch:** Moodle 5.2.x  
**Licence:** [GPL-3.0-or-later](LICENSE)  
**Copyright:** (C) 2026 University of East London  
**Maintainer:** [jontulloch](https://github.com/jontulloch)

Combined feedback PDF adds a selected-user bulk action to the Moodle Assignment grading table. It creates a ZIP containing one feedback package for each selected student who has a submission or saved feedback.

A package normally consists of the student's submitted or annotated script followed by a generated summary containing the saved grade and feedback. The exporter reads existing Moodle records at download time. It does not create grades, annotations or feedback records, and it does not alter the Assignment.

## Main features

For each selected student, the exporter can include:

- the existing **Annotate PDF** feedback document
- the original submitted PDF when no annotated document exists
- a PDF converted from DOCX or another format through Moodle's configured file converter
- saved **Feedback comments**
- a saved rubric or marking guide
- the overall Assignment grade
- the current-attempt submission date
- an individual Assignment extension date, when one exists
- the locally cached Turnitin similarity score and Turnitin submission ID, when the optional `plagiarism_turnitin` plugin is enabled for the Assignment
- saved marks from currently allocated markers when Moodle multiple marking is in use

The output is intended for controlled academic workflows such as downloading feedback for external examiners, moderation or local record keeping. Downloading a package does not release feedback to students and does not change marking-workflow state.

## User workflow

1. Open the Assignment grading table.
2. Select one or more students using the row checkboxes.
3. Open the bottom bulk-action menu. The action may appear under **More**, depending on the theme and viewport.
4. Choose **Download feedback**.
5. Confirm with **Save files**.
6. Moodle downloads a ZIP containing the successfully created packages and, where necessary, a `README.txt` explaining omitted or separately retained material.

The plugin deliberately does not add an assignment-wide **Download all feedback** action to the top Actions menu. This prevents a checkbox selection from being mistaken for an all-participants export.

## Export eligibility and the 20-package limit

The exporter first performs a lightweight preflight check. A selected student is considered exportable when the relevant saved grade/attempt has at least one of the following:

- an existing Annotate PDF download
- one or more saved file submissions
- a non-empty Feedback comment
- a saved numerical or scale grade
- a saved rubric or marking-guide instance
- saved marks from Moodle multiple marking

Students with no exportable material are skipped and do not count towards the limit.

A maximum of **20 exportable students** can be processed in one synchronous request. For example:

| Selected | Exportable | Result |
|---:|---:|---|
| 21 | 16 | 16 packages are created |
| 30 | 18 | 18 packages are created |
| 25 | 20 | 20 packages are created |
| 25 | 21 or more | The request is stopped before PDF generation |
| 10 | 0 | Moodle reports that there is nothing to export |

The limit is enforced server-side after Moodle's current-group, participant, marker-allocation and submission-access checks.

## Package construction

The plugin builds each student package independently.

### 1. Source document selection

The source is selected in this order:

1. An existing PDF from Moodle's `assignfeedback_editpdf` `download` file area
2. The submitted file or files from the matching Assignment attempt
3. No source document, when the package contains feedback or a grade only

An Annotate PDF document takes priority because it already contains the marker's saved annotations.

### 2. Document conversion

Submitted PDFs are merged directly. Other file types are passed to Moodle's core file-conversion API.

The plugin:

- reuses an existing completed conversion when available
- allows a configured Moodle converter, such as Unoconv, to create a PDF
- does not call LibreOffice, Unoconv, Poppler or Ghostscript directly
- does not wait indefinitely for an asynchronous converter
- retains the original file in the ZIP if conversion is pending, unavailable or unsuccessful

If a generated PDF cannot be read by Moodle's PDF merger, the plugin may use Moodle Annotate PDF's compatibility helper when Ghostscript is configured. Saved source files are never overwritten.

### 3. Feedback summary

A new summary PDF is generated with Moodle's TCPDF library. It can contain:

- student or anonymous participant label
- Assignment name
- grade
- Turnitin similarity information
- submission date
- extension date
- Feedback comments
- rubric or marking-guide detail
- individual marker marks

The source document pages are placed before the summary pages.

### 4. ZIP creation

The plugin places each finished package in a request-scoped temporary directory and creates the ZIP using Moodle's file-packer API. Moodle's normal temporary-file lifecycle removes the working files.

## Annotate PDF behaviour

The exporter reads the existing Annotate PDF `download` file for the selected grade record. It does not generate, update or save annotations.

When no annotated document exists:

- a submitted PDF is used directly
- a supported Office document is converted through Moodle's file-conversion API
- the feedback summary can still be produced when there is saved feedback or a grade but no script

## Turnitin integration

Turnitin support is optional and has no hard plugin dependency.

When `plagiarism_turnitin` is installed and enabled globally and for the current Assignment, the exporter looks for a locally cached Turnitin record matching the current submitted file. It makes no Turnitin API request and does not refresh the report.

The summary can show:

- **Overall similarity**
- **Turnitin submission ID**

The similarity badge follows the colours used by the Turnitin Moodle plugin:

| Similarity | Badge colour |
|---:|---|
| 0% | Blue |
| 1–25% | Green |
| 26–50% | Yellow |
| 51–75% | Orange |
| 76–100% | Red |
| Score not yet available | Grey |

When several files in the same attempt have Turnitin records, the compact summary uses the highest available similarity and its corresponding Turnitin submission ID.

No Turnitin row is shown when Turnitin is not enabled for the Assignment or when no matching local record exists.

## Rubrics and marking guides

Static advanced-grading data is cached once for the export request:

- active grading method
- grading definition
- criteria
- all rubric levels

For each student, the plugin retrieves the preferred grading instance once and loads all saved fillings in one query, then indexes them in PHP.

The rubric layout provides:

- a separate shaded heading for each criterion
- a dedicated score column
- a visible **Selected level** label and highlighted selected row
- a separate marker-comment row
- page-flow handling for long level descriptions and comments

Incomplete grading instances can be included when no active instance exists. This permits moderation of partly completed marking. Institutions should therefore control distribution of downloaded ZIPs through their assessment procedures.

## Blind marking, groups and access control

The bulk action requires `mod/assign:grade` and a valid session key.

Before processing, the plugin applies Moodle's:

- current activity group
- participant list
- marker allocation restrictions
- active-user and suspended-user visibility rules
- per-student `can_view_submission()` check

During blind marking:

- existing Moodle participant identifiers are used instead of student names
- missing mappings are represented by request-local labels such as `Participant unmapped 1`
- the failure event omits `relateduserid`
- collision suffixes in ZIP filenames do not contain Moodle user IDs

For team assignments, selecting more than one member of the same team can produce more than one package referring to the same group submission. The packages remain associated with the selected participant labels.

## Error handling

Each student is processed inside an independent error boundary. An unexpected problem with one record does not stop later selected students.

For an unexpected failure:

- the student's package is omitted
- the ZIP `README.txt` contains a privacy-safe label and a correlation reference such as `CFP-A1B2C3D4E5F6`
- exception messages, server paths, stack traces and credentials are not written into the ZIP
- sanitised technical details are recorded in Moodle's event log

An administrator can match the `CFP-...` reference to the event named **Combined feedback student export failed** under **Site administration > Reports > Logs**.

If source material cannot be merged, it is preserved separately where possible rather than silently discarded.

## Events and audit trail

The plugin records two Moodle events.

### Combined feedback export processed

Records:

- Assignment object ID and course-module context
- exporting user
- number selected after Moodle access filtering
- number judged exportable during preflight
- number of packages completed
- number of unexpected failures
- number skipped for a non-error reason

### Combined feedback student export failed

Records:

- Assignment object ID and course-module context
- exporting user
- affected student as `relateduserid`, except during blind marking
- privacy-safe student/participant label
- correlation reference
- sanitised exception class, code, message, source filename and line

The summary event is written only after the ZIP has been created successfully, or when an attempted export ends with no downloadable package after preflight/processing.

## Privacy

The plugin creates no database tables and no persistent plugin-owned file areas.

Its Privacy API provider declares use of:

- `core_files`
- `core_fileconverter`

This is required because the plugin reads Moodle files and can cause Moodle core to cache a converted PDF. Core files and converter records are managed by their own privacy providers and lifecycle processes.

The downloaded ZIP itself is outside Moodle after delivery. The person downloading it is responsible for storing, transferring and deleting it according to institutional policy and applicable data-protection requirements.

## Security model

The implementation:

- requires a valid Moodle session key for the bulk action
- requires `mod/assign:grade`
- restricts requested user IDs to Moodle's eligible participant list
- checks submission access again for each student
- uses Moodle DML with parameters rather than constructing user-input SQL
- sanitises ZIP filenames with `clean_filename()`
- uses Moodle's temporary directory, file API, converter API and ZIP packer
- does not use `eval()`, `unserialize()`, direct request superglobals, shell commands or direct external HTTP requests
- does not write Turnitin data, grades, submissions, annotations, feedback or rubric records

## Requirements

### Required

- Moodle **5.2.x**
- Moodle Assignment (`mod_assign`)
- Moodle Annotate PDF (`assignfeedback_editpdf`), declared as a component dependency because its PDF merger and compatibility helper are used
- PHP and database versions supported by the installed Moodle 5.2 release

### Required for Office-document conversion

At least one Moodle document converter capable of converting the submitted format to PDF. For example, a correctly configured Unoconv converter can convert DOCX submissions.

Without a working converter, PDFs can still be merged. Non-PDF originals are retained separately with a warning.

### Optional

- Ghostscript for Moodle's compatibility pass when a valid PDF cannot be parsed directly
- `plagiarism_turnitin` for local similarity score and submission-ID display
- Moodle multiple marking for individual allocated-marker marks

Poppler may be used by Moodle Annotate PDF to render grading-page previews, but this plugin does not invoke Poppler directly.

## Installation

### Install from a ZIP

1. Sign in as a Moodle site administrator.
2. Open **Site administration > Plugins > Install plugins**.
3. Upload the plugin ZIP.
4. Choose **Assignment feedback plugins (`assignfeedback`)** if Moodle asks for the plugin type.
5. Complete validation and installation.
6. Visit **Site administration > Plugins > Activity modules > Assignment > Feedback plugins** and confirm that **Combined feedback PDF** is visible/enabled.
7. Purge caches after an upgrade if the grading-page action does not update immediately.

### Manual installation

Extract the `combinedpdf` directory to:

```text
public/mod/assign/feedback/combinedpdf
```

Then visit **Site administration > Notifications** to complete installation.

The archive must contain exactly one top-level directory named `combinedpdf`.

## Upgrade

Install the new ZIP over the existing plugin through Moodle's normal upgrade process. Do not uninstall first, because uninstallation is unnecessary and briefly removes the component from the site.

This plugin has no database schema and therefore has no plugin-specific upgrade steps or savepoints.

## Uninstallation

The plugin owns no database tables or persistent file areas. Removing it removes the export action and event classes. It does not delete Assignment submissions, feedback, annotations, grades, Turnitin records or conversions managed by Moodle core.

Use Moodle's standard plugin-uninstallation process, then remove the directory if the server deployment process does not do so automatically.

## Configuration

There are no per-assignment settings. The plugin is a read-only export utility and is available to Assignments whenever it is globally visible in Moodle's Assignment feedback-plugin management page.

The maximum of 20 exportable packages is defined by `MAX_EXPORT_USERS` in `locallib.php`. Changing it requires code review and renewed performance testing.

## Performance and operational guidance

The export runs synchronously in the web request. The 20-package limit reduces risk but does not guarantee that every possible set of 20 files will fit the site's execution-time, memory and temporary-disk limits.

The heaviest operations are:

- first-time Office conversion
- parsing and merging long PDFs
- compatibility conversion through Ghostscript
- generating large rubric or marking-guide summaries
- ZIP compression

Before deployment, test realistic file sizes and cohorts while monitoring:

- PHP-FPM or web-server request timeout
- PHP memory use
- Moodle temporary storage
- CPU use by LibreOffice/Unoconv and Ghostscript
- simultaneous exports by multiple markers

For significantly larger batches, a future version should move generation into an ad-hoc task with delayed download rather than raising the synchronous limit.

## Troubleshooting

### The action is missing

- Confirm the plugin is installed in `mod/assign/feedback/combinedpdf`.
- Confirm it is visible under the Assignment feedback-plugin management page.
- Select at least one student and check the bottom bulk-action menu, including **More**.
- Purge Moodle caches after an upgrade.

### Nothing is exported

The selected students may have no file submission, annotations, Feedback comment, saved grade, advanced-grading instance or multiple-marker mark for the relevant attempt.

### More than 20 selected students are accepted or rejected unexpectedly

The limit counts exportable students after preflight, not all checked rows. An eligibility lookup that itself fails is counted conservatively towards the limit.

### A DOCX file appears separately instead of inside the PDF

Check Moodle's document-converter configuration and converter logs. The ZIP `README.txt` distinguishes pending and failed conversions.

### A source PDF appears separately

Moodle's PDF parser could not merge it. Confirm Ghostscript is correctly configured for Moodle's Annotate PDF compatibility test.

### Turnitin information is missing

Confirm:

- `plagiarism_turnitin` is installed
- Turnitin is enabled globally for Moodle Assignment
- Turnitin is enabled in this Assignment
- the current submitted file has a matching local Turnitin record
- the similarity report has been processed when a score is expected

### A `CFP-...` reference appears

Search Moodle logs for **Combined feedback student export failed** at the time of the download and match the reference. Technical details are deliberately kept out of the ZIP.

## Developer guide

### Directory structure

```text
combinedpdf/
├── classes/
│   ├── event/
│   │   ├── feedback_exported.php
│   │   └── student_export_failed.php
│   ├── local/
│   │   └── submission_pdf.php
│   └── privacy/
│       └── provider.php
├── lang/
│   └── en/
│       └── assignfeedback_combinedpdf.php
├── CHANGES.md
├── LICENSE
├── MARKETPLACE.md
├── README.md
├── VALIDATION.md
├── locallib.php
└── version.php
```

### Main class

`assign_feedback_combinedpdf` in `locallib.php` extends Moodle's `assign_feedback_plugin`.

Important entry points:

- `get_grading_batch_operation_details()` registers the selected-user action
- `grading_batch_operation()` validates the requested action and session key
- `send_feedback_zip()` applies access filtering, preflight, limits, isolation and ZIP delivery
- `build_student_pdf()` resolves sources and generates one package
- `create_summary_pdf()` renders summary metadata and feedback
- `get_advanced_grading_cache()` loads static rubric/guide data once per request
- `render_rubric_html()` and `render_guide_html()` render student-specific advanced grading

### File conversion helper

`classes/local/submission_pdf.php` wraps Moodle's `core_files\converter` API. It returns one of:

- `original`
- `converted`
- `pending`
- `failed`

It does not invoke converter binaries directly and does not force-refresh cached conversions.

### Moodle data read by the plugin

The plugin uses Moodle APIs wherever practical and reads records associated with:

- `assign`
- `assign_submission`
- `assign_grades`
- `assignsubmission_file`
- `assignfeedback_comments`
- `assignfeedback_editpdf` file areas
- `assign_user_flags`
- `assign_user_mapping`
- advanced-grading definitions, instances, criteria, levels and fillings
- Moodle 5.2 multiple-marking records through the Assignment API
- optional `plagiarism_turnitin_config` and `plagiarism_turnitin_files`

It does not insert, update or delete records in those tables.

### Caching strategy

Request-local arrays cache:

- Annotate PDF file lookups
- submission-file lookups
- Feedback comments
- grading instances
- rendered multiple-marker data
- advanced-grading method, definition, criteria and rubric levels
- privacy-safe export labels

The cache lasts only for the current PHP request.

### Error isolation

Both preflight and package generation catch `Throwable` per student. The limit exception is deliberately raised outside the per-student catch path so it cannot be misreported as a student failure.

When adding new export stages, keep them inside the per-student boundary unless failure must invalidate the entire archive.

### Adding another metadata source

A new optional integration should:

1. detect the external plugin or table safely
2. confirm that the integration is enabled for the activity
3. match the current Assignment attempt/file rather than merely the latest user record
4. read locally cached data only unless explicit external communication is part of the documented design
5. return no output when unavailable
6. avoid making the optional integration a hard dependency in `version.php`

### Internationalisation

All user-facing strings belong in `lang/en/assignfeedback_combinedpdf.php`. Keep each `$string[...]` assignment as one uninterrupted value; Moodle's language-processing tools do not support concatenation in language files.

Only the English language file should be included in the plugin. Community translations can be managed through Moodle's translation system after public registration.

### Release process

For each release:

1. update `$plugin->version`, `$plugin->release` and maturity in `version.php`
2. update `CHANGES.md`
3. run PHP syntax checks
4. run Moodle CodeSniffer and PHPDoc checks
5. run Moodle Plugin CI against every declared Moodle branch and supported database target
6. install or upgrade the ZIP on a clean Moodle site with developer debugging enabled
7. complete the staging matrix in `VALIDATION.md`
8. ensure the ZIP contains one top-level `combinedpdf` directory
9. calculate and publish a SHA-256 checksum

### Marketplace preparation

Before public submission, the maintainer should provide:

- a public source repository, conventionally named `moodle-assignfeedback_combinedpdf`
- a public issue tracker
- installation and usage documentation
- screenshots of selection, confirmation and exported output
- confirmed institutional copyright/licensing authorisation and maintainer details (see `LICENSING.md`)
- a unique Marketplace component registration for `assignfeedback_combinedpdf`
- successful Marketplace archive validation and Moodle plugin CI results

No third-party library is bundled, so `thirdpartylibs.xml` is not required for the current package.

## Known limitations

- Moodle 5.2.x is the only declared supported branch.
- Generation is synchronous and limited to 20 exportable students.
- Online-text submissions are not converted into script pages.
- Other non-file submission plugins are not rendered as source documents.
- Multi-file submissions use the highest matching Turnitin similarity in the compact summary.
- Saved feedback can be exported before workflow release.
- Incomplete rubric/guide instances can be included.
- Team members selected separately can produce duplicate group-script packages.
- Embedded images, video, audio, iframes, objects, embeds, SVG, linked stylesheets, style blocks and CSS URL content are removed or replaced with a note in generated feedback HTML.
- PDF/UA accessibility conformance has not been established.
- The plugin does not provide a student-facing download button; it creates a marker/admin ZIP from the grading table.

## Testing

See `VALIDATION.md` for static checks performed for this release and the required staging/production acceptance matrix.

## Licence and copyright

This plugin is licensed under GNU GPL v3 or later (`GPL-3.0-or-later`). See [LICENSE](LICENSE) and [LICENSING.md](LICENSING.md).

Copyright (C) 2026 University of East London. The University has authorised open-source publication under this licence, as confirmed by the project maintainer. The repository is maintained by [jontulloch](https://github.com/jontulloch). This attribution does not replace the need to verify rights in third-party contributions before distribution.
