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
 * Legacy lib definitions
 *
 * @package   block_archiver
 * @copyright 2024 Andreas Wagner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Serve block_archiver files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 *
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 *
 * @throws coding_exception
 * @throws required_capability_exception
 * @throws moodle_exception
 */
function block_archiver_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if (!$jobcollection = \block_archiver\local\jobcollection::load($args[0])) {
        send_file_not_found();
    }

    $courseid = $jobcollection->get_data()->courseid;
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    // Check permissions.
    require_login($course, false, $cm);

    $coursecontext = \context_course::instance($course->id);
    if (!has_capability('block/archiver:createjobcollection', $coursecontext)) {
        throw new moodle_exception("You have not the capability to download the archive file.");
    }

    $userid = $jobcollection->get_data()->userid;
    if ($userid != $USER->id) {
        throw new moodle_exception("You have not the capability to download the archive file.");
    }

    // Try to serve physical files.
    $file = $jobcollection->get_artifact_file();
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
