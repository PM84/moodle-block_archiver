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
 * Adhoc task to create a job collection.
 *
 * @package   block_archive
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\task;

defined('MOODLE_INTERNAL') || die();

use block_archiver\local\jobcollection;
/**
 * Adhoc task to create a job collection.
 *
 * @package   block_archive
 * @copyright 2024 Andreas Wagner, KnowHow
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adhoc_create_jobcollection extends \core\task\adhoc_task {

    const START_DELAY = 100;

    /**
     * Overridden to set a start delay.
     *
     * @return int
     */
    public function get_next_run_time() {
        if ($nextruntime = parent::get_next_run_time()) {
            return $nextruntime;
        }
        return time() + self::START_DELAY;
    }

    /**
     * Calls the task.
     */
    public function execute() {

        // Get templaterecord.
        $data = $this->get_custom_data();

        $jobcollection = jobcollection::load($data->jobcollectionid);
        if ($jobcollection->has_all_jobs_finished()) {
            $jobcollection->store_archive();
            return true;
        }

        // If there is a chance to get this job finished, reschedule task.
        if ($jobcollection->can_be_finished()) {
            // Update next runtime and throw exception to preserve the task
            // and try again later.
            throw new \moodle_exception('jobs not yet finished');
        }

        // If jobcollection can not be finished, set status and leave task
        // without rescheduling.
        $jobcollection->update_status(jobcollection::STATUS_FAILED);
        return true;
    }

}
