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
 * Event raised when a combined feedback export is processed.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace assignfeedback_combinedpdf\event;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Retain an explicit guard for this plugin entry point.
defined('MOODLE_INTERNAL') || die();

/**
 * A selected-student combined feedback export was processed.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class feedback_exported extends \core\event\base {
    /**
     * Create the event for an Assignment export.
     *
     * @param \assign $assign Assignment instance
     * @param int $selectedcount Number selected after Moodle access filtering
     * @param int $eligiblecount Number considered exportable during preflight
     * @param int $completedcount Number of feedback PDFs created
     * @param int $failedcount Number that failed unexpectedly
     * @param int $skippedcount Number with no package for a non-error reason
     * @return self
     */
    public static function create_for_export(
        \assign $assign,
        int $selectedcount,
        int $eligiblecount,
        int $completedcount,
        int $failedcount,
        int $skippedcount
    ): self {
        return self::create([
            'context' => $assign->get_context(),
            'objectid' => $assign->get_instance()->id,
            'other' => [
                'selectedcount' => $selectedcount,
                'eligiblecount' => $eligiblecount,
                'completedcount' => $completedcount,
                'failedcount' => $failedcount,
                'skippedcount' => $skippedcount,
            ],
        ]);
    }

    /**
     * Initialise the event metadata.
     *
     * @return void
     */
    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'assign';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('eventfeedbackexported', 'assignfeedback_combinedpdf');
    }

    /**
     * Return the non-localised event description.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        return "The user with id '$this->userid' processed a combined feedback export for assignment id " .
            "'$this->objectid' in course module id '$this->contextinstanceid'. Selected: " .
            "'{$this->other['selectedcount']}', eligible at preflight: '{$this->other['eligiblecount']}', " .
            "packages created: '{$this->other['completedcount']}', unexpected failures: " .
            "'{$this->other['failedcount']}', skipped: '{$this->other['skippedcount']}'.";
    }

    /**
     * Return the related Assignment grading-table URL.
     *
     * @return \moodle_url
     */
    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/assign/view.php', [
            'id' => $this->contextinstanceid,
            'action' => 'grading',
        ]);
    }

    /**
     * Validate event counts.
     *
     * @return void
     */
    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();

        $fields = ['selectedcount', 'eligiblecount', 'completedcount', 'failedcount', 'skippedcount'];
        foreach ($fields as $field) {
            if (
                !array_key_exists($field, $this->other) || !is_numeric($this->other[$field])
                    || (int) $this->other[$field] < 0
            ) {
                throw new \coding_exception("other['{$field}'] must be a non-negative integer");
            }
        }

        $selected = (int) $this->other['selectedcount'];
        $eligible = (int) $this->other['eligiblecount'];
        $completed = (int) $this->other['completedcount'];
        $failed = (int) $this->other['failedcount'];
        $skipped = (int) $this->other['skippedcount'];

        if ($eligible > $selected) {
            throw new \coding_exception('eligiblecount must not exceed selectedcount');
        }
        if ($completed + $failed > $eligible) {
            throw new \coding_exception('completedcount plus failedcount must not exceed eligiblecount');
        }
        if ($completed + $failed + $skipped !== $selected) {
            throw new \coding_exception('completedcount plus failedcount plus skippedcount must equal selectedcount');
        }
    }

    /**
     * Return the restore mapping for the Assignment object ID.
     *
     * @return array
     */
    #[\Override]
    public static function get_objectid_mapping(): array {
        return ['db' => 'assign', 'restore' => 'assign'];
    }
}
