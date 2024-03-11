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
 * Library code for manipulating PDFs
 *
 * @package block_archiver
 * @copyright 2024 Andreas Wagner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_archiver\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/assign/feedback/editpdf/classes/pdf.php');
require_once($CFG->dirroot . '/lib/pdflib.php');

/**
 * Library code for manipulating PDFs
 *
 * Inherited to keep the option to introduce a header in the generated pdf
 * of generate a content listing.
 *
 *
 * @package block_archiver
 * @copyright 2014 Andreas Wagner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdfmerger extends \assignfeedback_editpdf\pdf {

    /**
     * Combine the given PDF files into a single PDF. Optionally add a coversheet and coversheet fields.
     *
     * @param string[] $pdflist  the filenames of the files to combine
     * @param string $outfilename the filename to write to
     * @return int the number of pages in the combined PDF
     */
    public function get_page_numbers($pdflist, $outfilename) {

        raise_memory_limit(MEMORY_EXTRA);
        $olddebug = error_reporting(0);

        $this->setPageUnit('pt');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->scale = 72.0 / 100.0;
        $this->SetFont('helvetica', '', 16.0 * $this->scale);
        $this->SetTextColor(0, 0, 0);

        $totalpagecount = 0;
        $pagecounts = [];

        foreach ($pdflist as $file) {
            $pagecounts[$file] = $this->setSourceFile($file);
            $totalpagecount += $pagecounts[$file];
        }

        error_reporting($olddebug);
        return $pagecounts;
    }

    /**
     * Combine the given PDF files into a single PDF. Optionally add a coversheet and coversheet fields.
     *
     * @param string[] $pdflist  the filenames of the files to combine
     * @param string $outfilename the filename to write to
     * @return int the number of pages in the combined PDF
     */
    public function combine_pdfs($pdflist, $outfilename) {

        raise_memory_limit(MEMORY_EXTRA);
        $olddebug = error_reporting(0);

        $this->setPageUnit('pt');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->scale = 72.0 / 100.0;
        $this->SetFont('helvetica', '', 16.0 * $this->scale);
        $this->SetTextColor(0, 0, 0);

        $totalpagecount = 0;

        foreach ($pdflist as $file) {
            $pagecount = $this->setSourceFile($file);
            $totalpagecount += $pagecount;
            for ($i = 1; $i <= $pagecount; $i++) {
                $this->create_page_from_source($i);
            }
        }

        $this->save_pdf($outfilename);
        error_reporting($olddebug);

        return $totalpagecount;
    }

    /**
     * Combine the given PDF files into a single PDF.
     *
     * @param string[] $pdflist  the filenames of the files to combine
     * @param string $outfilename the filename to write to
     * @return int the number of pages in the combined PDF
     */
    public function combine_pdfs_with_header($pdflist, $outfilename) {

        raise_memory_limit(MEMORY_EXTRA);
        $olddebug = error_reporting(0);

        $this->setPageUnit('pt');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->scale = 72.0 / 100.0;
        $this->SetFont('helvetica', '', 16.0 * $this->scale);
        $this->SetTextColor(0, 0, 0);

        $totalpagecount = 0;

        foreach ($pdflist as $document) {
            $pagecount = $this->setSourceFile($document->filename);
            $totalpagecount += $pagecount;
            for ($i = 1; $i <= $pagecount; $i++) {
                $this->create_page_from_source($i);

                if (!empty($document->header)) {
                    $this->MultiCell(0, 20, $document->header, 'B', 'L', false, 1, '', '', true, 1);
                }
            }
        }

        $this->save_pdf($outfilename);
        error_reporting($olddebug);

        return $totalpagecount;
    }

}
