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
 * Class for file operations.
 *
 * @package    block_archiver
 * @author     2024 Andreas Wagner, KnowHow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\local;

defined('MOODLE_INTERNAL') || die();

use stored_file;

/**
 * Class for file operations.
 *
 * @package    block_archiver
 * @author     2024 Andreas Wagner, KnowHow
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filemanager extends \quiz_archiver\FileManager {

    /** @var string foldername of attachments in archive. */
    const ATTACHMENT_FOLDER = 'attachments';

    /**
     * Get the merged pdf filename.
     *
     * @return string
     */
    protected function get_pdf_overview_filename(): string {
        return 'answers-overview.pdf';
    }

    /**
     * Get files recursively from directory.
     *
     * @param string $tmpdir
     * @param string $extension
     * @return array
     */
    protected function get_files_from_dir(string $tmpdir, string $extension = ''): array {

        $directory = new \RecursiveDirectoryIterator($tmpdir, \FilesystemIterator::FOLLOW_SYMLINKS);
        $filter = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) use ($extension) {
                    // Skip hidden files and directories.
                    if ($current->getFilename()[0] === '.') {
                        return false;
                    }
                    if ($current->isDir()) {
                        // Stop recursion on attachment folders level.
                        return ($current->getFilename() != self::ATTACHMENT_FOLDER);
                    }
                    // Only consume files of interest.
                    return (($extension == '') || (strpos($current->getExtension(), $extension) !== false));
                }
        );
        $iterator = new \RecursiveIteratorIterator($filter);
        $files = array();
        foreach ($iterator as $info) {
            $relativepath = substr($info->getPathname(), strlen($tmpdir) + 1);
            $files[$relativepath] = $info->getPathname();
        }

        return $files;
    }

    /**
     * Collect all pdf files and store it to overall file.
     *
     * @param string $tmpdir
     * @return int number of pages in the pdf file.
     *
     * @throws \moodle_exception
     */
    public function create_merged_pdf(string $tmpdir): int {

        $files = $this->get_files_from_dir($tmpdir, 'pdf');

        if (count($files) == 0) {
            throw new \moodle_exception('no pdf files found');
        }

        // Create pdf with pdf merger.
        $targetpathname = $tmpdir . "/" . $this->get_pdf_overview_filename();

        $pdfmerger = new pdfmerger();
        return $pdfmerger->combine_pdfs(array_values($files), $targetpathname);
    }

    /**
     * Store whole tempdir in a file
     *
     * @param string $tmpdir
     * @param jobcollection $jobcollection
     * @throws \moodle_exception
     */
    public function store_archive_file(string $tmpdir, jobcollection $jobcollection) {

        // Intentionally not use application/x-gzip because the filepath is too long for
        // it restriction to 100 characters.
        $packer = get_file_packer('application/zip');
        $files = $this->get_files_from_dir($tmpdir);

        if (count($files) == 0) {
            throw new \moodle_exception('no files found');
        }

        $zipfilename = $tmpdir . $jobcollection->get_archive_filename();
        if (!$packer->archive_to_pathname($files, $zipfilename)) {
            throw new \moodle_exception('creating archive failed');
        }

        $usercontext = \context_user::instance($jobcollection->get_data()->userid);
        $filerecord = [
            'contextid' => $usercontext->id,
            'component' => jobcollection::FILE_COMPONENT,
            'filearea' => jobcollection::FILE_AREA,
            'filepath' => '/',
            'itemid' => $jobcollection->get_id(),
            'filename' => $jobcollection->get_archive_filename()
        ];

        // Store pdf in moodle files.
        $fs = get_file_storage();
        $fs->create_file_from_pathname($filerecord, $zipfilename);
    }

    /**
     * Extracts the data of a single attempt from a given artifact file into an
     * independent archive. Created files are stored inside the temp filearea and
     * will be automatically deleted after a certain time.
     *
     * @param stored_file $artifactfile Archive artifact file to extract attempt from
     * @param int $jobid ID of the job this artifact belongs to
     * @param int $attemptid ID of the attempt to extract
     * @throws \coding_exception
     * @throws \moodle_exception On error
     */
    public function copy_attempt_data_from_artifact_to_path(
            stored_file $artifactfile,
            int $jobid,
            int $attemptid,
            string $pathname
    ) {

        // Prepare.
        $packer = get_file_packer('application/x-gzip');
        $workdir = $pathname . "/jid{$jobid}_cid{$this->course_id}_cmid{$this->cm_id}_qid{$this->quiz_id}_aid{$attemptid}";

        // Wrap in try-catch to ensure cleanup on exit.
        try {
            // Extract metadata file from artifact and find relevant path information.
            $packer->extract_to_pathname($artifactfile, $workdir, [
                self::ARTIFACT_METADATA_FILE,
            ]);
            $metadata = array_map('str_getcsv', file($workdir . "/" . self::ARTIFACT_METADATA_FILE));

            if ($metadata[0][0] !== 'attemptid' || $metadata[0][9] !== 'path') {
                // Fail silently for old archives for now.
                if ($metadata[0][9] === 'report_filename') {
                    throw new \invalid_state_exception('Old artifact format is skipped');
                } else {
                    throw new \moodle_exception('Invalid metadata file in artifact archive');
                }
            }

            // Search for attempt path.
            $attemptpath = null;
            foreach ($metadata as $row) {
                if ($row[0] == $attemptid) {
                    $attemptpath = $row[9];
                    break;
                }
            }

            if (!$attemptpath) {
                throw new \moodle_exception('Attempt not found in metadata file');
            }

            // Extract attempt data from artifact
            // All files must be given explicitly to tgz_packer::extract_to_pathname(). Wildcards
            // are unsupported. Therefore, we list the contents and filter the index. This reduces
            // space and time complexity compared to extracting the whole archive at once.
            $attemptfiles = array_map(
                    fn($file): string => $file->pathname,
                    array_filter($packer->list_files($artifactfile), function ($file) use ($attemptpath) {
                        return strpos($file->pathname, ltrim($attemptpath, '/')) === 0;
                    })
            );
            if (!$packer->extract_to_pathname($artifactfile, $workdir . "/attemptdata", $attemptfiles)) {
                throw new \moodle_exception('Failed to extract attempt data from artifact archive');
            }
        } catch (\Exception $e) {
            // Cleanup.
            remove_dir($workdir);

            // Ignore skipped archives but always execute cleanup code!
            if (!($e instanceof \invalid_state_exception)) {
                throw $e;
            }
        }
    }

}
