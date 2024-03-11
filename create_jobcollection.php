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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    block_archiver
 * @copyright  2024, KnowHow
 * @author     Andreas Wagner, KnowHow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/quiz/report/archiver/report.php');

use \block_archiver\output\attempts_overview_table;
use \block_archiver\output\jobcollection_overview_table;
use \block_archiver\local\jobcollection;

global $PAGE, $USER, $DB, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);

$thisurl = new moodle_url('/blocks/archiver/create_jobcollection.php', ['courseid' => $courseid]);
$PAGE->set_url($thisurl);
$PAGE->set_pagelayout('incourse');

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

require_login($course, false);
$coursecontext = \context_course::instance($courseid);
// Check capability.
require_capability('block/archiver:createjobcollection', $coursecontext);

$postaction = optional_param('action', '', PARAM_ALPHANUMEXT);
if (data_submitted() && ($postaction == 'selectedquizzes') && confirm_sesskey()) {
    $quizids = optional_param_array('attemptid', [], PARAM_INT);
    if ($quizids) {
        $jobcollection = new jobcollection(['userid' => $USER->id, 'courseid' => $courseid]);
        $jobcollection->create_latest_attempt_collection($course, $coursecontext, $quizids);
        redirect($thisurl, get_string('jobcollectioncreated', 'block_archiver'));
    }
    redirect($thisurl, get_string('requiredquizselection', 'block_archiver'));
}

if (($postaction == 'delete_jobcollection') && confirm_sesskey()) {
    $jobcollectionid = required_param('id', PARAM_INT);
    if ($jobcollection = jobcollection::load($jobcollectionid)) {
        $jobcollection->delete();
        redirect($thisurl, get_string('jobcollectiondeleted', 'block_archiver'));
    }
    redirect($thisurl, get_string('jobcollectiondeletefailed', 'block_archiver'));
}

$PAGE->set_context($coursecontext);
$pagetitle = get_string('my_quiz_archives', 'block_archiver');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

echo $OUTPUT->header();

// List of quizzes with latest attempt.
$jobtblhtml = "";
$jobtbl = new attempts_overview_table('job_last_attempts_table', $courseid);
$jobtbl->define_baseurl($thisurl);
ob_start();
$jobtbl->out(1000, true);
$jobtblhtml .= ob_get_contents();
ob_end_clean();
$data['jobOverviewTable'] = $jobtblhtml;

// List of job collections to stro in an archive.
$overviewtablehtml = "";
$table = new jobcollection_overview_table('jobcollection_overview_table', $courseid, $USER->id);
$table->define_baseurl($thisurl);
ob_start();
$table->out(1000, true);
$overviewtablehtml .= ob_get_contents();
ob_end_clean();

$data['jobCollectionOverviewTable'] = $overviewtablehtml;
$data['sesskey'] = sesskey();

echo $OUTPUT->render_from_template('block_archiver/createcollection', $data);
echo $OUTPUT->footer();
