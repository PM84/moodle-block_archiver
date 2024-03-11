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
 * This file defines the quiz archiver latest attempt report class.
 *
 * @package   quiz_archiver
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_archiver\report\ReportLatestAttempt;

/**
 * This file defines the quiz archiver latest attempt report class.
 *
 * @package   quiz_archiver
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class latest_attempt_report extends \quiz_archiver_report {

    /**
     * Initialises an archive job for a specific user.
     *
     * Overrides the super methode to use ReportLatestAttempt class for selecting
     * only latest attempts pf users per quiz.
     *
     * Code is mainly taken from super changed lines are marked with "Overridden".
     *
     * @param int $userid
     * @return ArchiveJob|null Created ArchiveJob on success
     */
    public function initiate_users_archive_job(
        object $quiz,
        object $cm,
        object $course,
        object $context,
        bool $export_attempts,
        array $report_sections,
        bool $report_keep_html_files,
        string $paper_format,
        bool $export_quiz_backup,
        bool $export_course_backup,
        string $archive_filename_pattern,
        string $attempts_filename_pattern,
        ?int $retention_seconds = null,
        int $userid = 0
    ) {
        $this->context = $context;
        require_capability('mod/quiz_archiver:getownarchive', $this->context);

        $this->course = $course;
        $this->cm = $cm;
        $this->quiz = $quiz;

        // Include only the latest attempt per quiz.
        $this->report = new ReportLatestAttempt($this->course, $this->cm, $this->quiz); // Overridden.
        return $this->initiate_archive_job(
            $export_attempts,
            $report_sections,
            $report_keep_html_files,
            $paper_format,
            $export_quiz_backup,
            $export_course_backup,
            $archive_filename_pattern,
            $attempts_filename_pattern,
            $retention_seconds,
            $userid
        );
    }


}
