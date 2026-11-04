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
 * English language strings for the Combined feedback PDF plugin.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 Jonathan Tulloch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['assignment'] = 'Assignment';
$string['cannotcreatepdf'] = 'A feedback PDF could not be created. Please try again or contact the site administrator.';
$string['confirmdownloadselected'] = 'Create combined feedback PDFs for the selected students who have a submission or saved feedback, and download them in a ZIP file? A maximum of {$a} exportable students can be processed at once.';
$string['confirmdownloadselectedtitle'] = 'Download feedback';
$string['conversionfailed'] = '{$a}: No usable converted PDF was available. Check Moodle document-converter settings and logs, or use a PDF submission, then download again.';
$string['conversionpending'] = '{$a}: PDF conversion is pending or still in progress. Download the feedback again once Moodle has completed conversion.';
$string['criteriondescription'] = 'Criterion description';
$string['criterionnumber'] = 'Criterion {$a}';
$string['downloadallremoved'] = 'The assignment-wide feedback download has been removed. Select students and use Download feedback in the bottom bulk-action menu.';
$string['downloadselectedfeedback'] = 'Download feedback';
$string['embeddedmedianote'] = 'Embedded media in the feedback comment is not included in this PDF export.';
$string['eventfeedbackexported'] = 'Combined feedback export processed';
$string['eventstudentexportfailed'] = 'Combined feedback student export failed';
$string['exportlimitexceeded'] = 'A maximum of {$a->max} students with a submission or saved feedback can be exported at once. At least {$a->selected} exportable students were found in your selection. Reduce the number of exportable students and try again.';
$string['extensionuntil'] = 'Extension until';
$string['feedbackheading'] = 'Overall feedback';
$string['grade'] = 'Grade';
$string['gradeddate'] = 'Last saved';
$string['guideheading'] = 'Marking guide';
$string['leveldescription'] = 'Level description';
$string['marker'] = 'Marker';
$string['markerlabel'] = 'Marker {$a}';
$string['markerstitle'] = 'Individual marker marks';
$string['markingremark'] = 'Marker comment';
$string['nofeedbackcomment'] = 'No feedback comment has been saved.';
$string['nothingtoexport'] = 'No submission or saved feedback was found for the selected students.';
$string['overallsimilarity'] = 'Overall similarity';
$string['plugindisabled'] = 'Combined feedback PDF is disabled by the site administrator.';
$string['pluginname'] = 'Combined feedback PDF';
$string['privacy:metadata'] = 'The Combined feedback PDF plugin stores no personal data in its own tables or file areas.';
$string['privacy:metadata:conversionpurpose'] = 'The plugin can ask Moodle core to create and cache a PDF conversion of a submitted document for inclusion in the requested export.';
$string['privacy:metadata:filepurpose'] = 'The plugin reads saved Assignment submissions and feedback through Moodle core files while creating a requested export.';
$string['readmebody'] = 'Some selected students did not have a submission or saved feedback, or were no longer available to the exporter, so no package was created for them.';
$string['readmeconversion'] = 'Some submitted files could not be included as PDF pages in this export. Their originals are included separately in this ZIP. This is not a complete combined-script PDF for the affected submissions.';
$string['readmefailureentry'] = '{$a->label} (reference {$a->reference})';
$string['readmefailures'] = 'Some selected feedback packages could not be created. This ZIP does not contain technical error details. Give the reference below to a Moodle administrator.';
$string['readmeheading'] = 'Combined feedback export';
$string['readmeunmerged'] = 'A source PDF could not be merged for the following submissions. The feedback summary and source PDFs are included separately. Original Office documents are also retained when their converted PDFs could not be merged.';
$string['rubricheading'] = 'Rubric';
$string['savefilesbutton'] = 'Save files';
$string['score'] = 'Score';
$string['selectedlevel'] = 'Selected level';
$string['selectedstudentreference'] = 'Selected student {$a}';
$string['similaritypending'] = 'Pending';
$string['student'] = 'Student';
$string['submissiondate'] = 'Submission date';
$string['turnitinsubmissionid'] = 'Turnitin submission ID';
$string['unmappedparticipant'] = 'Participant unmapped {$a}';
