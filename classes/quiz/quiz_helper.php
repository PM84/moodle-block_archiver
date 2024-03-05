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
 * Helper class for block archiver quiz handling.
 *
 * @package    block_archiver
 * @copyright  2024, ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for block archiver quiz handling.
 *
 * @package    block_archiver
 * @copyright  2024, ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_helper extends \plugin_renderer_base {

    public static function get_all_quizzes_with_attempt(int $courseid): array {
        global $DB, $USER;

        $quizzes = get_coursemodules_in_course('quiz', $courseid);
        $quizwithattempts = [];

        foreach ($quizzes as $cmid => $quiz) {
            $attempts = self::get_quiz_attemts($quiz->instance, $USER->id);

            if ($attempts > 0) {
                $quizinstance = $DB->get_record('quiz', ['id' => $quiz->instance]);

                $quiz->name = $quizinstance->name;
                $quiz->attemptcnt = count($attempts);

                $quizwithattempts[] = $quiz;
            }
        }
        // print_r($quizwithattempts);die;
        return $quizwithattempts;
    }

    /**
     * Get all quiz attempts of a user of a single quiz.
     * @param int $quizid
     * @param int $userid
     * @return array
     */
    private static function get_quiz_attemts(int $quizid, int $userid): array {
        global $DB;

        return $DB->get_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid]);
    }
}
