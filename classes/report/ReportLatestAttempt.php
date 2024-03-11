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
 * This file defines the block archiver report class.
 *
 * @package   quiz_archiver
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\report;

defined('MOODLE_INTERNAL') || die();

/**
 * This file defines the block archiver report class.
 *
 * Teh super class is overridden to select only the latest attempt per quiz.
 *
 * @package   quiz_archiver
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ReportLatestAttempt extends \quiz_archiver\Report {

    /**
     * Get the latest attempt for all users inside this quiz, excluding previews
     *
     * @param int $userid - If set, only the attempts of the given user are included.
     * @return array Array of all attempt IDs together with the userid that were
     * made inside this quiz. Indexed by attemptid.
     *
     * @throws \dml_exception
     */
    public function get_attempts($userid = 0): array {
        global $DB;

        $conditions = [
            'quiz' => $this->quiz->id,
            'preview' => 0,
        ];

        if (!empty($userid)) {
            $conditions['userid'] = $userid;
        }

        return $DB->get_records('quiz_attempts', $conditions, '', 'Max(id) AS attemptid, userid');
    }

}
