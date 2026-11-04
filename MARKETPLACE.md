# Moodle Marketplace preparation notes

This file records the distinction between code/package readiness and the external information required to list `assignfeedback_combinedpdf` publicly.

## Package status

The 1.0.0 package is structured as an Assignment feedback plugin with the Frankenstyle component:

```text
assignfeedback_combinedpdf
```

The archive must contain one top-level directory:

```text
combinedpdf/
```

The package includes:

- `version.php` with component, version, Moodle requirement, supported branch, Assignment and Annotate PDF dependencies, maturity and release
- English language strings in Marketplace/AMOS-compatible one-line assignments
- Privacy API metadata
- Moodle event classes
- GNU GPL v3 licence text
- administrator and developer documentation
- Marketplace-recognised `CHANGES.md`

No third-party library is bundled. The plugin uses Moodle core APIs and therefore does not require `thirdpartylibs.xml` in its current form.

## External prerequisites before submission

The following cannot be completed solely inside the ZIP and must be supplied by the maintainer/provider:

1. **Retain documentation of institutional licensing authority.** The University of East London is the copyright holder and has authorised open-source publication under `GPL-3.0-or-later`, as confirmed by the maintainer. See [LICENSING.md](LICENSING.md). Verify the rights and attribution of any third-party contributions before public distribution.
2. **Create a public source repository.** The conventional repository name is `moodle-assignfeedback_combinedpdf`.
3. **Create a public issue tracker.** Link it from the Marketplace listing and repository README.
4. **Reserve or confirm the Frankenstyle component.** A preliminary public search found no exact listing for `assignfeedback_combinedpdf`, but Moodle Marketplace is the authoritative registry.
5. **Prepare Marketplace text.** Provide a concise short description and a full description consistent with `README.md`.
6. **Provide screenshots.** Recommended examples are the grading-table bulk action, the confirmation dialog, an exported summary page, a highlighted rubric and the ZIP contents.
7. **Run Marketplace archive validation and Moodle plugin CI.** Resolve all errors and review warnings before requesting approval.
8. **Run Moodle CodeSniffer and PHPDoc checks.** The development workspace performed targeted style checks but did not have the official Moodle CodeSniffer toolchain installed.
9. **Test supported databases.** At minimum, test MySQL/MariaDB and PostgreSQL on Moodle 5.2 with developer debugging enabled.
10. **Test the complete converter stack.** Include a fresh DOCX conversion, cached conversion, PDF merge, Ghostscript compatibility pass and converter failure.
11. **Complete privacy validation.** Confirm the plugin appears correctly in Moodle's privacy registry and run the site's Privacy API tests.
12. **Define support and maintenance ownership.** Identify the lead maintainer, security contact, support channel and expected Moodle-version support policy.

## Suggested Marketplace short description

> Download selected Moodle Assignment submissions and saved feedback as combined PDFs, including annotations, feedback comments, advanced grading, optional Turnitin similarity and audit-safe failure reporting.

## Suggested category and pricing

- Plugin type: **Assignment feedback**
- Suggested listing: **Free**, under GNU GPL v3 or later

The package contains no commercial licence check and requires no paid service. Turnitin display is optional and reads locally cached data from an independently configured Turnitin installation.

## Review topics to explain clearly

A reviewer is likely to ask about the following areas:

- why the plugin is implemented as an Assignment feedback subplugin while storing no feedback of its own
- why the export is synchronous and limited to 20 exportable packages
- how access restrictions, marker allocation and blind marking are preserved
- why incomplete advanced-grading instances may be exported
- how core document conversion can create cached data even though the plugin owns no file area
- how optional Turnitin data is matched to the current file without contacting Turnitin
- how per-student failure details are separated between the ZIP and Moodle's restricted event log

The answers are documented in `README.md`.

## Recommended CI matrix

For the declared 5.2-only release, test at least:

- PHP 8.3 with Moodle 5.2
- PHP 8.4 with Moodle 5.2
- MariaDB/MySQL
- PostgreSQL
- core Boost theme
- the institution's production theme
- document conversion enabled and disabled
- Turnitin installed/enabled, installed/disabled and not installed
- blind marking on and off
- individual and team submissions
- single marking and multiple marking

## Release checklist

Before uploading a version:

- bump `$plugin->version`
- update `$plugin->release` and maturity as appropriate
- update `CHANGES.md`
- confirm the ZIP root and directory name
- run PHP lint, Moodle CodeSniffer, PHPDoc checks and Moodle Plugin CI
- install/upgrade on a clean staging site
- complete the functional matrix in `VALIDATION.md`
- publish the checksum and release notes
