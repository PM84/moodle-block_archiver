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
 * Handles upgrading instances of this plugin.
 *
 * @package    block_archiver
 * @copyright  2024, ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the badges block
 *
 * @param int $oldversion
 * @param object $block
 */
function xmldb_block_archiver_upgrade($oldversion, $block) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024022715) {

        // Define table block_archiver_jobcollection to be created.
        $table = new xmldb_table('block_archiver_jobcollection');

        // Adding fields to table block_archiver_jobcollection.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timelastrun', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table block_archiver_jobcollection.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_archiver_jobcollection.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));

        // Conditionally launch create table for block_archiver_jobcollection.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_archiver_jobcollection_jobs to be created.
        $table = new xmldb_table('block_archiver_jobcollection_jobs');

        // Adding fields to table block_archiver_jobcollection_jobs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jobcollectionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table block_archiver_jobcollection_jobs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table block_archiver_jobcollection_jobs.
        $table->add_index('jobcollectionid', XMLDB_INDEX_NOTUNIQUE, array('jobcollectionid'));
        $table->add_index('jobid', XMLDB_INDEX_NOTUNIQUE, array('jobid'));

        // Conditionally launch create table for block_archiver_jobcollection_jobs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Archiver savepoint reached.
        upgrade_block_savepoint(true, 2024022715, 'archiver');
    }

    return true;
}
