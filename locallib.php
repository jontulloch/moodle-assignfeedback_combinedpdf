<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Assignment feedback plugin implementation for combined PDF exports.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Retain an explicit guard for this plugin entry point.
defined('MOODLE_INTERNAL') || die();

/**
 * Assignment feedback plugin which exports saved feedback as combined PDFs.
 *
 * This utility plugin stores no assignment-level data of its own.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class assign_feedback_combinedpdf extends assign_feedback_plugin {
    /** Maximum number of exportable feedback packages generated in one synchronous request. */
    private const MAX_EXPORT_USERS = 20;

    /** @var array Anonymous labels for users without an existing participant mapping. */
    private array $unmappedlabels = [];

    /** @var array Cached export labels for this request. */
    private array $exportlabels = [];

    /** @var array|null Cached advanced-grading definition and static rubric/guide data. */
    private ?array $advancedgradingcache = null;

    /** @var array Cached Annotate PDF downloads indexed by grade ID. */
    private array $annotatedpdfcache = [];

    /** @var array Cached submissions indexed by user and grade. */
    private array $submissioncache = [];

    /** @var array Cached submission files indexed by user and grade. */
    private array $submissionfilescache = [];

    /** @var array Cached Feedback comments indexed by grade ID. */
    private array $feedbackcommentcache = [];

    /** @var array Cached rendered individual-marker information indexed by grade ID. */
    private array $markergradinghtmlcache = [];

    /** @var array Cached grading instances indexed by definition and grade ID. */
    private array $gradinginstancecache = [];

    /** @var bool|null Cached availability of Turnitin for this Assignment. */
    private ?bool $turnitinenabled = null;

    /**
     * Plugin display name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_combinedpdf');
    }

    /**
     * This is a utility plugin and is available for every assignment.
     *
     * @return bool
     */
    public function is_enabled() {
        return true;
    }

    /**
     * No per-assignment setting is required.
     *
     * @return bool
     */
    public function is_configurable() {
        return false;
    }

    /**
     * Do not add an empty feedback column to the grading table.
     *
     * @return bool
     */
    public function has_user_summary() {
        return false;
    }

    /**
     * This plugin does not store its own user feedback.
     *
     * @param stdClass $grade
     * @return bool
     */
    public function is_empty(stdClass $grade) {
        return true;
    }

    /**
     * This utility has no editable feedback fields.
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    public function is_feedback_modified(stdClass $grade, stdClass $data) {
        return false;
    }

    /**
     * Keep feedback downloads in the selected-student bulk menu only.
     *
     * @return array
     */
    public function get_grading_actions() {
        return [];
    }

    /**
     * Retire the old assignment-wide download without exporting unselected users.
     *
     * @param string $action
     * @return string
     */
    public function view_page($action) {
        if ($action !== 'downloadallfeedback') {
            return '';
        }

        require_capability('mod/assign:grade', $this->assignment->get_context());
        $url = new moodle_url('/mod/assign/view.php', [
            'id' => $this->assignment->get_course_module()->id,
            'action' => 'grading',
        ]);
        redirect(
            $url,
            get_string('downloadallremoved', 'assignfeedback_combinedpdf'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
        return '';
    }

    /**
     * Add the selected-submission bulk operation.
     *
     * @return array
     */
    public function get_grading_batch_operation_details() {
        global $OUTPUT;

        $operation = new stdClass();
        $operation->key = 'downloadfeedback';
        $operation->label = get_string('downloadselectedfeedback', 'assignfeedback_combinedpdf');
        $operation->icon = $OUTPUT->pix_icon('t/download', '');
        $operation->confirmationtitle = get_string('confirmdownloadselectedtitle', 'assignfeedback_combinedpdf');
        $operation->confirmationquestion = get_string(
            'confirmdownloadselected',
            'assignfeedback_combinedpdf',
            self::MAX_EXPORT_USERS
        );
        $operation->confirmationyes = get_string('savefilesbutton', 'assignfeedback_combinedpdf');

        return [$operation];
    }

    /**
     * Handle the selected-submission bulk operation.
     *
     * @param string $action
     * @param array $users
     * @return void
     */
    public function grading_batch_operation($action, $users) {
        if ($action !== 'downloadfeedback') {
            return;
        }

        require_sesskey();
        require_capability('mod/assign:grade', $this->assignment->get_context());
        $userids = array_values(array_unique(array_filter(array_map('intval', (array) $users))));
        $this->send_feedback_zip($userids);
    }

    /**
     * Build and stream a ZIP containing one combined PDF per user.
     *
     * @param array $userids
     * @return void
     */
    private function send_feedback_zip(array $userids) {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        require_capability('mod/assign:grade', $this->assignment->get_context());
        if (!$this->is_visible()) {
            throw new moodle_exception('plugindisabled', 'assignfeedback_combinedpdf');
        }

        // Use Moodle's participant list, which also applies marker allocation and activity access.
        $currentgroup = groups_get_activity_group($this->assignment->get_course_module(), true);
        $participants = $this->assignment->list_participants($currentgroup, true);
        $userids = array_values(array_intersect(
            array_unique(array_filter(array_map('intval', $userids), static fn($id) => $id > 0)),
            array_keys($participants)
        ));

        if (empty($userids)) {
            throw new moodle_exception('nothingtoexport', 'assignfeedback_combinedpdf');
        }
        $selectedcount = count($userids);

        // Preflight the selection before applying the synchronous export limit. This
        // performs metadata/database checks only: no Office conversion, PDF rendering,
        // PDF merging or ZIP creation occurs here.
        $eligibleusers = [];
        $skipped = [];
        $failedexports = [];
        foreach ($userids as $position => $userid) {
            $label = get_string('selectedstudentreference', 'assignfeedback_combinedpdf', $position + 1);

            try {
                if (!$this->assignment->can_view_submission($userid)) {
                    $skipped[] = $label;
                    continue;
                }

                $label = $this->get_export_label($userid);
                $grade = $this->get_saved_grade($userid);
                if (!$grade || !$this->has_exportable_content($userid, $grade)) {
                    $skipped[] = $label;
                    continue;
                }

                $eligibleusers[] = (object) [
                    'userid' => (int) $userid,
                    'label' => $label,
                    'grade' => $grade,
                ];
            } catch (\Throwable $e) {
                // A broken record must not prevent other selected students from exporting.
                // Count it conservatively as a candidate because eligibility could not be
                // determined, then expose only a correlation reference in the ZIP.
                $reference = $this->create_failure_reference();
                $failedexports[] = (object) [
                    'label' => $label,
                    'reference' => $reference,
                ];
                $this->log_student_export_failure($userid, $label, $reference, $e);
            } finally {
                // Run the guard outside the catch path so the intentional limit
                // exception is never misreported as a student export failure.
                $this->assert_within_export_limit(count($eligibleusers) + count($failedexports));
            }
        }

        $eligiblecount = count($eligibleusers) + count($failedexports);

        if ($eligiblecount === 0) {
            $this->log_export_summary($selectedcount, 0, 0, 0, $selectedcount);
            throw new moodle_exception('nothingtoexport', 'assignfeedback_combinedpdf');
        }

        // There is no useful ZIP when every potentially exportable record failed
        // during preflight. The individual failure events already contain the safe
        // diagnostic references for administrators.
        if (empty($eligibleusers)) {
            $failedcount = count($failedexports);
            $this->log_export_summary(
                $selectedcount,
                $eligiblecount,
                0,
                $failedcount,
                max(0, $selectedcount - $failedcount)
            );
            throw new moodle_exception('nothingtoexport', 'assignfeedback_combinedpdf');
        }

        $requestdir = make_request_directory();
        $zipcontent = [];
        $unmerged = [];
        $conversionnotes = [];
        $completedcount = 0;

        foreach ($eligibleusers as $candidate) {
            $userid = (int) $candidate->userid;
            $label = (string) $candidate->label;
            $grade = $candidate->grade;

            try {
                $result = $this->build_student_pdf($userid, $grade, $requestdir);
                if (!$result) {
                    // The preflight and generation checks should normally agree. If the
                    // underlying data changes during this request, treat it as no longer
                    // exportable rather than as a technical failure.
                    $skipped[] = $label;
                    continue;
                }

                [$pdfpath, $filename, $extrafiles, $hasunmergedsource, $studentconversionnotes] = $result;
                $filename = $this->unique_export_filename($filename, $zipcontent);
                $zipcontent[$filename] = $pdfpath;
                foreach ($extrafiles as $extrafile) {
                    $extraname = $this->unique_export_filename($extrafile['filename'], $zipcontent);
                    $zipcontent[$extraname] = $extrafile['path'];
                }
                if ($hasunmergedsource) {
                    $unmerged[] = $label;
                }
                foreach ($studentconversionnotes as $note) {
                    $conversionnotes[] = $label . ': ' . $note;
                }
                $completedcount++;
            } catch (\Throwable $e) {
                $reference = $this->create_failure_reference();
                $failedexports[] = (object) [
                    'label' => $label,
                    'reference' => $reference,
                ];
                $this->log_student_export_failure($userid, $label, $reference, $e);
            }
        }

        $failedcount = count($failedexports);
        $skippedcount = max(0, $selectedcount - $completedcount - $failedcount);

        if (!empty($skipped) || !empty($unmerged) || !empty($conversionnotes) || !empty($failedexports)) {
            $message = get_string('readmeheading', 'assignfeedback_combinedpdf') . "\n\n";
            if ($skipped) {
                $message .= get_string('readmebody', 'assignfeedback_combinedpdf') . "\n\n";
                foreach ($skipped as $label) {
                    $message .= '- ' . $label . "\n";
                }
            }
            if ($failedexports) {
                $message .= "\n" . get_string('readmefailures', 'assignfeedback_combinedpdf') . "\n\n";
                foreach ($failedexports as $failure) {
                    $message .= '- ' . get_string('readmefailureentry', 'assignfeedback_combinedpdf', $failure) . "\n";
                }
            }
            if ($unmerged) {
                $message .= "\n" . get_string('readmeunmerged', 'assignfeedback_combinedpdf') . "\n\n";
                foreach ($unmerged as $label) {
                    $message .= '- ' . $label . "\n";
                }
            }
            if ($conversionnotes) {
                $message .= "\n" . get_string('readmeconversion', 'assignfeedback_combinedpdf') . "\n\n";
                foreach ($conversionnotes as $note) {
                    $message .= '- ' . $note . "\n";
                }
            }
            $zipcontent['README.txt'] = ['content_as_string' => $message];
        }

        if ($completedcount === 0 || empty($zipcontent)) {
            $this->log_export_summary(
                $selectedcount,
                $eligiblecount,
                0,
                $failedcount,
                $skippedcount
            );
            throw new moodle_exception('nothingtoexport', 'assignfeedback_combinedpdf');
        }

        $assignmentname = clean_filename($this->get_assignment_plain_name());
        if ($assignmentname === '') {
            $assignmentname = 'assignment';
        }

        $zipname = clean_filename($assignmentname . '-feedback.zip');
        $zippath = $requestdir . DIRECTORY_SEPARATOR . 'combined-feedback.zip';

        $packer = get_file_packer('application/zip');
        if (!$packer->archive_to_pathname($zipcontent, $zippath, false)) {
            throw new moodle_exception('cannotcreatezip', 'error');
        }

        $this->log_export_summary(
            $selectedcount,
            $eligiblecount,
            $completedcount,
            $failedcount,
            $skippedcount
        );
        send_temp_file($zippath, $zipname);
        die;
    }

    /**
     * Create a short correlation reference for a failed student export.
     *
     * @return string
     */
    private function create_failure_reference(): string {
        try {
            return 'CFP-' . strtoupper(bin2hex(random_bytes(6)));
        } catch (\Throwable $e) {
            $seed = microtime(true) . '|' . mt_rand() . '|' . spl_object_id($this);
            return 'CFP-' . strtoupper(substr(hash('sha256', $seed), 0, 12));
        }
    }

    /**
     * Record a failed student export in Moodle without exposing secrets or paths.
     *
     * @param int $userid Student whose export failed
     * @param string $label Privacy-safe student or participant label
     * @param string $reference Correlation reference shown in README.txt
     * @param \Throwable $exception Original exception
     * @return void
     */
    private function log_student_export_failure(
        int $userid,
        string $label,
        string $reference,
        \Throwable $exception
    ): void {
        $details = $this->get_safe_exception_details($exception);
        $relateduserid = $this->assignment->is_blind_marking() ? null : $userid;

        try {
            $event = \assignfeedback_combinedpdf\event\student_export_failed::create_for_failure(
                $this->assignment,
                $relateduserid,
                $label,
                $reference,
                $details
            );
            $event->trigger();
        } catch (\Throwable $loggingerror) {
            // Logging must never stop the remaining selected students from exporting.
            debugging(
                'Combined feedback export failure ' . $reference . ' could not be recorded: ' .
                    get_class($loggingerror),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Return technical exception details suitable for Moodle's restricted event log.
     *
     * Full paths, site URLs, query credentials and common secret values are removed.
     * The ZIP only receives the correlation reference, never these details.
     *
     * @param \Throwable $exception
     * @return array
     */
    private function get_safe_exception_details(\Throwable $exception): array {
        global $CFG;

        $message = trim((string) $exception->getMessage());
        $redactions = [];
        foreach (['dirroot', 'dataroot', 'tempdir', 'cachedir', 'localcachedir', 'wwwroot'] as $property) {
            if (!empty($CFG->{$property}) && is_string($CFG->{$property})) {
                $redactions[] = $CFG->{$property};
            }
        }
        usort($redactions, static fn($a, $b) => strlen($b) <=> strlen($a));
        foreach (array_unique($redactions) as $redaction) {
            $message = str_replace($redaction, '[redacted]', $message);
        }

        $message = preg_replace(
            '~\b(https?://)([^\s/@:]+):([^\s/@]+)@~i',
            '$1[redacted]@',
            $message
        ) ?? $message;
        $message = preg_replace(
            '/([?&](?:password|passwd|secret|token|apikey|api_key|sesskey)=)[^&\s]+/i',
            '$1[redacted]',
            $message
        ) ?? $message;
        $message = preg_replace(
            '/\b(password|passwd|secret|token|apikey|api_key|sesskey)\s*[:=]\s*[^\s,;]+/i',
            '$1=[redacted]',
            $message
        ) ?? $message;
        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;
        $message = trim(\core_text::substr($message, 0, 500));
        if ($message === '') {
            $message = get_class($exception);
        }

        return [
            'exceptionclass' => get_class($exception),
            'exceptioncode' => (string) $exception->getCode(),
            'detail' => $message,
            'source' => basename((string) $exception->getFile()),
            'line' => (int) $exception->getLine(),
        ];
    }

    /**
     * Record the processed export in Moodle's event log.
     *
     * @param int $selectedcount Number selected after Moodle access filtering
     * @param int $eligiblecount Number considered exportable during preflight
     * @param int $completedcount Number of feedback PDFs created
     * @param int $failedcount Number that failed unexpectedly
     * @param int $skippedcount Number with no package created for a non-error reason
     * @return void
     */
    private function log_export_summary(
        int $selectedcount,
        int $eligiblecount,
        int $completedcount,
        int $failedcount,
        int $skippedcount
    ): void {
        try {
            $event = \assignfeedback_combinedpdf\event\feedback_exported::create_for_export(
                $this->assignment,
                $selectedcount,
                $eligiblecount,
                $completedcount,
                $failedcount,
                $skippedcount
            );
            $event->trigger();
        } catch (\Throwable $e) {
            // A logging problem must not interrupt the export or discard any completed PDFs.
            debugging(
                'Combined feedback export was processed but its audit event could not be recorded: ' . get_class($e),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Return the assignment name after Moodle formatting, before HTML escaping.
     *
     * @return string
     */
    private function get_assignment_display_name(): string {
        return format_string($this->assignment->get_instance()->name, true, [
            'context' => $this->assignment->get_context(),
            'escape' => false,
        ]);
    }

    /**
     * Return a plain assignment name for PDF metadata and filenames.
     *
     * @return string
     */
    private function get_assignment_plain_name(): string {
        $name = html_entity_decode(strip_tags($this->get_assignment_display_name()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        return trim($name);
    }

    /**
     * Find the latest attempt's saved grade without creating submissions or grades.
     *
     * Moodle's get_user_grade() can create a submission when the attempt is omitted,
     * even when its $create argument is false.
     *
     * @param int $userid
     * @return stdClass|false
     */
    private function get_saved_grade($userid) {
        global $DB;

        if ($this->assignment->get_instance()->teamsubmission) {
            $submission = $this->assignment->get_group_submission($userid, 0, false);
        } else {
            $submission = $this->assignment->get_user_submission($userid, false);
        }

        if ($submission) {
            return $this->assignment->get_user_grade($userid, false, (int) $submission->attemptnumber);
        }

        $grades = $DB->get_records('assign_grades', [
            'assignment' => $this->assignment->get_instance()->id,
            'userid' => $userid,
        ], 'attemptnumber DESC, id DESC', '*', 0, 1);

        return $grades ? reset($grades) : false;
    }

    /**
     * Stop preflight as soon as more than the permitted number of exportable
     * students has been found.
     *
     * @param int $candidatecount
     * @return void
     */
    private function assert_within_export_limit(int $candidatecount): void {
        if ($candidatecount <= self::MAX_EXPORT_USERS) {
            return;
        }

        throw new moodle_exception('exportlimitexceeded', 'assignfeedback_combinedpdf', '', (object) [
            'max' => self::MAX_EXPORT_USERS,
            'selected' => $candidatecount,
        ]);
    }

    /**
     * Check whether one selected student has material that can produce a package.
     *
     * This deliberately avoids document conversion and PDF generation. The cached
     * lookups are reused later when the eligible package is built.
     *
     * @param int $userid
     * @param stdClass $grade
     * @return bool
     */
    private function has_exportable_content(int $userid, stdClass $grade): bool {
        if ($this->get_annotated_pdf($grade)) {
            return true;
        }

        if ($this->get_submission_files($userid, $grade)) {
            return true;
        }

        if ($this->get_feedback_comment($grade) !== null) {
            return true;
        }

        if (isset($grade->grade) && is_numeric($grade->grade) && (float) $grade->grade >= 0) {
            return true;
        }

        if ($this->has_advanced_grading_feedback($grade)) {
            return true;
        }

        return $this->get_marker_grading_html($grade) !== '';
    }

    /**
     * Check for a saved rubric or marking-guide instance without rendering it.
     *
     * @param stdClass $grade
     * @return bool
     */
    private function has_advanced_grading_feedback(stdClass $grade): bool {
        try {
            $cache = $this->get_advanced_grading_cache();
            if ($cache['method'] === '' || !$cache['definition'] || !$cache['criteria']) {
                return false;
            }

            return (bool) $this->get_grading_instance(
                (int) $cache['definition']->id,
                (int) $grade->id
            );
        } catch (\Throwable $e) {
            debugging(
                'Could not check advanced grading feedback eligibility: ' . get_class($e),
                DEBUG_DEVELOPER
            );
            return false;
        }
    }

    /**
     * Avoid overwriting another file when labels clean to the same filename.
     *
     * Numeric suffixes are deliberately independent of Moodle user IDs so that
     * blind-marking exports cannot disclose an identifier through a collision.
     *
     * @param string $filename
     * @param array $files
     * @return string
     */
    private function unique_export_filename(string $filename, array $files): string {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $extensionpart = $extension !== '' ? '.' . $extension : '';
        $candidate = $filename;
        $suffix = 2;
        while (array_key_exists($candidate, $files)) {
            $candidate = clean_filename($base . '-' . $suffix . $extensionpart);
            $suffix++;
        }
        return $candidate;
    }

    /**
     * Build the combined PDF for one student.
     *
     * @param int $userid
     * @param stdClass $grade
     * @param string $requestdir
     * @return array|null
     */
    private function build_student_pdf($userid, stdClass $grade, $requestdir) {
        $safeid = (int) $userid . '-' . (int) $grade->id;
        $summarypath = $requestdir . DIRECTORY_SEPARATOR . 'summary-' . $safeid . '.pdf';
        $combinedpath = $requestdir . DIRECTORY_SEPARATOR . 'feedback-' . $safeid . '.pdf';

        $annotated = $this->get_annotated_pdf($grade);
        $submissionfiles = $annotated ? [] : $this->get_submission_files($userid, $grade);
        $comment = $this->get_feedback_comment($grade);
        $advancedhtml = $this->get_advanced_grading_html($grade);
        $advancedhtml .= $this->get_marker_grading_html($grade);

        $hasgrade = isset($grade->grade) && is_numeric($grade->grade) && (float) $grade->grade >= 0;
        if (!$annotated && empty($submissionfiles) && $comment === null && $advancedhtml === '' && !$hasgrade) {
            return null;
        }

        $this->create_summary_pdf($userid, $grade, $comment, $advancedhtml, $summarypath);

        $sourcefiles = [];
        $extrafiles = [];
        $hasunmergedsource = false;
        $conversionnotes = [];
        $exportbase = clean_filename($this->get_export_label($userid));
        if ($exportbase === '') {
            $exportbase = 'user-' . (int) $userid;
        }

        if ($annotated) {
            $annotatedpath = $requestdir . DIRECTORY_SEPARATOR . 'annotated-' . $safeid . '.pdf';
            if (!$annotated->copy_content_to($annotatedpath)) {
                throw new moodle_exception('cannotcreatepdf', 'assignfeedback_combinedpdf');
            }
            $sourcefiles[] = [
                'path' => $annotatedpath,
                'filename' => clean_filename($exportbase . '-annotated.pdf'),
            ];
        } else {
            $index = 0;
            foreach ($submissionfiles as $submissionfile) {
                $index++;
                $originalname = clean_filename($submissionfile->get_filename());
                if ($originalname === '') {
                    $originalname = 'submission-' . $index;
                }
                $extension = strtolower(pathinfo($originalname, PATHINFO_EXTENSION));
                $submissionpath = $requestdir . DIRECTORY_SEPARATOR . 'submission-' . $safeid . '-' . $index;
                if ($extension !== '') {
                    $submissionpath .= '.' . $extension;
                }
                if (!$submissionfile->copy_content_to($submissionpath)) {
                    throw new moodle_exception('cannotcreatepdf', 'assignfeedback_combinedpdf');
                }

                $originalexport = [
                    'path' => $submissionpath,
                    'filename' => clean_filename($exportbase . '-submission-' . $originalname),
                ];
                $resolved = \assignfeedback_combinedpdf\local\submission_pdf::resolve($submissionfile);
                if ($resolved['status'] === 'original') {
                    $sourcefiles[] = $originalexport;
                } else if ($resolved['status'] === 'converted') {
                    $convertedpath = $requestdir . DIRECTORY_SEPARATOR . 'converted-' . $safeid . '-' . $index . '.pdf';
                    $copied = false;
                    try {
                        $copied = $resolved['file']->copy_content_to($convertedpath);
                    } catch (\Throwable $e) {
                        // Retain the original when the converted file cannot be retrieved.
                        $copied = false;
                    }
                    if ($copied && is_file($convertedpath) && filesize($convertedpath) > 0) {
                        $sourcefiles[] = [
                            'path' => $convertedpath,
                            'filename' => clean_filename($exportbase . '-submission-' . $originalname . '.pdf'),
                            'original' => $originalexport,
                        ];
                    } else {
                        $extrafiles[] = $originalexport;
                        $conversionnotes[] = get_string('conversionfailed', 'assignfeedback_combinedpdf', $originalname);
                    }
                } else {
                    $extrafiles[] = $originalexport;
                    $stringid = $resolved['status'] === 'pending' ? 'conversionpending' : 'conversionfailed';
                    $conversionnotes[] = get_string($stringid, 'assignfeedback_combinedpdf', $originalname);
                }
            }
        }

        if ($sourcefiles) {
            $olddebug = error_reporting();
            try {
                $this->merge_source_pdfs($sourcefiles, $summarypath, $combinedpath, !$annotated);
            } catch (\Throwable $e) {
                $hasunmergedsource = true;
                foreach ($sourcefiles as $sourcefile) {
                    $extrafiles[] = $sourcefile;
                    if (isset($sourcefile['original'])) {
                        $extrafiles[] = $sourcefile['original'];
                    }
                }
                if (!copy($summarypath, $combinedpath)) {
                    throw new moodle_exception('cannotcreatepdf', 'assignfeedback_combinedpdf');
                }
            } finally {
                // The core merger lowers error_reporting() and may throw before restoring it.
                error_reporting($olddebug);
            }
        } else {
            if (!copy($summarypath, $combinedpath)) {
                throw new moodle_exception('cannotcreatepdf', 'assignfeedback_combinedpdf');
            }
        }

        $filename = clean_filename($exportbase . '-feedback.pdf');
        if ($filename === '' || $filename === '-feedback.pdf') {
            $filename = 'user-' . (int) $userid . '-feedback.pdf';
        }

        return [$combinedpath, $filename, $extrafiles, $hasunmergedsource, $conversionnotes];
    }

    /**
     * Merge PDFs, retrying incompatible unannotated sources through Moodle's PDF normaliser.
     *
     * The retry works on temporary copies only. Saved annotated feedback is never
     * normalised here, because it must retain its existing annotation behaviour.
     *
     * @param array $sourcefiles Prepared PDF source descriptors
     * @param string $summarypath Feedback summary path
     * @param string $combinedpath Export destination
     * @param bool $allowcompatibilityretry Whether unannotated sources may be normalised
     * @return void
     */
    private function merge_source_pdfs(
        array $sourcefiles,
        string $summarypath,
        string $combinedpath,
        bool $allowcompatibilityretry
    ): void {
        global $CFG;

        $sourcepaths = array_column($sourcefiles, 'path');
        try {
            $this->combine_pdf_paths(array_merge($sourcepaths, [$summarypath]), $combinedpath);
            return;
        } catch (\Throwable $e) {
            if (!$allowcompatibilityretry || empty($CFG->pathtogs) || !file_is_executable($CFG->pathtogs)) {
                throw $e;
            }
        }

        // Some valid converter PDFs need the same compatibility pass as Annotate PDF.
        $compatiblepaths = [];
        foreach ($sourcepaths as $sourcepath) {
            $compatiblepath = \assignfeedback_editpdf\pdf::ensure_pdf_file_compatible($sourcepath);
            if (!$compatiblepath || !is_file($compatiblepath) || filesize($compatiblepath) === 0) {
                throw new moodle_exception('cannotcreatepdf', 'assignfeedback_combinedpdf');
            }
            $compatiblepaths[] = $compatiblepath;
        }
        $this->combine_pdf_paths(array_merge($compatiblepaths, [$summarypath]), $combinedpath);
    }

    /**
     * Combine prepared paths and restore PHP error reporting even on a parser exception.
     *
     * @param string[] $paths Source paths in page order
     * @param string $destination Output path
     * @return void
     */
    private function combine_pdf_paths(array $paths, string $destination): void {
        $olddebug = error_reporting();
        try {
            $merger = new \assignfeedback_editpdf\pdf();
            $pagecount = $merger->combine_pdfs($paths, $destination);
            clearstatcache(true, $destination);
            if ($pagecount < 1 || !is_file($destination) || filesize($destination) === 0) {
                throw new moodle_exception('cannotcreatepdf', 'assignfeedback_combinedpdf');
            }
        } finally {
            error_reporting($olddebug);
        }
    }

    /**
     * Return the existing Moodle Annotate PDF feedback file.
     *
     * The exporter deliberately does not generate or alter annotate-PDF data.
     *
     * @param stdClass $grade
     * @return stored_file|false
     */
    private function get_annotated_pdf(stdClass $grade) {
        $gradeid = (int) $grade->id;
        if (array_key_exists($gradeid, $this->annotatedpdfcache)) {
            return $this->annotatedpdfcache[$gradeid];
        }

        // Use the already checked grade ID. The document service may create grade/submission records.
        $files = get_file_storage()->get_area_files(
            $this->assignment->get_context()->id,
            'assignfeedback_editpdf',
            'download',
            $gradeid,
            'itemid, filepath, filename',
            false
        );
        $this->annotatedpdfcache[$gradeid] = $files ? reset($files) : false;
        return $this->annotatedpdfcache[$gradeid];
    }

    /**
     * Return the saved Assignment submission for the same attempt as the grade.
     *
     * @param int $userid
     * @param stdClass $grade
     * @return stdClass|false
     */
    private function get_submission_for_grade($userid, stdClass $grade) {
        $cachekey = (int) $userid . ':' . (int) $grade->id;
        if (array_key_exists($cachekey, $this->submissioncache)) {
            return $this->submissioncache[$cachekey];
        }

        $attemptnumber = isset($grade->attemptnumber) ? (int) $grade->attemptnumber : -1;
        if ($this->assignment->get_instance()->teamsubmission) {
            $submission = $this->assignment->get_group_submission($userid, 0, false, $attemptnumber);
        } else {
            $submission = $this->assignment->get_user_submission($userid, false, $attemptnumber);
        }

        $this->submissioncache[$cachekey] = $submission ?: false;
        return $this->submissioncache[$cachekey];
    }

    /**
     * Return the saved file-submission files for the same attempt as the grade.
     *
     * These are used only when no Annotate PDF download exists. PDF submissions can
     * be merged directly before the feedback summary. Other file types are resolved
     * through Moodle's conversion API and retained separately if conversion is not ready.
     *
     * @param int $userid
     * @param stdClass $grade
     * @return stored_file[]
     */
    private function get_submission_files($userid, stdClass $grade) {
        $cachekey = (int) $userid . ':' . (int) $grade->id;
        if (array_key_exists($cachekey, $this->submissionfilescache)) {
            return $this->submissionfilescache[$cachekey];
        }

        $submission = $this->get_submission_for_grade($userid, $grade);
        if (!$submission) {
            $this->submissionfilescache[$cachekey] = [];
            return [];
        }

        $this->submissionfilescache[$cachekey] = get_file_storage()->get_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_file',
            'submission_files',
            (int) $submission->id,
            'filepath, filename, id',
            false
        );
        return $this->submissionfilescache[$cachekey];
    }

    /**
     * Return the student's Assignment extension due date, when one has been granted.
     *
     * @param int $userid
     * @return int
     */
    private function get_extension_due_date($userid): int {
        global $DB;

        $extensionduedate = $DB->get_field('assign_user_flags', 'extensionduedate', [
            'assignment' => $this->assignment->get_instance()->id,
            'userid' => (int) $userid,
        ]);

        return ($extensionduedate && (int) $extensionduedate > 0) ? (int) $extensionduedate : 0;
    }

    /**
     * Check whether the optional Turnitin plagiarism integration is available.
     *
     * @return bool
     */
    private function is_turnitin_enabled(): bool {
        global $DB;

        if ($this->turnitinenabled !== null) {
            return $this->turnitinenabled;
        }

        if (empty(get_config('plagiarism_turnitin', 'plagiarism_turnitin_mod_assign'))) {
            $this->turnitinenabled = false;
            return false;
        }

        $manager = $DB->get_manager();
        if (
            !$manager->table_exists('plagiarism_turnitin_files')
                || !$manager->table_exists('plagiarism_turnitin_config')
        ) {
            $this->turnitinenabled = false;
            return false;
        }

        $usevalue = $DB->get_field('plagiarism_turnitin_config', 'value', [
            'cm' => (int) $this->assignment->get_course_module()->id,
            'name' => 'use_turnitin',
        ]);
        $this->turnitinenabled = !empty($usevalue);
        return $this->turnitinenabled;
    }

    /**
     * Read the locally cached Turnitin score for the current file submission.
     *
     * plagiarism_turnitin is optional. No Turnitin API request is made here: the
     * exporter only reads the score and external submission ID already stored by
     * the Turnitin plagiarism plugin. When several current files have reports, the
     * highest similarity is used for the compact summary.
     *
     * @param stdClass|false $submission
     * @return stdClass|null
     */
    private function get_turnitin_summary($submission): ?stdClass {
        global $DB;

        if (!$submission || !$this->is_turnitin_enabled()) {
            return null;
        }

        $cmid = (int) $this->assignment->get_course_module()->id;

        $files = get_file_storage()->get_area_files(
            $this->assignment->get_context()->id,
            'assignsubmission_file',
            'submission_files',
            (int) $submission->id,
            'filepath, filename, id',
            false
        );
        if (!$files) {
            return null;
        }

        $identifiers = [];
        foreach ($files as $file) {
            $identifiers[] = $file->get_pathnamehash();
        }
        $identifiers = array_values(array_unique(array_filter($identifiers)));
        if (!$identifiers) {
            return null;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($identifiers, SQL_PARAMS_QM, 'tiiidentifier');
        $params = array_merge([
            $cmid,
            (int) $submission->id,
        ], $inparams);
        $records = $DB->get_records_select(
            'plagiarism_turnitin_files',
            'cm = ? AND itemid = ? AND identifier ' . $insql,
            $params,
            'id DESC',
            'id,identifier,externalid,statuscode,similarityscore,orcapable,transmatch,lastmodified,submissiontype,errorcode'
        );

        $candidates = [];
        foreach ($records as $record) {
            if ((string) $record->submissiontype !== 'file') {
                continue;
            }

            $hasscore = is_numeric($record->similarityscore);
            $status = strtolower((string) $record->statuscode);
            $hasexternalid = $record->externalid !== null && trim((string) $record->externalid) !== '';
            $canbepending = $hasexternalid && in_array($status, ['queued', 'pending', 'success'], true);
            if (!$hasscore && !$canbepending) {
                continue;
            }
            if (!$hasscore && $record->orcapable !== null && (int) $record->orcapable === 0) {
                continue;
            }
            $candidates[] = $record;
        }
        if (!$candidates) {
            return null;
        }

        usort($candidates, static function ($a, $b) {
            $ascore = is_numeric($a->similarityscore) ? (int) $a->similarityscore : -1;
            $bscore = is_numeric($b->similarityscore) ? (int) $b->similarityscore : -1;
            if ($ascore !== $bscore) {
                return $bscore <=> $ascore;
            }
            return (int) $b->id <=> (int) $a->id;
        });

        $summary = clone $candidates[0];
        $summary->filecount = count($candidates);
        return $summary;
    }

    /**
     * Render a compact Turnitin-style similarity badge for TCPDF.
     *
     * The thresholds and colours mirror plagiarism_turnitin's score_colour classes:
     * 0 blue, 1-25 green, 26-50 yellow, 51-75 orange, 76-100 red.
     *
     * @param mixed $score
     * @return string
     */
    private function render_turnitin_similarity_badge($score): string {
        if ($score === null || !is_numeric($score)) {
            $background = '#999999';
            $foreground = '#000000';
            $label = get_string('similaritypending', 'assignfeedback_combinedpdf');
        } else {
            $score = max(0, min(100, (int) $score));
            $label = $score . '%';
            if ($score === 0) {
                $background = '#4EA3F0';
                $foreground = '#FFFFFF';
            } else if ($score <= 25) {
                $background = '#70C30F';
                $foreground = '#000000';
            } else if ($score <= 50) {
                $background = '#FFE225';
                $foreground = '#000000';
            } else if ($score <= 75) {
                $background = '#FFA400';
                $foreground = '#000000';
            } else {
                $background = '#F03A3A';
                $foreground = '#FFFFFF';
            }
        }

        return '<table cellpadding="3" cellspacing="0" border="0" width="24%">'
            . '<tr><td align="center" bgcolor="' . $background . '" style="color:' . $foreground . ';">'
            . '<strong>' . s($label) . '</strong></td></tr></table>';
    }

    /**
     * Get the saved Feedback comments value.
     *
     * @param stdClass $grade
     * @return stdClass|null
     */
    private function get_feedback_comment(stdClass $grade) {
        global $DB;

        $gradeid = (int) $grade->id;
        if (array_key_exists($gradeid, $this->feedbackcommentcache)) {
            return $this->feedbackcommentcache[$gradeid];
        }

        if (!$this->assignment->get_feedback_plugin_by_type('comments')) {
            $this->feedbackcommentcache[$gradeid] = null;
            return null;
        }

        $record = $DB->get_record('assignfeedback_comments', [
            'assignment' => $this->assignment->get_instance()->id,
            'grade' => $gradeid,
        ], 'id,commenttext,commentformat', IGNORE_MISSING);

        if (!$record || trim((string) $record->commenttext) === '') {
            $this->feedbackcommentcache[$gradeid] = null;
            return null;
        }

        $this->feedbackcommentcache[$gradeid] = $record;
        return $record;
    }

    /**
     * Create the appended feedback/rubric page(s).
     *
     * @param int $userid
     * @param stdClass $grade
     * @param stdClass|null $comment
     * @param string $advancedhtml
     * @param string $path
     * @return void
     */
    private function create_summary_pdf($userid, stdClass $grade, $comment, $advancedhtml, $path) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $context = $this->assignment->get_context();
        $assignment = $this->assignment->get_instance();
        $assignmentdisplayname = $this->get_assignment_display_name();
        $submission = $this->get_submission_for_grade($userid, $grade);
        $turnitin = $this->get_turnitin_summary($submission);
        $extensionduedate = $this->get_extension_due_date($userid);

        $html = '<h1>' . s(get_string('pluginname', 'assignfeedback_combinedpdf')) . '</h1>';
        $html .= '<table cellpadding="5" cellspacing="0" border="0" width="100%">';
        $html .= '<tr><td width="25%"><strong>' . s(get_string('student', 'assignfeedback_combinedpdf')) . '</strong></td>';
        $html .= '<td width="75%">' . s($this->get_export_label($userid)) . '</td></tr>';
        $html .= '<tr><td><strong>' . s(get_string('assignment', 'assignfeedback_combinedpdf')) . '</strong></td>';
        $html .= '<td>' . s($assignmentdisplayname) . '</td></tr>';

        if (isset($grade->grade) && is_numeric($grade->grade) && (float) $grade->grade >= 0) {
            $html .= '<tr><td><strong>' . s(get_string('grade', 'assignfeedback_combinedpdf')) . '</strong></td>';
            $html .= '<td>' . s($this->format_grade_for_export($grade)) . '</td></tr>';
        }

        if ($turnitin) {
            $html .= '<tr><td><strong>' . s(get_string('overallsimilarity', 'assignfeedback_combinedpdf')) . '</strong></td>';
            $html .= '<td>' . $this->render_turnitin_similarity_badge($turnitin->similarityscore) . '</td></tr>';
            if ($turnitin->externalid !== null && trim((string) $turnitin->externalid) !== '') {
                $html .= '<tr><td><strong>' . s(get_string('turnitinsubmissionid', 'assignfeedback_combinedpdf'))
                    . '</strong></td>';
                $html .= '<td>' . s((string) $turnitin->externalid) . '</td></tr>';
            }
        }

        if ($submission && !empty($submission->timemodified)) {
            $html .= '<tr><td><strong>' . s(get_string('submissiondate', 'assignfeedback_combinedpdf')) . '</strong></td>';
            $html .= '<td>' . s(userdate($submission->timemodified)) . '</td></tr>';
        }

        if ($extensionduedate > 0) {
            $html .= '<tr><td><strong>' . s(get_string('extensionuntil', 'assignfeedback_combinedpdf')) . '</strong></td>';
            $html .= '<td>' . s(userdate($extensionduedate)) . '</td></tr>';
        }
        $html .= '</table>';

        $html .= '<h2>' . s(get_string('feedbackheading', 'assignfeedback_combinedpdf')) . '</h2>';
        if ($comment) {
            $commenthtml = format_text(
                $comment->commenttext,
                $comment->commentformat,
                ['context' => $context, 'para' => false, 'filter' => false]
            );
            $commenthtml = $this->make_pdf_safe_html($commenthtml);
            $html .= $commenthtml;
        } else {
            $html .= '<p><em>' . s(get_string('nofeedbackcomment', 'assignfeedback_combinedpdf')) . '</em></p>';
        }

        if ($advancedhtml !== '') {
            $html .= $advancedhtml;
        }

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Moodle');
        $pdf->SetTitle($this->get_assignment_plain_name() . ' - ' .
            get_string('pluginname', 'assignfeedback_combinedpdf'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('freesans', '', 10);
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($path, 'F');
    }

    /**
     * Include saved marks from Moodle 5.2's currently allocated markers.
     *
     * The overall Assignment grade remains separate and is never recalculated here.
     * Marker identities are not added to the export.
     *
     * @param stdClass $grade
     * @return string
     */
    private function get_marker_grading_html(stdClass $grade) {
        $gradeid = (int) $grade->id;
        if (array_key_exists($gradeid, $this->markergradinghtmlcache)) {
            return $this->markergradinghtmlcache[$gradeid];
        }

        if (!$this->assignment->is_using_multiple_marking()) {
            $this->markergradinghtmlcache[$gradeid] = '';
            return '';
        }

        $marks = $this->assignment->get_mark_records($gradeid, (int) $grade->userid);
        $rows = '';
        $position = 0;
        foreach ($marks as $mark) {
            if (!isset($mark->mark) || !is_numeric($mark->mark) || (float) $mark->mark < 0) {
                continue;
            }
            $position++;
            $markgrade = (object) ['grade' => $mark->mark];
            $rows .= '<tr><td>' . s(get_string('markerlabel', 'assignfeedback_combinedpdf', $position)) . '</td>';
            $rows .= '<td>' . s($this->format_grade_for_export($markgrade)) . '</td>';
            $rows .= '<td>' . (!empty($mark->timemodified) ? s(userdate($mark->timemodified)) : '') . '</td></tr>';
        }

        if ($rows === '') {
            $this->markergradinghtmlcache[$gradeid] = '';
            return '';
        }

        $html = '<h2>' . s(get_string('markerstitle', 'assignfeedback_combinedpdf')) . '</h2>';
        $html .= '<table cellpadding="5" cellspacing="0" border="1" width="100%">';
        $html .= '<tr><th>' . s(get_string('marker', 'assignfeedback_combinedpdf')) . '</th>';
        $html .= '<th>' . s(get_string('score', 'assignfeedback_combinedpdf')) . '</th>';
        $html .= '<th>' . s(get_string('gradeddate', 'assignfeedback_combinedpdf')) . '</th></tr>';
        $this->markergradinghtmlcache[$gradeid] = $html . $rows . '</table>';
        return $this->markergradinghtmlcache[$gradeid];
    }

    /**
     * Keep editor HTML simple enough for TCPDF and avoid authenticated media URLs.
     *
     * @param string $html
     * @return string
     */
    private function make_pdf_safe_html(string $html): string {
        $replacement = '<em>[' . s(get_string('embeddedmedianote', 'assignfeedback_combinedpdf')) . ']</em>';
        $html = preg_replace('/<img\b[^>]*>/i', $replacement, $html) ?? $html;
        $html = preg_replace(
            '/<(video|audio|iframe|object|svg)\b[^>]*>.*?<\/\1>/is',
            $replacement,
            $html
        ) ?? $html;
        $html = preg_replace('/<embed\b[^>]*>/i', $replacement, $html) ?? $html;
        $html = preg_replace('/<(?:script|style)\b[^>]*>.*?<\/(?:script|style)>/is', '', $html) ?? $html;
        $html = preg_replace('/<link\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/url\s*\([^)]*\)/i', 'none', $html) ?? $html;
        return $html;
    }

    /**
     * Render the current saved rubric or marking guide.
     *
     * Definition data, rubric criteria and rubric levels are cached once for
     * this export request. Student-specific fillings are fetched in one query.
     *
     * @param stdClass $grade
     * @return string
     */
    private function get_advanced_grading_html(stdClass $grade): string {
        try {
            $cache = $this->get_advanced_grading_cache();
            if ($cache['method'] === '' || !$cache['definition']) {
                return '';
            }

            $instance = $this->get_grading_instance(
                (int) $cache['definition']->id,
                (int) $grade->id
            );
            if (!$instance) {
                return '';
            }

            if ($cache['method'] === 'rubric') {
                return $this->render_rubric_html(
                    $instance,
                    $cache['criteria'],
                    $cache['levelsbycriterion']
                );
            }

            return $this->render_guide_html($instance, $cache['criteria']);
        } catch (\Throwable $e) {
            // Match Moodle's existing graceful behaviour: a damaged advanced-grading
            // record must not prevent the rest of the student's feedback from exporting.
            debugging(
                'Could not render advanced grading feedback: ' . get_class($e),
                DEBUG_DEVELOPER
            );
            return '';
        }
    }

    /**
     * Load static advanced-grading data once for the whole request.
     *
     * @return array{method: string, definition: stdClass|null, criteria: array, levelsbycriterion: array}
     */
    private function get_advanced_grading_cache(): array {
        global $DB;

        if ($this->advancedgradingcache !== null) {
            return $this->advancedgradingcache;
        }

        $cache = [
            'method' => '',
            'definition' => null,
            'criteria' => [],
            'levelsbycriterion' => [],
        ];

        try {
            $manager = get_grading_manager(
                $this->assignment->get_context(),
                'mod_assign',
                'submissions'
            );
            $method = $manager->get_active_method();
            if ($method !== 'rubric' && $method !== 'guide') {
                $this->advancedgradingcache = $cache;
                return $cache;
            }

            $controller = $manager->get_active_controller();
            if (!$controller) {
                $this->advancedgradingcache = $cache;
                return $cache;
            }

            $definition = $controller->get_definition();
            if (!$definition || empty($definition->id)) {
                $this->advancedgradingcache = $cache;
                return $cache;
            }

            $cache['method'] = $method;
            $cache['definition'] = $definition;

            if ($method === 'rubric') {
                $criteria = $DB->get_records(
                    'gradingform_rubric_criteria',
                    ['definitionid' => $definition->id],
                    'sortorder ASC, id ASC'
                );
                $cache['criteria'] = $criteria ?: [];

                if ($criteria) {
                    [$insql, $params] = $DB->get_in_or_equal(
                        array_keys($criteria),
                        SQL_PARAMS_NAMED,
                        'criterion'
                    );
                    $levels = $DB->get_records_select(
                        'gradingform_rubric_levels',
                        'criterionid ' . $insql,
                        $params,
                        'criterionid ASC, score ASC, id ASC'
                    );
                    foreach ($levels as $level) {
                        $criterionid = (int) $level->criterionid;
                        $cache['levelsbycriterion'][$criterionid] ??= [];
                        $cache['levelsbycriterion'][$criterionid][] = $level;
                    }
                }
            } else {
                $criteria = $DB->get_records(
                    'gradingform_guide_criteria',
                    ['definitionid' => $definition->id],
                    'sortorder ASC, id ASC'
                );
                $cache['criteria'] = $criteria ?: [];
            }

            $this->advancedgradingcache = $cache;
            return $cache;
        } catch (\Throwable $e) {
            // Cache an empty result so a global definition problem is not queried again
            // for every selected student. Student feedback can still export without it.
            $this->advancedgradingcache = $cache;
            debugging(
                'Could not load advanced grading definition: ' . get_class($e),
                DEBUG_DEVELOPER
            );
            return $cache;
        }
    }

    /**
     * Return the preferred grading instance for one saved grade in a single query.
     *
     * Active and needs-update instances take priority over an incomplete draft.
     *
     * @param int $definitionid
     * @param int $gradeid
     * @return stdClass|false
     */
    private function get_grading_instance(int $definitionid, int $gradeid) {
        global $DB;

        $cachekey = $definitionid . ':' . $gradeid;
        if (array_key_exists($cachekey, $this->gradinginstancecache)) {
            return $this->gradinginstancecache[$cachekey];
        }

        $statuses = [
            \gradingform_instance::INSTANCE_STATUS_ACTIVE,
            \gradingform_instance::INSTANCE_STATUS_NEEDUPDATE,
            \gradingform_instance::INSTANCE_STATUS_INCOMPLETE,
        ];
        [$statussql, $params] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'status');
        $params['definitionid'] = $definitionid;
        $params['itemid'] = $gradeid;

        $instances = $DB->get_records_select(
            'grading_instances',
            'definitionid = :definitionid AND itemid = :itemid AND status ' . $statussql,
            $params,
            'timemodified DESC, id DESC'
        );
        if (!$instances) {
            $this->gradinginstancecache[$cachekey] = false;
            return false;
        }

        $incomplete = false;
        foreach ($instances as $instance) {
            $status = (int) $instance->status;
            if (
                $status === (int) \gradingform_instance::INSTANCE_STATUS_ACTIVE
                    || $status === (int) \gradingform_instance::INSTANCE_STATUS_NEEDUPDATE
            ) {
                $this->gradinginstancecache[$cachekey] = $instance;
                return $instance;
            }
            if (!$incomplete && $status === (int) \gradingform_instance::INSTANCE_STATUS_INCOMPLETE) {
                $incomplete = $instance;
            }
        }

        $this->gradinginstancecache[$cachekey] = $incomplete;
        return $incomplete;
    }

    /**
     * Render rubric data as PDF-friendly HTML.
     *
     * @param stdClass $instance
     * @param stdClass[] $criteria Cached rubric criteria
     * @param array $levelsbycriterion Cached levels indexed by criterion ID
     * @return string
     */
    private function render_rubric_html(
        stdClass $instance,
        array $criteria,
        array $levelsbycriterion
    ): string {
        global $DB;

        if (!$criteria) {
            return '';
        }

        $fillings = $DB->get_records(
            'gradingform_rubric_fillings',
            ['instanceid' => $instance->id],
            'criterionid ASC, id ASC'
        );
        $fillingsbycriterion = [];
        foreach ($fillings as $filling) {
            $fillingsbycriterion[(int) $filling->criterionid] = $filling;
        }

        $context = $this->assignment->get_context();
        $html = '<h2>' . s(get_string('rubricheading', 'assignfeedback_combinedpdf')) . '</h2>';
        $position = 0;

        foreach ($criteria as $criterion) {
            $position++;
            $criterionid = (int) $criterion->id;
            $criteriontext = format_text(
                $criterion->description,
                $criterion->descriptionformat,
                ['context' => $context, 'para' => false, 'filter' => false]
            );
            $criteriontext = $this->make_pdf_safe_html($criteriontext);

            // Only repeat compact descriptions. Long/rich descriptions remain intact
            // in a normal body row, so a tall repeated header cannot consume the page.
            $repeatdescription = strlen(trim(strip_tags($criteriontext))) <= 700
                && preg_match_all('/<(?:br|p|div|h[1-6]|li|tr)\b/i', $criteriontext) <= 4
                && !preg_match('/<(?:table|ul|ol)\b/i', $criteriontext);
            $heading = '<span style="font-size:11pt;"><strong>'
                . s(get_string('criterionnumber', 'assignfeedback_combinedpdf', $position)) . '</strong></span>';
            if ($repeatdescription) {
                $heading .= '<br />' . $criteriontext;
            }

            // TCPDF supports table headers, cell colours and non-breaking rows.
            // Never make the whole criterion non-breaking: long rubrics must flow.
            $html .= '<table cellpadding="7" cellspacing="0" border="0" width="100%">';
            $html .= '<thead><tr><td colspan="2" bgcolor="#ECEFF2" '
                . 'style="font-size:10pt;color:#202830;border-top:1pt solid #7F8C98;'
                . 'border-bottom:0.5pt solid #BAC3CC;">' . $heading . '</td></tr>';
            $html .= '<tr><th width="86%" align="left" bgcolor="#F7F8FA" '
                . 'style="border-bottom:0.5pt solid #CBD2D9;"><strong>'
                . s(get_string('leveldescription', 'assignfeedback_combinedpdf')) . '</strong></th>';
            $html .= '<th width="14%" align="right" bgcolor="#F7F8FA" '
                . 'style="border-bottom:0.5pt solid #CBD2D9;"><strong>'
                . s(get_string('score', 'assignfeedback_combinedpdf')) . '</strong></th></tr></thead>';

            if (!$repeatdescription) {
                $html .= '<tr><td colspan="2" bgcolor="#F7F8FA" '
                    . 'style="border-bottom:0.5pt solid #CBD2D9;"><strong>'
                    . s(get_string('criteriondescription', 'assignfeedback_combinedpdf'))
                    . '</strong><br />' . $criteriontext . '</td></tr>';
            }

            $levels = $levelsbycriterion[$criterionid] ?? [];
            $filling = $fillingsbycriterion[$criterionid] ?? false;

            foreach ($levels as $level) {
                $leveltext = format_text(
                    $level->definition,
                    $level->definitionformat,
                    ['context' => $context, 'para' => false, 'filter' => false]
                );
                $leveltext = $this->make_pdf_safe_html($leveltext);
                $isselected = $filling && (int) $filling->levelid === (int) $level->id;
                $background = $isselected ? '#E8F1FA' : '#FFFFFF';
                $border = $isselected ? 'border:1pt solid #7E95AA;' : 'border-bottom:0.4pt solid #CBD2D9;';
                $score = s(format_float($level->score, 2));
                $selectedlabel = '';
                if ($isselected) {
                    $selectedlabel = '<strong>' . s(get_string('selectedlevel', 'assignfeedback_combinedpdf'))
                        . '</strong><br />';
                    $score = '<strong>' . $score . '</strong>';
                }

                // Keep ordinary short rows together. Long or structurally rich
                // descriptors must be allowed to flow across a page boundary.
                $plainlevel = trim(strip_tags($leveltext));
                $keeptogether = \core_text::strlen($plainlevel) <= 800
                    && !preg_match('/<(?:table|ul|ol|div|h[1-6]|li)\b/i', $leveltext);
                $rowattribute = $keeptogether ? ' nobr="true"' : '';
                $html .= '<tr' . $rowattribute . '><td width="86%" bgcolor="' . $background . '" '
                    . 'style="color:#202830;' . $border . '">' . $selectedlabel . $leveltext . '</td>';
                $html .= '<td width="14%" align="right" valign="middle" bgcolor="' . $background . '" '
                    . 'style="color:#202830;vertical-align:middle;' . $border . '">' . $score . '</td></tr>';
            }

            // Ensure even an unfinished, empty criterion has a table body.
            if (!$levels && $repeatdescription && (!$filling || trim((string) $filling->remark) === '')) {
                $html .= '<tr><td colspan="2">&nbsp;</td></tr>';
            }

            if ($filling && trim((string) $filling->remark) !== '') {
                $remark = format_text(
                    $filling->remark,
                    $filling->remarkformat,
                    ['context' => $context, 'para' => false, 'filter' => false]
                );
                $remark = $this->make_pdf_safe_html($remark);
                // A full-width row separates personalised comments from level wording.
                // Do not force long marker comments onto a single page.
                $html .= '<tr><td colspan="2" bgcolor="#F7F8FA" '
                    . 'style="border-top:1pt solid #AAB5BF;border-bottom:0.5pt solid #CBD2D9;">'
                    . '<strong>' . s(get_string('markingremark', 'assignfeedback_combinedpdf'))
                    . '</strong><br />' . $remark . '</td></tr>';
            }
            $html .= '</table><br /><br />';
        }

        if (isset($instance->rawgrade) && $instance->rawgrade !== null) {
            $html .= '<p><strong>' . s(get_string('score', 'assignfeedback_combinedpdf')) . ': ' .
                s(format_float($instance->rawgrade, 2)) . '</strong></p>';
        }

        return $html;
    }

    /**
     * Render marking guide data as PDF-friendly HTML.
     *
     * @param stdClass $instance
     * @param stdClass[] $criteria Cached guide criteria
     * @return string
     */
    private function render_guide_html(stdClass $instance, array $criteria): string {
        global $DB;

        if (!$criteria) {
            return '';
        }

        $fillings = $DB->get_records(
            'gradingform_guide_fillings',
            ['instanceid' => $instance->id],
            'criterionid ASC, id ASC'
        );
        $fillingsbycriterion = [];
        foreach ($fillings as $filling) {
            $fillingsbycriterion[(int) $filling->criterionid] = $filling;
        }

        $context = $this->assignment->get_context();
        $html = '<h2>' . s(get_string('guideheading', 'assignfeedback_combinedpdf')) . '</h2>';

        foreach ($criteria as $criterion) {
            $filling = $fillingsbycriterion[(int) $criterion->id] ?? false;
            $shortname = format_string($criterion->shortname, true, [
                'context' => $context,
                'escape' => false,
            ]);
            $description = format_text(
                $criterion->description,
                $criterion->descriptionformat,
                ['context' => $context, 'para' => false, 'filter' => false]
            );
            $description = $this->make_pdf_safe_html($description);

            $html .= '<table cellpadding="5" cellspacing="0" border="1" width="100%">';
            $html .= '<tr><td><strong>' . s($shortname) . '</strong><br>' . $description . '</td></tr>';

            if ($filling) {
                if (isset($filling->score) && $filling->score !== null) {
                    $html .= '<tr><td><strong>' . s(get_string('score', 'assignfeedback_combinedpdf')) . ': ' .
                        s(format_float($filling->score, 2)) . ' / ' .
                        s(format_float($criterion->maxscore, 2)) . '</strong></td></tr>';
                }

                if (trim((string) $filling->remark) !== '') {
                    $remark = format_text(
                        $filling->remark,
                        $filling->remarkformat,
                        ['context' => $context, 'para' => false, 'filter' => false]
                    );
                    $remark = $this->make_pdf_safe_html($remark);
                    $html .= '<tr><td><strong>' . s(get_string('markingremark', 'assignfeedback_combinedpdf')) .
                        '</strong><br>' . $remark . '</td></tr>';
                }
            }
            $html .= '</table><br>';
        }

        if (isset($instance->rawgrade) && $instance->rawgrade !== null) {
            $html .= '<p><strong>' . s(get_string('score', 'assignfeedback_combinedpdf')) . ': ' .
                s(format_float($instance->rawgrade, 2)) . '</strong></p>';
        }

        return $html;
    }

    /**
     * Format the Assignment grade for the summary page.
     *
     * @param stdClass $grade
     * @return string
     */
    private function format_grade_for_export(stdClass $grade) {
        $assignment = $this->assignment->get_instance();
        $value = (float) $grade->grade;

        if ((float) $assignment->grade > 0) {
            return format_float($value, 2) . ' / ' . format_float($assignment->grade, 2);
        }

        if ((float) $assignment->grade < 0) {
            $options = make_grades_menu($assignment->grade);
            $index = (int) $value;
            if (isset($options[$index])) {
                return $options[$index];
            }
        }

        return format_float($value, 2);
    }

    /**
     * Return a privacy-conscious filename/display label.
     *
     * @param int $userid
     * @return string
     */
    private function get_export_label($userid) {
        global $DB;

        $userid = (int) $userid;
        if (array_key_exists($userid, $this->exportlabels)) {
            return $this->exportlabels[$userid];
        }

        if ($this->assignment->is_blind_marking()) {
            $mapping = $DB->get_record('assign_user_mapping', [
                'assignment' => $this->assignment->get_instance()->id,
                'userid' => $userid,
            ], 'id', IGNORE_MISSING);
            if ($mapping) {
                $label = get_string('participant', 'assign') . ' ' . $mapping->id;
                $this->exportlabels[$userid] = $label;
                return $label;
            }
            // Do not create core mappings or expose user IDs during a feedback download.
            $this->unmappedlabels[$userid] ??= count($this->unmappedlabels) + 1;
            $label = get_string('unmappedparticipant', 'assignfeedback_combinedpdf', $this->unmappedlabels[$userid]);
            $this->exportlabels[$userid] = $label;
            return $label;
        }

        $fields = 'id,' . implode(',', \core_user\fields::get_name_fields());
        $user = $DB->get_record('user', ['id' => $userid], $fields, MUST_EXIST);
        $label = fullname($user);
        $label = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $label) ?? $label;
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        $label = trim($label);
        if ($label === '') {
            $label = get_string('selectedstudentreference', 'assignfeedback_combinedpdf', 1);
        }
        $this->exportlabels[$userid] = $label;
        return $label;
    }
}
