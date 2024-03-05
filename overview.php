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
 * @copyright  2024, ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/quiz/report/archiver/report.php');

use \block_archiver\quiz\quiz_helper;
use \quiz_archiver\output\job_overview_table;

global $PAGE, $USER, $DB, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);

$thisurl = new moodle_url('/blocks/archiver/overview.php', ['courseid' => $courseid]);
$PAGE->set_url($thisurl);
$PAGE->set_pagelayout('incourse');

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

require_login($courseid, false);
$coursecontext = \context_course::instance($courseid);

$postaction = optional_param('action', '', PARAM_ALPHANUMEXT);
if ($postaction == 'selectedquizzes') {
    $quizinstances = required_param('quizinstance', PARAM_RAW);

    foreach($quizinstances as $quizid) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        $cm = $DB->get_record('course_modules', ['instance' => $quizid, 'module' => 83]);

        $archiverreport = new quiz_archiver_report();
        $sections = [
            'header' => 1,
            'quiz_feedback' => 1,
            'question' => 1,
            'question_feedback' => 1,
            'general_feedback' => 1,
            'rightanswer' => 1,
            'history' => 1,
            'attachments' => 1
        ];
        $archiverreport->initiate_users_archive_job($quiz,$cm, $course, $coursecontext, true, $sections, false, "A4", false, false, 'quiz-archive-${courseshortname}-${courseid}-${quizname}-${quizid}_${date}-${time}', 'attempt-${attemptid}-${username}_${date}-${time}',null, $USER->id);
    }

}


$PAGE->set_context($coursecontext);
$pagetitle = get_string('my_quiz_archives', 'block_archiver');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

echo $OUTPUT->header();

$data['quizze'] = quiz_helper::get_all_quizzes_with_attempt($courseid);

$jobtbl = new job_overview_table('job_overview_table', $courseid, 1040, 60, $USER->id);
$jobtbl->define_baseurl($thisurl);
ob_start();
$jobtbl->out(10, true);
$jobtbl_html = ob_get_contents();
ob_end_clean();
$data['jobOverviewTable'] = $jobtbl_html;


echo $OUTPUT->render_from_template('block_archiver/overview', $data);


// $renderer = $PAGE->get_renderer('block_mbsteachshare');
// template::add_template_management_info($template);

// echo $renderer->render_templateinfo_box($course, $template, $thisurl);

// $logdata = log::get_template_history($template->id);
// echo $renderer->render_template_history($template, $logdata);

echo $OUTPUT->footer();
