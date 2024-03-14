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
 * This file defines the job overview table renderer
 *
 * @package   block_archiver
 * @copyright 2024 Andreas Wagner, Knowhow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\output;

use \local_activityapproval\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir.'/tablelib.php');

/**
 * This class defines the job overview table renderer
 *
 * @package   block_archiver
 * @copyright 2024 Andreas Wagner, Knowhow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempts_overview_table extends \table_sql {

    /**
     * Constructor
     *
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param int $courseid ID of the course
     * @param int $cmid ID of the course module
     * @param int $quizid ID of the quiz
     * @param int $userid - If set, the table is limited to the archives created by the user itself.
     *
     * @throws \coding_exception
     */
    public function __construct(string $uniqueid, int $courseid, int $userid = 0) {
        global $USER, $OUTPUT;

        parent::__construct($uniqueid);

        $this->define_columns([
            'checkbox',
            'name',
            'status',
        ]);

        $this->define_headers([
            $OUTPUT->render_from_template('block_archiver/checkbox_all', []),
            get_string('quizname', 'block_archiver'),
            get_string('approvalstatus', 'block_archiver'),
        ]);

        $fields = "q.id as quizid, q.name, q.course, approval.status ";

        $from = "{quiz} q
                 JOIN {course_modules} cm ON cm.instance = q.id
                 JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                 JOIN {local_actapp_jap_ext} actapp ON actapp.coursemoduleid = cm.id
            LEFT JOIN {local_actapp_approval} approval ON approval.coursemoduleid = cm.id AND approval.userid = :userid1 ";

        $where = "actapp.approval_expected = 1
                  AND cm.course = :courseid
                  AND q.id IN (SELECT quiz
                                 FROM {quiz_attempts} qa
                                WHERE qa.quiz = q.id AND qa.userid = :userid2 AND qa.preview = 0)";

        $params = [
            'courseid' => $courseid,
            'userid1' => $USER->id,
            'userid2' => $USER->id,
            'modulename' => 'quiz'
         ];

        $this->set_sql($fields, $from, $where, $params);

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('checkbox');
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Column renderer for the checkbox column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     */
    public function col_checkbox($values) {
        return \html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'quizid[]', 'value' => $values->quizid]);
    }

    /**
     * Render approval status.
     *
     * @param int $status
     * @return type
     */
    public function render_approval_status(int $status) {
        switch ($status) {
            case manager::STATUS_PENDING:
                $content = get_string('state_pending', 'rb_source_courseapproval');
                $classes = 'badge badge-warning';
                break;
            case manager::STATUS_APPROVED:
                $content = get_string('state_approved', 'rb_source_courseapproval');
                $classes = 'badge badge-success';
                break;
            case manager::STATUS_REJECTED:
                $content = get_string('state_rejected', 'rb_source_courseapproval');
                $classes = 'badge badge-danger';
                break;
            default:
                break;
        }
        $link = \html_writer::tag('span', $content, array(
            'class' => $classes
        ));
        return $link;
    }

    /**
     * Column renderer for the status column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     */
    public function col_status($values) {
        if (!isset($values->status)) {
            return get_string('norecentapproval', 'block_archiver');
        }
        return $this->render_approval_status($values->status);
    }
}
