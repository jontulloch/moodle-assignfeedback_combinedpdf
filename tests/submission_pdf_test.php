<?php
// This file is part of Moodle - https://moodle.org/
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
 * Tests for the stored PDF selection helper.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace assignfeedback_combinedpdf;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Retain an explicit guard for this plugin entry point.
defined('MOODLE_INTERNAL') || die();

use assignfeedback_combinedpdf\local\submission_pdf;

/**
 * Verify source PDF handling without invoking a document converter.
 *
 * @covers \assignfeedback_combinedpdf\local\submission_pdf
 */
final class submission_pdf_test extends \advanced_testcase {
    /**
     * Core PDF files are reused as the source without launching a conversion.
     *
     * @return void
     */
    public function test_original_pdf_is_reused(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $source = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'submission.pdf',
            'userid' => $user->id,
            'mimetype' => 'application/pdf',
        ], "%PDF-1.7\n%%EOF\n");

        $result = submission_pdf::resolve($source);
        $this->assertSame('original', $result['status']);
        $this->assertSame($source, $result['file']);
    }

    /**
     * Empty submissions are not exported or passed to a converter.
     *
     * @return void
     */
    public function test_empty_file_is_rejected(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $source = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($user->id)->id,
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'empty.pdf',
            'userid' => $user->id,
            'mimetype' => 'application/pdf',
        ], '');

        $result = submission_pdf::resolve($source);
        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['file']);
    }
}
