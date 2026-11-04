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
 * Event raised when one selected student export fails.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace assignfeedback_combinedpdf\event;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Retain an explicit guard for this plugin entry point.
defined('MOODLE_INTERNAL') || die();

/**
 * One selected student's feedback package could not be created.
 *
 * The event stores a sanitised diagnostic message for administrators. The ZIP
 * receives only the short correlation reference.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class student_export_failed extends \core\event\base {
    /**
     * Create a failure event.
     *
     * @param \assign $assign Assignment instance
     * @param int|null $relateduserid Student whose package failed, omitted during blind marking
     * @param string $studentlabel Privacy-safe student or participant label
     * @param string $reference Correlation reference included in README.txt
     * @param array $details Sanitised technical details
     * @return self
     */
    public static function create_for_failure(
        \assign $assign,
        ?int $relateduserid,
        string $studentlabel,
        string $reference,
        array $details
    ): self {
        $data = [
            'context' => $assign->get_context(),
            'objectid' => $assign->get_instance()->id,
            'other' => [
                'studentlabel' => $studentlabel,
                'reference' => $reference,
                'exceptionclass' => (string) ($details['exceptionclass'] ?? ''),
                'exceptioncode' => (string) ($details['exceptioncode'] ?? ''),
                'detail' => (string) ($details['detail'] ?? ''),
                'source' => (string) ($details['source'] ?? ''),
                'line' => (int) ($details['line'] ?? 0),
            ],
        ];
        if ($relateduserid !== null && $relateduserid > 0) {
            $data['relateduserid'] = $relateduserid;
        }

        return self::create($data);
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
        return get_string('eventstudentexportfailed', 'assignfeedback_combinedpdf');
    }

    /**
     * Return the non-localised event description.
     *
     * @return string
     */
    #[\Override]
    public function get_description(): string {
        $subject = !empty($this->relateduserid)
            ? "user id '$this->relateduserid'"
            : "'{$this->other['studentlabel']}'";

        return "The user with id '$this->userid' could not export combined feedback for {$subject} " .
            "in assignment id '$this->objectid' and course module id '$this->contextinstanceid'. " .
            "Reference '{$this->other['reference']}'. " .
            "Exception '{$this->other['exceptionclass']}' with code '{$this->other['exceptioncode']}' " .
            "in '{$this->other['source']}' on line '{$this->other['line']}': '{$this->other['detail']}'.";
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
     * Validate required diagnostic fields.
     *
     * @return void
     */
    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();

        foreach (['studentlabel', 'reference', 'exceptionclass', 'exceptioncode', 'detail', 'source', 'line'] as $field) {
            if (!array_key_exists($field, $this->other)) {
                throw new \coding_exception("other['{$field}'] must be set");
            }
        }
        if ($this->other['studentlabel'] === '' || $this->other['reference'] === '') {
            throw new \coding_exception("other['studentlabel'] and other['reference'] must not be empty");
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
