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
 * Tests for the ArchiveJob class
 *
 * @package   block_archiver
 * @copyright 2024 Andreas Wagner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver;

defined('MOODLE_INTERNAL') || die();

use quiz_archiver\ArchiveJob;
use block_archiver\local\jobcollection;
use block_archiver\local\filemanager;
use block_archiver\task\adhoc_create_jobcollection;

/**
 * Tests for the ArchiveJob class
 */
class jobcollection_test extends \advanced_testcase {

    /**
     * Generates a mock quiz to use in the tests
     *
     * @return \stdClass Created mock objects
     */
    protected function generateMockQuiz(): \stdClass {
        // Create course, course module and quiz.
        $this->resetAfterTest();

        // Prepare user and course.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'grade' => 100.0,
            'sumgrades' => 100
        ]);

        return (object) [
                    'user' => $user,
                    'course' => $course,
                    'quiz' => $quiz,
                    'attempts' => [
                        (object) ['userid' => 1, 'attemptid' => 1],
                        (object) ['userid' => 1, 'attemptid' => 12],
                        (object) ['userid' => 2, 'attemptid' => 42],
                        (object) ['userid' => 3, 'attemptid' => 1337],
                    ],
                    'settings' => [
                        'num_attempts' => 3,
                        'export_attempts' => 1,
                        'export_report_section_header' => 1,
                        'export_report_section_quiz_feedback' => 1,
                        'export_report_section_question' => 1,
                        'export_report_section_question_feedback' => 0,
                        'export_report_section_general_feedback' => 1,
                        'export_report_section_rightanswer' => 0,
                        'export_report_section_history' => 1,
                        'export_report_section_attachments' => 1,
                        'export_quiz_backup' => 1,
                        'export_course_backup' => 0,
                        'archive_autodelete' => 1,
                        'archive_retention_time' => '42w',
                    ],
        ];
    }

    /**
     * Create an archive job.
     *
     * @param object $mocks
     * @param string $jobid
     * @param string $token
     * @param string $artifactfilepath
     * @param string $status
     * @return ArchiveJob
     */
    protected function create_archive_job(
            object $mocks,
            string $jobid,
            string $token,
            string $artifactfilepath = '',
            string $status = ArchiveJob::STATUS_UNKNOWN
    ): ArchiveJob {

        $job = ArchiveJob::create(
                        $jobid,
                        $mocks->course->id,
                        $mocks->quiz->cmid,
                        $mocks->quiz->id,
                        $mocks->user->id,
                        null,
                        $token,
                        $mocks->attempts,
                        $mocks->settings,
                        $status
        );

        if ($artifactfilepath) {

            $filerecord = [
                'contextid' => (\context_user::instance($mocks->user->id))->id,
                'component' => 'user',
                'filearea' => 'draft',
                'filepath' => '/',
                'itemid' => $job->get_id(),
                'filename' => basename($artifactfilepath)
            ];
            $draftfile = get_file_storage()->create_file_from_pathname($filerecord, $artifactfilepath);
            // Store uploaded file.
            $fm = new filemanager($job->get_course_id(), $job->get_cm_id(), $job->get_quiz_id());
            try {
                $artifact = $fm->store_uploaded_artifact($draftfile);
                $job->link_artifact($artifact->get_id(), filemanager::hash_file($draftfile));
            } catch (\Exception $e) {
                $job->set_status(ArchiveJob::STATUS_FAILED);
                return [
                    'status' => 'E_STORE_ARTIFACT_FAILED',
                ];
            }
        }

        return $job;
    }

    /**
     * Test collection.
     */
    public function test_jobcollection() {
        global $USER;

        $this->setAdminUser();

        // Create new archive job.
        $mocks = $this->generateMockQuiz();
        $job1 = $this->create_archive_job($mocks, '11111111-1234-5678-abcd-ef4242424242', 'TEST-WS-TOKEN-1');
        $job2 = $this->create_archive_job($mocks, '22222222-1234-5678-abcd-ef4242424242', 'TEST-WS-TOKEN-2');
        $job3 = $this->create_archive_job($mocks, '33333333-1234-5678-abcd-ef4242424242', 'TEST-WS-TOKEN-3');

        // Create collection.
        $jobcollection = new jobcollection(['userid' => $USER->id, 'courseid' => $mocks->course->id]);
        $jobcollection->add_job($job1);
        $jobcollection->add_job($job2);
        $jobcollection->add_job($job3);
        $jobcollection->save();

        // Check data.
        $jobcollection1 = jobcollection::load($jobcollection->get_id());
        $this->assertEquals($USER->id, $jobcollection1->get_data()->userid);
        $jobs = $jobcollection1->get_jobs();

        $expectedjobids = [$job1->get_id(), $job2->get_id(), $job3->get_id()];
        $this->assertEqualsCanonicalizing($expectedjobids, array_keys($jobs));

        $jobcollection->remove_job($job1->get_id());

        $jobcollection->save();
        $jobcollection1 = jobcollection::load($jobcollection->get_id());

        // Check data.
        $this->assertEquals($USER->id, $jobcollection1->get_data()->userid);
        $jobs = $jobcollection1->get_jobs();
        $expectedjobids = [$job2->get_id(), $job3->get_id()];
        $this->assertEqualsCanonicalizing($expectedjobids, array_keys($jobs));

        $this->assertFalse($jobcollection1->has_all_jobs_finished());
        $this->assertTrue($jobcollection1->can_be_finished());
    }

    /**
     * Test storing a collection archive.
     */
    public function test_jobcollection_store_archive() {
        global $USER, $CFG, $DB;

        $this->setAdminUser();

        $mockfilesdir1 = $CFG->dirroot .
                '/blocks/archiver/tests/fixtures/quiz-archive-CID-0000000003-3-Test quiz-11_2024-03-11-08-41-42.tar.gz';
        $mockfilesdir2 = $CFG->dirroot .
                '/blocks/archiver/tests/fixtures/quiz-archive-CID-0000000003-3-Test quiz-11_2024-03-11-08-53-09.tar.gz';
        $mockfilesdir3 = $CFG->dirroot .
                '/blocks/archiver/tests/fixtures/quiz-archive-CID-0000000003-3-Test quiz-11_2024-03-11-09-15-18.tar.gz';

        // Create new archive job.
        $mocks = $this->generateMockQuiz();
        $mocks->attempts = [
            (object) ['userid' => $USER->id, 'attemptid' => 23],
            (object) ['userid' => $USER->id, 'attemptid' => 25],
            (object) ['userid' => $USER->id, 'attemptid' => 26],
        ];
        $job1 = $this->create_archive_job($mocks, '11111111-1234-5678-abcd-ef4242424242',
                'TEST-WS-TOKEN-1', $mockfilesdir1, jobcollection::STATUS_FINISHED);

        $mocks->attempts = [
            (object) ['userid' => 2, 'attemptid' => 26],
        ];
        $job2 = $this->create_archive_job($mocks, '22222222-1234-5678-abcd-ef4242424242',
                'TEST-WS-TOKEN-2', $mockfilesdir2, jobcollection::STATUS_FINISHED);

        $job3 = $this->create_archive_job($mocks, '33333333-1234-5678-abcd-ef4242424242',
                'TEST-WS-TOKEN-3', $mockfilesdir3, jobcollection::STATUS_FINISHED);

        // Create collection.
        $jobcollection = new jobcollection(['userid' => $USER->id, 'courseid' => $mocks->course->id]);
        $jobcollection->add_job($job1);
        $jobcollection->add_job($job2);
        $jobcollection->add_job($job3);
        $jobcollection->save();

        $jobcollection->store_archive();

        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, jobcollection::FILE_COMPONENT,
                jobcollection::FILE_AREA, $jobcollection->get_id(), '', false);

        $file = array_shift($files);
        $this->assertEquals($jobcollection->get_archive_filename(), $file->get_filename());

        $jobcollection->delete();
        $this->assertEmpty($DB->get_records('block_archiver_jobcollection_jobs', ['jobid' => $jobcollection->get_id()]));
        $this->assertEmpty($DB->get_records('block_archiver_jobcollection', ['id' => $jobcollection->get_id()]));
        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, jobcollection::FILE_COMPONENT,
                jobcollection::FILE_AREA, $jobcollection->get_id(), '', false);
        $this->assertEmpty($files);
    }

    /**
     * Test storing a collection archive.
     */
    public function test_jobcollection_archive_task() {
        global $USER, $CFG, $DB;

        $this->setAdminUser();

        $mockfilesdir1 = $CFG->dirroot .
                '/blocks/archiver/tests/fixtures/quiz-archive-CID-0000000003-3-Test quiz-11_2024-03-11-08-41-42.tar.gz';
        $mockfilesdir2 = $CFG->dirroot .
                '/blocks/archiver/tests/fixtures/quiz-archive-CID-0000000003-3-Test quiz-11_2024-03-11-08-53-09.tar.gz';
        $mockfilesdir3 = $CFG->dirroot .
                '/blocks/archiver/tests/fixtures/quiz-archive-CID-0000000003-3-Test quiz-11_2024-03-11-09-15-18.tar.gz';

        $startdelay = 100;
        set_config('startdelay', $startdelay, 'block_archiver');

        // Create new archive job.
        $mocks = $this->generateMockQuiz();
        $mocks->attempts = [
            (object) ['userid' => $USER->id, 'attemptid' => 23],
            (object) ['userid' => $USER->id, 'attemptid' => 25],
            (object) ['userid' => $USER->id, 'attemptid' => 26],
        ];
        $job1 = $this->create_archive_job($mocks, '11111111-1234-5678-abcd-ef4242424242',
                'TEST-WS-TOKEN-1', $mockfilesdir1, jobcollection::STATUS_FINISHED);
        $mocks->attempts = [
            (object) ['userid' => 2, 'attemptid' => 26],
        ];
        $job2 = $this->create_archive_job($mocks, '22222222-1234-5678-abcd-ef4242424242',
                'TEST-WS-TOKEN-2', $mockfilesdir2, jobcollection::STATUS_FINISHED);

        $job3 = $this->create_archive_job($mocks, '33333333-1234-5678-abcd-ef4242424242',
                'TEST-WS-TOKEN-3', $mockfilesdir3, jobcollection::STATUS_RUNNING);

        // Create collection.
        $DB->delete_records('task_adhoc');

        $jobcollection = new jobcollection(['userid' => $USER->id, 'courseid' => $mocks->course->id]);
        $jobcollection->add_job($job1);
        $jobcollection->add_job($job2);
        $jobcollection->add_job($job3);
        $jobcollection->save();

        $jobcollection->schedule_store_archive();
        $nextruntime = $jobcollection->get_store_archive_next_run_time();
        $this->assertNotEmpty($nextruntime);
        $this->assertNotEquals(time(), $nextruntime);

        // Reload from database.
        $jobcollection = jobcollection::load($jobcollection->get_id());
        $this->assertEquals(jobcollection::STATUS_AWAITING_PROCESSING, $jobcollection->get_data()->status);

        // Execute adhoc task.
        $this->assertEmpty(\core\task\manager::get_next_adhoc_task(time()));

        $task = \core\task\manager::get_next_adhoc_task(time() + $startdelay + 1);
        $this->assertInstanceOf('\\block_archiver\\task\\adhoc_create_jobcollection', $task);
        try {
            $task->execute();
            // Should not reach line below because exception is expected.
            $this->assertTrue(false);
        } catch (\Exception $e) {
            $this->assertTrue(true);
            \core\task\manager::adhoc_task_failed($task);
        }

        $nextruntime2 = $jobcollection->get_store_archive_next_run_time();
        $this->assertNotEquals($nextruntime, $nextruntime2);
        $this->assertEquals(jobcollection::STATUS_AWAITING_PROCESSING, $jobcollection->get_data()->status);

        // Set job3 to finished and run again.
        $job3->set_status(jobcollection::STATUS_FINISHED);

        $task = \core\task\manager::get_next_adhoc_task(time() + $startdelay + 61);
        $this->assertInstanceOf('\\block_archiver\\task\\adhoc_create_jobcollection', $task);
        try {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Should not reach line below because no exception is expected.
            $this->assertTrue(false);
        }

        $jobcollection = jobcollection::load($jobcollection->get_id());
        $this->assertEquals(jobcollection::STATUS_FINISHED, $jobcollection->get_data()->status);

        // Check creation of archive file.
        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, jobcollection::FILE_COMPONENT,
                jobcollection::FILE_AREA, $jobcollection->get_id(), '', false);

        $file = array_shift($files);
        $this->assertEquals($jobcollection->get_archive_filename(), $file->get_filename());
    }

}
