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
 * This file keeps track of upgrades to the paynow enrolment plugin
 *
 * @package    enrol
 * @subpackage paynow
 * @copyright  2014 Tawanda Kembo
 * @author     Tawanda Kembo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_enrol_paynow_upgrade($oldversion=0) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Add instanceid field to enrol_paynow_transactions table
    if ($oldversion < 2011121300) {
        $table = new xmldb_table('enrol_paynow_transactions');
        $field = new xmldb_field('instanceid');
        if (!$dbman->field_exists($table, $field)) {
            $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'userid');
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2011121300, 'enrol', 'paynow');
    }

    return true;
}

