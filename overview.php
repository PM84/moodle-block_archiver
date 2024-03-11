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

$coursecontext = \context_course::instance($courseid);
// Check capability.
require_capability('block/archiver:createjobcollection', $coursecontext);

$postaction = optional_param('action', '', PARAM_ALPHANUMEXT);
if (data_submitted() && ($postaction == 'selectedquizzes') && confirm_sesskey()) {

    $quizinstances = optional_param_array('quizinstance', [], PARAM_INT);

    print_r($quizinstances); die;

    if (!$quizinstances) {
        redirect($thisurl, get_string('requiredquizselection', 'block_archiver'));
    }

    foreach ($quizinstances as $quizid) {
        $quiz = $DB->get_record('quiz', ['id' => $quizid]);
        $quizmodule = $DB->get_record('modules', ['name' => 'quiz']);
        $cm = $DB->get_record('course_modules', ['instance' => $quizid, 'module' => $quizmodule->id]);

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
        $archiverreport->initiate_users_archive_job(
            $quiz,
            $cm,
            $course,
            $coursecontext,
            true,
            $sections,
            false,
            get_config('quiz_archiver', 'job_preset_export_attempts_paper_format'),
            false,
            false,
            get_config('quiz_archiver', 'job_preset_archive_filename_pattern'),
            get_config('quiz_archiver', 'job_preset_export_attempts_filename_pattern'),
            null,
            $USER->id
        );
    }
}


$PAGE->set_context($coursecontext);
$pagetitle = get_string('my_quiz_archives', 'block_archiver');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

echo $OUTPUT->header();

$data['quizze'] = quiz_helper::get_all_quizzes_with_attempt($courseid);

$jobtbl_html = "";
foreach ($data['quizze'] as $quizwithattempt) {
    $jobtbl = new job_overview_table('job_overview_table', $courseid, $quizwithattempt->id, $quizwithattempt->instance, $USER->id);
    $jobtbl->define_baseurl($thisurl);
    ob_start();
    $jobtbl->out(10, true);
    $jobtbl_html .= "<hr><h3>Test: ". $quizwithattempt->name."</h3>" . ob_get_contents();
    ob_end_clean();
}
$data['jobOverviewTable'] = $jobtbl_html;
$data['sesskey'] = sesskey();

echo $OUTPUT->render_from_template('block_archiver/overview', $data);


// $renderer = $PAGE->get_renderer('block_mbsteachshare');
// template::add_template_management_info($template);

// echo $renderer->render_templateinfo_box($course, $template, $thisurl);

// $logdata = log::get_template_history($template->id);
// echo $renderer->render_template_history($template, $logdata);

echo $OUTPUT->footer();
