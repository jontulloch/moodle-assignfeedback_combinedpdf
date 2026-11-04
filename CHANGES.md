# Changelog

## 1.0.1-rc1 (Moodle 5.2) - 4 November 2026

- Assign a distinct GitHub-era release-candidate identifier (`1.0.1-rc1`; Moodle plugin version `2026110400`; `MATURITY_RC`) to separate this build from the original 1.0.0 ZIP.
- Record University of East London copyright and `GPL-3.0-or-later` licensing in source headers and project documentation, with `jontulloch` as GitHub maintainer.
- Retain the Moodle 5.2 automated PHP, Moodle validation and PHPUnit checks on MariaDB and PostgreSQL, plus Semgrep and Gitleaks security checks.
- Add a deterministic Moodle-installable package builder, release notes and SHA-256 verification for GitHub draft release packaging.
- No new user-facing functionality or Moodle database schema changes. Production deployment remains subject to institutional staging acceptance.

## 1.0.0 (Moodle 5.2) - 8 October 2026

- Remove the no-longer-required JavaScript confirmation fallback and rely on Moodle 5.2's native `confirmationyes` batch-action property.
- Convert every English language string to a Marketplace/AMOS-compatible single assignment without concatenation.
- Add full GPL boilerplate and file-level PHPDoc metadata to all PHP files.
- Prevent blind-marking filename collisions from disclosing Moodle user IDs.
- Omit related user IDs from failure events during blind marking and use privacy-safe participant labels.
- Record selected, preflight-eligible, completed, failed and skipped counts in the export summary event, including selections with no exportable material.
- Write the summary event only after ZIP creation, or when processing ends without a downloadable package.
- Confirm Turnitin is enabled globally and for the current Assignment before displaying cached Turnitin information, cache that check per request, and ignore unusable failed records without a score.
- Allow very long rubric level rows to flow across PDF pages rather than forcing every row to remain unbroken.
- Extend PDF-safe feedback handling to embedded object, embed, SVG, style, linked-style and CSS URL content.
- Cache the matching Assignment submission so preflight, source lookup and summary metadata reuse one request-local result.
- Add comprehensive administrator, user, developer, privacy, security and Marketplace documentation.
- Add the GNU GPL v3 licence file, declare the core Annotate PDF component dependency, and publish the release as stable version `2026100804`.

## 0.2.0-rc2 (Moodle 5.2) - 8 October 2026

- Preflight selected students using metadata and saved-feedback checks before enforcing
  the synchronous export limit. Students with no submission or saved feedback are
  skipped and do not count towards the maximum of 20.
- Reuse cached Annotate PDF, submission-file and Feedback comment lookups during
  package generation, and cache grading-instance and individual-marker results.
- Preserve per-student error isolation and count records whose eligibility cannot be
  determined conservatively as export candidates.
- Clarify the confirmation, limit and README messages to refer to exportable students.
- Increase plugin version to `2026100801`.

## 0.2.0-rc1 (Moodle 5.2) - 8 October 2026

- Replace the null Privacy API declaration with the same core subsystem links used
  by Annotate PDF: `core_files` and `core_fileconverter`.
- Enforce a hard limit of 20 eligible selected students per export request.
- Isolate unexpected errors per student, continue processing the remaining selection,
  put only a privacy-safe correlation reference in `README.txt`, and record sanitised
  technical details in Moodle's event log.
- Add a summary event containing Assignment/context, exporting user, selected count,
  completed count and failed count.
- Cache the active advanced-grading definition, rubric criteria and rubric levels once
  per request. Load rubric levels in one query and student fillings in one query. Apply
  the same single-query filling lookup to marking guides.
- Preserve graceful export when advanced-grading data cannot be rendered.
- Remove double escaping from Assignment and marking-guide names used in PDF output,
  metadata and ZIP filenames.
- Increase plugin version to `2026100800` and maturity to release candidate.

## 0.1.9-test (Moodle 5.2) - 6 October 2026

- Replace the summary-page grading timestamp with the current Assignment submission date/time.
- Add the locally cached Turnitin overall similarity score directly below the grade when
  the optional `plagiarism_turnitin` plugin has a report for the current submitted file.
- Mirror Turnitin's score bands and colours: 0 blue, 1-25 green, 26-50 yellow,
  51-75 orange, and 76-100 red. Pending reports use Turnitin's grey treatment.
