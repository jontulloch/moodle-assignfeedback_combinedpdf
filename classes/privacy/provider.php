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
 * Privacy provider for the Combined feedback PDF plugin.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace assignfeedback_combinedpdf\privacy;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Retain an explicit guard for this plugin entry point.
defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use mod_assign\privacy\assign_plugin_request_data;
use mod_assign\privacy\assignfeedback_provider;
use mod_assign\privacy\assignfeedback_user_provider;
use mod_assign\privacy\useridlist;

/**
 * Privacy provider for the Combined feedback PDF utility.
 *
 * The plugin has no tables or persistent file areas of its own. It can cause
 * Moodle core to create reusable document-conversion records and converted
 * files, so those subsystems are declared in the same way as Annotate PDF.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class provider implements assignfeedback_provider, assignfeedback_user_provider, \core_privacy\local\metadata\provider {
    /**
     * Describe core subsystems used while generating an export.
     *
     * @param collection $collection Privacy metadata collection
     * @return collection
     */
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:filepurpose');
        $collection->add_subsystem_link('core_fileconverter', [], 'privacy:metadata:conversionpurpose');
        return $collection;
    }

    /**
     * Add contexts for plugin-owned records associated with a user.
     *
     * The plugin owns no records outside Moodle core and Assignment data.
     *
     * @param int $userid User ID
     * @param contextlist $contextlist Context list to populate
     * @return void
     */
    #[\Override]
    public static function get_context_for_userid_within_feedback(int $userid, contextlist $contextlist): void {
        // No plugin-owned records exist outside the Assignment grade and core subsystems.
    }

    /**
     * Add student IDs stored by this plugin for a teacher.
     *
     * @param useridlist $useridlist Assignment privacy user list
     * @return void
     */
    #[\Override]
    public static function get_student_user_ids(useridlist $useridlist): void {
        // No plugin-owned records contain student user IDs.
    }

    /**
     * Add user IDs stored by this plugin in a context.
     *
     * @param userlist $userlist Core privacy user list
     * @return void
     */
    #[\Override]
    public static function get_userids_from_context(userlist $userlist): void {
        // No plugin-owned records contain user IDs.
    }

    /**
     * Export plugin-owned feedback data for a user.
     *
     * @param assign_plugin_request_data $exportdata Assignment privacy request data
     * @return void
     */
    #[\Override]
    public static function export_feedback_user_data(assign_plugin_request_data $exportdata): void {
        // Persistent conversion data is exported by Moodle core's file subsystems.
    }

    /**
     * Delete plugin-owned feedback data in a context.
     *
     * @param assign_plugin_request_data $requestdata Assignment privacy request data
     * @return void
     */
    #[\Override]
    public static function delete_feedback_for_context(assign_plugin_request_data $requestdata): void {
        // No plugin-owned data exists to delete.
    }

    /**
     * Delete plugin-owned feedback data for one grade.
     *
     * @param assign_plugin_request_data $requestdata Assignment privacy request data
     * @return void
     */
    #[\Override]
    public static function delete_feedback_for_grade(assign_plugin_request_data $requestdata): void {
        // No plugin-owned data exists to delete.
    }

    /**
     * Delete plugin-owned feedback data for multiple grades.
     *
     * @param assign_plugin_request_data $deletedata Assignment privacy request data
     * @return void
     */
    #[\Override]
    public static function delete_feedback_for_grades(assign_plugin_request_data $deletedata): void {
        // No plugin-owned data exists to delete.
    }
}
