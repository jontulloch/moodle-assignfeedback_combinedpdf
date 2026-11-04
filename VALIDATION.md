# Validation record for Combined feedback PDF 1.0.0

This record separates checks executed in the development workspace from checks that require a complete Moodle installation or the Moodle Marketplace toolchain.

## Package reviewed

- Component: `assignfeedback_combinedpdf`
- Release: `1.0.0`
- Plugin version: `2026100804`
- Declared Moodle support: `5.2.x`
- PHP used for executable checks: `8.4.24`

## Executed checks

### PHP and package structure

- Every packaged PHP file passed `php -l`.
- The language file passed PHP lint and a dedicated pure-data check.
- Every `$string[...]` entry is a single one-line assignment with no concatenation, heredoc or nowdoc.
- Every PHP file contains the Moodle GPL boilerplate, package metadata, copyright line, licence tag and `MOODLE_INTERNAL` guard.
- No PHP closing tags are present.
- No tabs or trailing whitespace were found.
- No non-language PHP line exceeded 132 characters in the targeted line-length check.
- No direct request superglobals, `eval()`, `unserialize()`, shell-execution functions or JavaScript build directory were found.
- All `get_string(..., 'assignfeedback_combinedpdf')` references resolve to an English string identifier.
- `version.php` contains the expected component, stable release, Moodle requirement, supported branch, Assignment dependency and Annotate PDF dependency.

### Event tests with Moodle service doubles

The event classes were loaded against small test doubles and checked for:

- valid selected, eligible, completed, failed and skipped counts
- rejection when eligible exceeds selected
- rejection when completed plus failed exceeds eligible
- rejection when completed plus failed plus skipped does not equal selected
- blind-marking failure events without `relateduserid`
- privacy-safe participant label and `CFP-...` reference in the failure description

### Privacy-provider tests with Moodle service doubles

The privacy provider was loaded against interface doubles and confirmed to:

- implement the Assignment feedback privacy interfaces
- declare `core_files`
- declare `core_fileconverter`
- own no plugin table or file-area export/deletion implementation

### File-conversion helper tests with Moodle service doubles

The submission-PDF helper was checked for:

- direct PDF reuse
- completed converted-PDF reuse
- pending conversion handling
- failed converter handling
- empty-file rejection

### Isolated helper tests

Private helper methods were exercised through reflection to confirm:

- duplicate ZIP filenames receive generic numeric suffixes rather than Moodle user IDs
- image, embed, object and script elements are removed/replaced from PDF-safe editor HTML
- known Moodle paths, URL credentials and token-like values are redacted from logged exception messages
- 20 candidates are accepted and the 21st triggers the export limit

### Source-review checks

The source was reviewed for:

- capability and session-key checks
- participant and per-student submission access checks
- blind-marking labels
- parameterised Moodle DML use
- absence of plugin database writes
- request-local caching of submissions, files, feedback, grading definitions, levels and instances
- one-query-per-student advanced-grading fillings
- per-student `Throwable` isolation
- ZIP-safe failure reporting
- Turnitin activity-enable checks and current-file matching
- event object mappings
- Moodle core converter, PDF and ZIP API use

## Maintainer-reported staging check

The maintainer removed the earlier confirmation-dialog JavaScript fallback and reported that Moodle 5.2 with the target theme continued to display **Save files** and complete the selected-student export correctly using Moodle's native `confirmationyes` property.

This report does not treat that observation as a substitute for the final acceptance matrix below.

## Checks not available in this workspace

The following were not executed here:

- installation and upgrade inside a full Moodle 5.2 site
- official Moodle CodeSniffer and PHPDoc prechecks
- Moodle Plugin CI
- PHPUnit or Behat inside Moodle
- MySQL/MariaDB and PostgreSQL comparison testing
- Edwiser RemUI automated browser testing
- live Unoconv/LibreOffice, Ghostscript and Poppler execution
- live Turnitin database records
- concurrent marker load testing
- PDF/UA accessibility validation

These are not implied by the static and isolated checks above.

## Required staging acceptance matrix

Complete the following before production rollout or Marketplace submission.

1. Install or upgrade the ZIP on Moodle 5.2 with developer debugging enabled. Confirm no installation, deprecation, coding or runtime notices.
2. Confirm the action appears only in the selected-user bottom bulk-action menu and that **Save files** works without custom JavaScript.
3. Select 21 students where only 16 have a submission or saved feedback. Confirm 16 packages are created.
4. Select 21 exportable students. Confirm the friendly limit message appears before conversion and PDF generation.
5. Include one damaged or unsupported source between successful students. Confirm later students still export and `README.txt` contains only a label plus a `CFP-...` reference.
6. Match the reference to **Combined feedback student export failed** in Moodle logs and verify that blind marking does not expose the related user.
7. Confirm **Combined feedback export processed** records selected, eligible, completed, failed and skipped counts correctly.
8. Export multiple students sharing one rubric. Verify criterion order, levels, selected levels, zero/decimal scores and comments.
9. Test a marking guide and a partly completed rubric. Confirm no selection or score is invented.
10. Test very long criterion, level and comment text across page boundaries.
11. Test an annotated PDF, an unannotated PDF, a cached DOCX conversion and a first-time DOCX conversion.
12. Test converter pending and converter failure behaviour, including preservation of originals.
13. Confirm Turnitin enabled, pending, disabled and absent scenarios.
14. Confirm submission date and extension date against Moodle records.
15. Test blind marking, revealed identities, individual submissions, team submissions, restricted groups, marker allocation, duplicate names and reopened attempts.
16. Test grade values of zero, decimal grades, scales and no saved grade.
17. Monitor web-request duration, PHP memory, temporary disk, converter CPU and ZIP size with 20 realistic submissions.
18. Repeat key tests under both PHP 8.3 and PHP 8.4 when both are in the intended support matrix.
19. Run the site's Privacy API tests and inspect the plugin in Moodle's privacy registry.
20. Run Moodle CodeSniffer, PHPDoc checks and Moodle Plugin CI, then review every warning before Marketplace submission.

## Production conclusion

The package passed syntax, structure, targeted security, privacy, event and helper checks in this workspace. It is a strong production candidate for the tested Moodle 5.2 environment, but final approval depends on the complete staging matrix and operational load test.
