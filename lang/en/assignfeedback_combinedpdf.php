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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for the Combined feedback PDF plugin.
 *
 * @package    assignfeedback_combinedpdf
 * @copyright  2026 Jonathan Tulloch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Combined feedback PDF';
$string['downloadselectedfeedback'] = 'Download feedback';
$string['confirmdownloadselectedtitle'] = 'Download feedback';
$string['confirmdownloadselected'] = 'Create combined feedback PDFs for the selected students who have a submission or saved feedback, and download them in a ZIP file? A maximum of {$a} exportable students can be processed at once.';
$string['savefilesbutton'] = 'Save files';
$string['downloadallremoved'] = 'The assignment-wide feedback download has been removed. Select students and use Download feedback in the bottom bulk-action menu.';
$string['feedbackheading'] = 'Overall feedback';
$string['rubricheading'] = 'Rubric';
$string['guideheading'] = 'Marking guide';
$string['criterionnumber'] = 'Criterion {$a}';
$string['criteriondescription'] = 'Criterion description';
$string['leveldescription'] = 'Level description';
$string['selectedlevel'] = 'Selected level';
$string['markingremark'] = 'Marker comment';
$string['score'] = 'Score';
$string['grade'] = 'Grade';
$string['overallsimilarity'] = 'Overall similarity';
$string['similaritypending'] = 'Pending';
$string['turnitinsubmissionid'] = 'Turnitin submission ID';
$string['submissiondate'] = 'Submission date';
$string['extensionuntil'] = 'Extension until';
$string['gradeddate'] = 'Last saved';
$string['assignment'] = 'Assignment';
$string['student'] = 'Student';
$string['unmappedparticipant'] = 'Participant unmapped {$a}';
$string['nofeedbackcomment'] = 'No feedback comment has been saved.';
$string['embeddedmedianote'] = 'Embedded media in the feedback comment is not included in this PDF export.';
$string['nothingtoexport'] = 'No submission or saved feedback was found for the selected students.';
$string['exportlimitexceeded'] = 'A maximum of {$a->max} students with a submission or saved feedback can be exported at once. At least {$a->selected} exportable students were found in your selection. Reduce the number of exportable students and try again.';
$string['plugindisabled'] = 'Combined feedback PDF is disabled by the site administrator.';
$string['cannotcreatepdf'] = 'A feedback PDF could not be created. Please try again or contact the site administrator.';
$string['markerstitle'] = 'Individual marker marks';
$string['marker'] = 'Marker';
$string['markerlabel'] = 'Marker {$a}';
$string['readmeheading'] = 'Combined feedback export';
$string['readmebody'] = 'Some selected students did not have a submission or saved feedback, or were no longer available to the exporter, so no package was created for them.';
$string['readmefailures'] = 'Some selected feedback packages could not be created. This ZIP does not contain technical error details. Give the reference below to a Moodle administrator.';
$string['readmefailureentry'] = '{$a->label} (reference {$a->reference})';
$string['readmeunmerged'] = 'A source PDF could not be merged for the following submissions. The feedback summary and source PDFs are included separately. Original Office documents are also retained when their converted PDFs could not be merged.';
$string['readmeconversion'] = 'Some submitted files could not be included as PDF pages in this export. Their originals are included separately in this ZIP. This is not a complete combined-script PDF for the affected submissions.';
$string['conversionpending'] = '{$a}: PDF conversion is pending or still in progress. Download the feedback again once Moodle has completed conversion.';
$string['conversionfailed'] = '{$a}: No usable converted PDF was available. Check Moodle document-converter settings and logs, or use a PDF submission, then download again.';
$string['selectedstudentreference'] = 'Selected student {$a}';
$string['eventfeedbackexported'] = 'Combined feedback export processed';
$string['eventstudentexportfailed'] = 'Combined feedback student export failed';
$string['privacy:metadata'] = 'The Combined feedback PDF plugin stores no personal data in its own tables or file areas.';
$string['privacy:metadata:filepurpose'] = 'The plugin reads saved Assignment submissions and feedback through Moodle core files while creating a requested export.';
$string['privacy:metadata:conversionpurpose'] = 'The plugin can ask Moodle core to create and cache a PDF conversion of a submitted document for inclusion in the requested export.';