- Add the Turnitin submission ID beneath the similarity score when available.
- Add the student's Assignment extension due date beneath the submission date only
  when a per-user extension has actually been granted.
- Keep Turnitin integration optional and local-only: no Turnitin API calls, report
  refreshes, database writes or hard plugin dependency are introduced.
- Preserve the 0.1.8 rubric layout and the 0.1.7 selected-student UI behaviour.
- Increase plugin version to `2026100601` for an in-place upgrade.

## 0.1.8-test (Moodle 5.2) - 6 October 2026

- Add numbered pale-grey criterion bands and greater separation between criteria.
- Give level descriptions and scores separate columns, with scores right-aligned.
- Highlight the selected level with pale blue, a stronger border, an explicit
  label and a bold score, without automatically bolding the entire descriptor.
- Put marker comments into a distinct shaded full-width section.
- Add repeating headers and row-level keep-together hints. Keep long criterion
  descriptions outside the repeating header without truncating their content.
- Retain all rubric wording, levels, sorting, scores and saved-selection lookup.
- Leave every non-rubric-rendering method, document conversion class and AMD file
  byte-for-byte unchanged from 0.1.7-test. No database migration is needed.
- Increase plugin version to `2026100600` for an in-place upgrade.

## 0.1.7-test (Moodle 5.2) - 5 October 2026

- Remove Download all feedback from the top Actions menu; retain Download feedback
  in the bottom selected-student bulk-action menu.
- Retire old assignment-wide download links by returning to the grading table
  with guidance rather than exporting unselected students.
- Set the confirmation button to Save files and refer to selected students in
  the confirmation text.
- Include a narrowly scoped AMD label fallback for this operation's modal only.
  Leave the core selection, confirmation, cancellation and form submission intact.
- Preserve the 0.1.6 conversion and export implementation without changes.
- Increase plugin version to `2026100503` for an in-place upgrade.

## 0.1.6-test (Moodle 5.2) - 5 October 2026

- Use Moodle's core file conversion API for DOCX and other non-PDF uploads when
  no saved Annotate PDF download exists for the selected grade attempt.
- Reuse cached conversions without forced refresh and merge completed PDFs before
  the feedback summary. Existing annotated downloads still take priority.
- Retain originals and add visible ZIP warnings for pending, failed, missing or
  unavailable conversions rather than silently omitting the script.
- Try Moodle's PDF compatibility helper once when unannotated sources fail to
  merge. Preserve source PDFs and original Office documents if merging fails.
- Keep conversion scoped to the existing submission attempt and unchanged access
  checks. Do not use cached partial PDFs or create grades/annotations.
- Document Moodle-managed conversion caches, synchronous export limitations and
  the validation still required on a Moodle staging site.
- Increase plugin version to `2026100502` for upgrades from 0.1.5-test.

## 0.1.5-test (Moodle 5.2) - 5 October 2026

- When no saved Annotate PDF download exists, use the saved file submission as
  the fallback source for the export.
- Merge submitted PDFs ahead of the feedback summary so students receive their
  original script even when no PDF annotations were made.
- Retain non-PDF submission files separately in the ZIP because this utility does
  not perform Office-document conversion.
- If a fallback submission PDF cannot be merged, retain it separately rather than
  dropping the script.

## 0.1.4-test (Moodle 5.2) - 5 October 2026

- Set Moodle 5.2 as the minimum and declared supported branch; increase the plugin
  version to `2026100500` for upgrades from the supplied 0.1.3-test package.
- Use the modern detailed bulk-action API and remove the legacy override.
- Declare that this utility has no modified editable feedback fields, avoiding
  the inherited Assignment plugin debugging message when grades are saved.
- Apply the current group and Moodle participant/marker-allocation list to bulk
  selections, as well as submission-view permissions and the global plugin state.
- Avoid core grade, submission and blind-mapping creation during exports.
- Include saved marks from currently allocated Moodle 5.2 multiple markers.
- Preserve each student's file when student names produce identical filenames.
- Fetch all Moodle name fields when formatting student names.
- Preserve annotations separately when merging fails; restore PHP error reporting
  after a merger exception and refuse ZIP creation with missing files.
- Include updated installation instructions and a Moodle 5.2 site-test checklist.

## 0.1.3-test - 18 September 2026

Original attached test build targeting Moodle 4.2–5.2.
