<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings for the Oxi document converter.
 *
 * @package    fileconverter_oxidocs
 * @copyright  2026 Ryuji Yasukouchi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configexecutable('fileconverter_oxidocs/pathtooxidocs',
        new lang_string('pathtooxidocs', 'fileconverter_oxidocs'),
        new lang_string('pathtooxidocs_help', 'fileconverter_oxidocs'),
        '/usr/local/bin/oxidocs'));

    $settings->add(new admin_setting_configcheckbox('fileconverter_oxidocs/strict',
        new lang_string('strict', 'fileconverter_oxidocs'),
        new lang_string('strict_help', 'fileconverter_oxidocs'),
        1));

    $settings->add(new admin_setting_configduration('fileconverter_oxidocs/timeout',
        new lang_string('timeout', 'fileconverter_oxidocs'),
        new lang_string('timeout_help', 'fileconverter_oxidocs'),
        60, 1));

    $url = new moodle_url('/files/converter/oxidocs/testoxidocs.php');
    $link = html_writer::link($url, get_string('test_oxidocs', 'fileconverter_oxidocs'));
    $settings->add(new admin_setting_heading('fileconverter_oxidocs/test', '', $link));
}
