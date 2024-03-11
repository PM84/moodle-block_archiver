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
 * This file defines the jobcollection overview table renderer
 *
 * @package   quiz_archiver
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\output;

use block_archiver\local\jobcollection;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * This file defines the jobcollection overview table renderer
 *
 * @package   quiz_archiver
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobcollection_overview_table extends \table_sql {

    /**
     * Constructor
     *
     * @param string $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     * @param int $courseid ID of the course
     * @param int $userid - If set, the table is limited to the archives created by the user itself.
     *
     * @throws \coding_exception
     */
    public function __construct(string $uniqueid, int $courseid, int $userid = 0) {
        global $USER;

        parent::__construct($uniqueid);

        $this->define_columns([
            'id',
            'timecreated',
            'jobs',
            'status',
            'actions',
        ]);

        $this->define_headers([
            'Id',
            get_string('timecreated', 'block_archiver'),
            get_string('statusjobs', 'block_archiver'),
            get_string('status', 'block_archiver'),
            '',
        ]);

        $params = [
            'courseid' => $courseid,
            'userid' => $USER->id,
        ];

        $fields = "*";
        $from = "{block_archiver_jobcollection} jc ";
        $where = "jc.courseid = :courseid AND jc.userid = :userid ";
        $this->set_sql($fields, $from, $where, $params);

        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('actions');
        $this->no_sorting('jobs');
        $this->collapsible(false);
    }

    /**
     * Column renderer for the timecreated column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     */
    public function col_timecreated($values) {
        return date('Y-m-d H:i:s', $values->timecreated);
    }

    /**
     * Column renderer for the status column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     */
    public function col_status($values) {
        $s = jobcollection::get_status_display_args($values->status);
        return '<span class="badge badge-' . $s['color'] . '">' . $s['text'] . '</span><br/>' .
                '<small>' . date('H:i:s', $values->timemodified) . '</small>';
    }

    /**
     * Column renderer for the actions column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions($values): string {

        if (!$jobcollection = jobcollection::load($values->id)) {
            return '';
        }

        $html = '';

        // Action: Download.
        if ($artifactfile = $jobcollection->get_artifact_file()) {

            $artifacturl = \moodle_url::make_pluginfile_url(
                            $artifactfile->get_contextid(),
                            $artifactfile->get_component(),
                            $artifactfile->get_filearea(),
                            $artifactfile->get_itemid(),
                            $artifactfile->get_filepath(),
                            $artifactfile->get_filename(),
                            true,
            );

            $downloadtitle = get_string('download') . ': ' . $artifactfile->get_filename() .
                    ' (' . get_string('size') . ': ' . display_size($artifactfile->get_filesize()) . ')';

            $attrs = [
                'class' => 'btn btn-success mx-1',
                'title' => $downloadtitle,
                'alt' => $downloadtitle,
                'role' => 'button',
            ];
            $html .= \html_writer::link($artifacturl, '<i class="fa fa-download"></i>', $attrs);
        } else {
            $attrs = [
                'class' => 'btn btn-outline-success disabled mx-1',
                'alt' => get_string('download'),
                'role' => 'button',
                'disabled' => 'disabled'
            ];
            $html .= \html_writer::link('#', '<i class="fa fa-download"></i>', $attrs);
        }

        // Action: Delete.
        $deleteurl = new \moodle_url('', [
            'id' => $values->id,
            'mode' => 'archiver',
            'action' => 'delete_jobcollection',
            'courseid' => $values->courseid,
            'sesskey' => sesskey()
        ]);
        $attrs = [
            'class' => 'btn btn-danger mx-1',
            'alt' => get_string('delete', 'moodle'),
            'role' => 'button',
        ];
        $html .= \html_writer::link($deleteurl, '<i class="fa fa-times"></i>', $attrs);

        return $html;
    }

    /**
     * Column renderer for the jobs column
     *
     * @param \stdClass $values Values of the current row
     * @return string HTML code to be displayed
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_jobs($values): string {

        if (!$jobcollection = jobcollection::load($values->id)) {
            return '';
        }

        $items = [];
        $states = $jobcollection->get_jobs_states();

        if ($states->missingjobids) {
            $items[] = get_string('deletedjobs', 'block_archiver') . ': ' . implode(', ', $states->missingjobids);
        }

        foreach ($states->existingjobids as $status => $jobids) {

            $s = \quiz_archiver\ArchiveJob::get_status_display_args($status);
            $renderedstatus = '<span class="badge badge-' . $s['color'] . '">' . $s['text'] . '</span>';

            if (!empty($jobids)) {
                $items[] = $renderedstatus . ': Job ' . implode(', Job ', $jobids);
            }
        }

        if (count($items) > 0) {
            return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
        }
        return '';
    }

}
