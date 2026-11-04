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
 * Submission file to PDF resolution helper.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace assignfeedback_combinedpdf\local;

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Retain an explicit guard for this plugin entry point.
defined('MOODLE_INTERNAL') || die();

use core_files\conversion;
use core_files\converter;
use stored_file;

/**
 * Resolve an original submission file to a PDF without changing saved feedback.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 University of East London
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class submission_pdf {
    /**
     * Reuse a completed core conversion or request one through configured converters.
     *
     * No forced refresh, sleep loop, blank placeholder, or direct converter command
     * is used. An asynchronous conversion is reported as pending for this export.
     * Caller must already have checked permission to export the source submission.
     *
     * @param stored_file $source File from the selected submission attempt
     * @param converter|null $converter Injectable core API, primarily for tests
     * @return array{file: stored_file|null, status: string} Original, converted, pending, or failed
     */
    public static function resolve(stored_file $source, ?converter $converter = null): array {
        if ($source->is_directory() || $source->get_filesize() < 1) {
            return ['file' => null, 'status' => 'failed'];
        }
        if (self::is_pdf($source)) {
            return ['file' => $source, 'status' => 'original'];
        }

        try {
            // The core API finds cached conversions by the actual source content.
            // Calling this before a capability probe also allows reuse of a cached
            // PDF when the converter that produced it is no longer enabled.
            $converter = $converter ?? new converter();
            $conversion = $converter->start_conversion($source, 'pdf');
            $status = $conversion->get('status');
            if ($status === conversion::STATUS_COMPLETE) {
                $destination = $conversion->get_destfile();
                if (
                    $destination instanceof stored_file && !$destination->is_directory()
                        && $destination->get_filesize() > 0 && self::is_pdf($destination)
                ) {
                    return ['file' => $destination, 'status' => 'converted'];
                }
            } else if ($status === conversion::STATUS_PENDING || $status === conversion::STATUS_IN_PROGRESS) {
                return ['file' => null, 'status' => 'pending'];
            }
        } catch (\Throwable $e) {
            // A broken or unavailable converter must not discard the original or
            // stop the whole cohort export. The caller adds a visible ZIP warning.
            // Do not disclose converter credentials or server paths in the export.
            return ['file' => null, 'status' => 'failed'];
        }

        return ['file' => null, 'status' => 'failed'];
    }

    /**
     * Recognise PDF metadata; Moodle's merger still validates the actual PDF.
     *
     * @param stored_file $file File to inspect
     * @return bool
     */
    private static function is_pdf(stored_file $file): bool {
        return $file->get_mimetype() === 'application/pdf'
            || strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'pdf';
    }
}
