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
 * Class representing a collection of job archives.
 *
 * @package    block_archiver
 * @author     2024 Andreas Wagner, KnowHow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\local;

defined('MOODLE_INTERNAL') || die();

use quiz_archiver\ArchiveJob;

/**
 * Class representing a collection of job archives.
 *
 * @package    block_archiver
 * @author     2024 Andreas Wagner, KnowHow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobcollection {

    /** @var const for the component of jobcollection related files. */
    const FILE_COMPONENT = 'block_archiver';

    /** @var const for the file area of jobcollection related files. */
    const FILE_AREA = 'reportpdf';

    /** @var string path to tempdir for storing archive operations */
    const TEMP_DIR = 'block_archiver_pdf/merged/';

    // Job status values.

    /** @var string Job status: Unknown */
    const STATUS_UNKNOWN = 'UNKNOWN';

    /** @var string Job status: Uninitialized */
    const STATUS_UNINITIALIZED = 'UNINITIALIZED';

    /** @var string Job status: Awaiting processing */
    const STATUS_AWAITING_PROCESSING = 'AWAITING_PROCESSING';

    /** @var string Job status: Running */
    const STATUS_RUNNING = 'RUNNING';

    /** @var string Job status: Finished */
    const STATUS_FINISHED = 'FINISHED';

    /** @var string Job status: Failed */
    const STATUS_FAILED = 'FAILED';

    /** @var string Job status: Timeout */
    const STATUS_TIMEOUT = 'TIMEOUT';

    /** @var string Job status: Deleted */
    const STATUS_DELETED = 'DELETED';

    /** @var string Job status: exc eption */
    const STATUS_EXCEPTION = 'EXCEPTION';

    /** @var int id of jobcollection. */
    protected $id;

    /** @var char status of jobcollection (one of the status constants). */
    protected $status;

    /** @var int timestamp of jobcollection creation. */
    protected $timecreated;

    /** @var int timestamp of jobcollection modification. */
    protected $timemodified;

    /** @var int timestamp of last run of adhoc task. */
    protected $timelastrun;

    /** @var int userid related to this collection. */
    protected $userid;

    /** @var int courseid related to this collection. */
    protected $courseid;

    /** @var array list of related jobs indexed by job id based on relation table. */
    protected $jobs = [];

    /** @var int timestamp of last run of adhoc task. */
    protected $requiredfields = ['userid' => 1, 'courseid' => 1];

    /**
     * Construct an object for a collection of jobs.
     *
     * @param array $record
     * @throws \moodle_exception
     */
    public function __construct(array $record) {

        $this->status = self::STATUS_UNINITIALIZED;
        foreach ($record as $fieldname => $value) {
            $this->{$fieldname} = $value;
            unset($this->requiredfields[$fieldname]);
        }

        if (count($this->requiredfields) > 0) {
            throw new \moodle_exception('field missing');
        }
    }

    /**
     * Get id of collection.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Returns object with fields and values that are defined in database
     *
     * @return stdClass
     */
    public function get_data(): \stdClass {
        $data = new \stdClass();
        foreach ($this as $name => $value) {
            $data->$name = $value;
        }
        return $data;
    }

    /**
     * Add a job to this collection.
     *
     * @param object $job
     */
    public function add_job(object $job) {
        $this->jobs[$job->get_id()] = $job;
    }

    /**
     * Remove a job from this collection.
     *
     * @param int $jobid
     */
    public function remove_job(int $jobid) {
        if (isset($this->jobs[$jobid])) {
            unset($this->jobs[$jobid]);
        }
    }

    /**
     * Delete a colleciont from database.
     */
    public function delete() {
        global $DB;

        $DB->delete_records('block_archiver_jobcollection_jobs', ['jobid' => $this->id]);
        $DB->delete_records('block_archiver_jobcollection', ['id' => $this->id]);

        $usercontext = \context_user::instance($this->userid);

        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, self::FILE_COMPONENT, self::FILE_AREA, $this->id);
        foreach ($files as $file) {
            $file->delete();
        }

        // Delete adhoc task if exists.
        if ($taskrecord = $this->get_adhoc_task_record()) {
            $DB->delete_records('task_adhoc', ['id' => $taskrecord->id]);
        }
    }

    /**
     * Save this collection with all relationships to (hopyfully) existing jobs.
     */
    public function save() {
        global $DB;

        $this->timemodified = time();

        if ($this->id) {
            $DB->update_record('block_archiver_jobcollection', $this->get_data());
            $DB->delete_records('block_archiver_jobcollection_jobs', ['jobcollectionid' => $this->id]);
        } else {
            $this->timecreated = time();
            $this->id = $DB->insert_record('block_archiver_jobcollection', $this->get_data());
        }

        // We rely on job ids here, because job maybe already deleted from other process.
        foreach (array_keys($this->jobs) as $jobid) {
            $record = (object) [
                        'jobcollectionid' => $this->id,
                        'jobid' => $jobid,
                        'timecreated' => time()
            ];
            $DB->insert_record('block_archiver_jobcollection_jobs', $record);
        }
    }

    /**
     * Get jobs of the collection from database. Please note that some of the jobs may
     * have different status. If a job is deleted, the object related to the jobid
     * in array key is null.
     *
     * @return array
     */
    public function get_jobs(): array {
        global $DB;

        $sql = "SELECT jcjobs.jobid as jobrawid, qajobs.*
                  FROM {block_archiver_jobcollection_jobs} jcjobs
             LEFT JOIN {quiz_archiver_jobs} qajobs ON qajobs.id = jcjobs.jobid
                 WHERE jcjobs.jobcollectionid = ? ";

        $this->jobs = $DB->get_records_sql($sql, ['id' => $this->id]);

        return $this->jobs;
    }

    /**
     * Get an information object for the status of jobs within the collection.
     *
     * @return object
     */
    public function get_jobs_states(): object {

        $states = (object) [
                    'missingjobids' => [],
                    'existingjobids' => [],
        ];
        foreach ($this->jobs as $requiredjobid => $job) {
            if (!$job) {
                $states->missingjobids[] = $requiredjobid;
                continue;
            }
            if (!isset($states->existingjobids[$job->status])) {
                $states->existingjobids[$job->status] = [];
            }
            $states->existingjobids[$job->status][] = $requiredjobid;
        }
        return $states;
    }

    /**
     * Check, if all jobs are finished, so the collection archive can be created.
     *
     * If there are missing jobs, because the are deleted, the jobs are not finished
     * (and we will not be able to finish them in future).
     *
     * @return bool
     */
    public function has_all_jobs_finished(): bool {

        $states = $this->get_jobs_states();

        if (count($states->missingjobids) > 0) {
            return false;
        }

        if (empty($states->existingjobids['FINISHED'])) {
            return false;
        }
        return (count($this->jobs) == count($states->existingjobids['FINISHED']));
    }

    /**
     * Check, if there is a chance to finished all jobs in future.
     *
     * If there are missing jobs, because the are deleted, collection cannot ever
     * be finished as well as there are failed, timedout or deleted jobs.
     *
     * @return bool
     */
    public function can_be_finished(): bool {

        $states = $this->get_jobs_states();

        if (count($states->missingjobids) > 0) {
            return false;
        }

        $finalstates = [self::STATUS_FAILED, self::STATUS_TIMEOUT, self::STATUS_DELETED];
        foreach ($finalstates as $finalstate) {
            if (!empty($states->existingjobids[$finalstate])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Load the data for a collection from database an create a collection object.
     *
     * @param int $id
     * @return jobcollection
     */
    public static function load(int $id): jobcollection {
        global $DB;

        $record = (array) $DB->get_record('block_archiver_jobcollection', ['id' => $id], '*', MUST_EXIST);

        $jobcollection = new jobcollection($record);
        $jobcollection->get_jobs();

        return $jobcollection;
    }

    /**
     * Get the archived collection filename.
     *
     * @return string
     */
    public function get_archive_filename(): string {
        return 'archive.zip';
    }

    /**
     * Store attempts from different jobs into one archive.
     *
     * @throws \moodle_exception
     */
    public function store_archive() {
        global $DB;

        $this->update_status(self::STATUS_RUNNING);

        try {
            $tmpdir = make_temp_directory(self::TEMP_DIR . $this->id);

            if (count($this->jobs) == 0) {
                throw new \moodle_exception('no jobs available');
            }

            list($injobids, $params) = $DB->get_in_or_equal(array_keys($this->jobs), SQL_PARAMS_NAMED);

            $sql = "SELECT a.id, j.id AS jobid, j.courseid, j.cmid, j.quizid, j.artifactfileid, a.attemptid
                      FROM {quiz} q
                      JOIN {" . ArchiveJob::JOB_TABLE_NAME . "} j ON j.quizid = q.id
                      JOIN {" . ArchiveJob::ATTEMPTS_TABLE_NAME . "} a ON a.jobid = j.id
                WHERE j.id $injobids ";

            // Process artifact files for the user in the given context.
            $attemptartifacts = $DB->get_records_sql($sql, $params);

            $fs = get_file_storage();

            foreach ($attemptartifacts as $row) {
                $fm = new filemanager($row->courseid, $row->cmid, $row->quizid);
                $artifact = $fs->get_file_by_id($row->artifactfileid);
                $fm->copy_attempt_data_from_artifact_to_path($artifact, $row->jobid, $row->attemptid, $tmpdir);
            }

            $fm->create_merged_pdf($tmpdir);
            $fm->store_archive_file($tmpdir, $this);

            $this->update_status(self::STATUS_FINISHED);
        } catch (\Exception $e) {

            $this->update_status(self::STATUS_EXCEPTION);
            // Throw to called method.
            throw new \moodle_exception('create archive failed' . $e->getMessage());
        }
    }

    /**
     * Update the status of job collection.
     *
     * @param string $status
     */
    public function update_status(string $status) {
        global $DB;

        $DB->set_field('block_archiver_jobcollection', 'status', $status, ['id' => $this->id]);
        $this->status = $status;
    }

    /**
     * Schedule an adhoc task to create a collection archive.
     */
    public function schedule_store_archive() {
        $adhoctask = new \block_archiver\task\adhoc_create_jobcollection();
        $adhoctask->set_custom_data(['jobcollectionid' => $this->id]);
        \core\task\manager::queue_adhoc_task($adhoctask, true);
        $this->update_status(self::STATUS_AWAITING_PROCESSING);
    }

    /**
     * Get an adhoc task by its class, componentn and customdata.
     *
     *
     * @param block_archive\task\adhoc_create_jobcollection $task
     * @return object
     */
    public function get_adhoc_task_record() {
        global $DB;

        $adhoctask = new \block_archiver\task\adhoc_create_jobcollection();
        $adhoctask->set_custom_data(['jobcollectionid' => $this->id]);

        $record = \core\task\manager::record_from_adhoc_task($adhoctask);
        $params = [$record->classname, $record->component, $record->customdata];
        $sql = 'classname = ? AND component = ? AND ' .
                $DB->sql_compare_text('customdata', \core_text::strlen($record->customdata) + 1) . ' = ?';

        if ($record->userid) {
            $params[] = $record->userid;
            $sql .= " AND userid = ? ";
        }
        return $DB->get_record_select('task_adhoc', $sql, $params);
    }

    /**
     * Get the timestamp for the next execution.
     *
     * @return int if 0 task is not scheduled anymore.
     */
    public function get_store_archive_next_run_time(): int {

        if (!$taskrecord = $this->get_adhoc_task_record()) {
            return 0;
        }

        return $taskrecord->nextruntime;
    }

    /**
     * Create jobs for quiz_archiver, which includes the last attempts of this user and schedule an adhoc
     * tahsk for collecting the generated files into one directory, create a merged pdf dokument from
     * result pages and create a zip archive.
     *
     * @param object $course
     * @param object $coursecontext
     * @param array $quizids
     * @return boolean
     */
    public function create_latest_attempt_collection(object $course, object $coursecontext, array $quizids) {
        global $USER, $DB, $CFG;

        if (count($quizids) == 0) {
            return false;
        }

        require_once($CFG->dirroot . '/blocks/archiver/classes/latest_attempt_report.php');
        $quizmodule = $DB->get_record('modules', ['name' => 'quiz']);

        foreach ($quizids as $quizid) {

            $quiz = $DB->get_record('quiz', ['id' => $quizid]);
            $cm = $DB->get_record('course_modules', ['instance' => $quizid, 'module' => $quizmodule->id]);

            $archiverreport = new \latest_attempt_report();

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
            $job = $archiverreport->initiate_users_archive_job(
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
            $this->add_job($job);
        }
        $this->save();
        $this->schedule_store_archive();

        return true;
    }

    /**
     * Returns the status indicator display arguments based on the given job status
     *
     * @param string $status JOB_STATUS value to convert
     * @return array Status of this job, translated for display
     * @throws \coding_exception
     */
    public static function get_status_display_args(string $status): array {
        switch ($status) {
            case self::STATUS_UNKNOWN:
                return ['color' => 'warning', 'text' => get_string('job_status_UNKNOWN', 'block_archiver')];
            case self::STATUS_UNINITIALIZED:
                return ['color' => 'secondary', 'text' => get_string('job_status_UNINITIALIZED', 'block_archiver')];
            case self::STATUS_AWAITING_PROCESSING:
                return ['color' => 'secondary', 'text' => get_string('job_status_AWAITING_PROCESSING', 'block_archiver')];
            case self::STATUS_RUNNING:
                return ['color' => 'primary', 'text' => get_string('job_status_RUNNING', 'block_archiver')];
            case self::STATUS_FINISHED:
                return ['color' => 'success', 'text' => get_string('job_status_FINISHED', 'block_archiver')];
            case self::STATUS_FAILED:
                return ['color' => 'danger', 'text' => get_string('job_status_FAILED', 'block_archiver')];
            case self::STATUS_TIMEOUT:
                return ['color' => 'danger', 'text' => get_string('job_status_TIMEOUT', 'block_archiver')];
            case self::STATUS_DELETED:
                return ['color' => 'secondary', 'text' => get_string('job_status_DELETED', 'block_archiver')];
            case self::STATUS_EXCEPTION:
                return ['color' => 'danger', 'text' => get_string('job_status_EXCEPTION', 'block_archiver')];
            default:
                return ['color' => 'light', 'text' => $status];
        }
    }

    public function get_artifact_file() {
        $usercontext = \context_user::instance($this->userid);
        $fs = get_file_storage();
        if ($files = $fs->get_area_files($usercontext->id, self::FILE_COMPONENT, self::FILE_AREA, $this->id, '', false)) {
            return array_shift($files);
        }
        return null;
    }

}
