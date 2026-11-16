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
 * Check that oxidocs is configured, and offer a converted test document.
 *
 * @package    fileconverter_oxidocs
 * @copyright  2026 Ryuji Yasukouchi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

$sendpdf = optional_param('sendpdf', 0, PARAM_BOOL);

$PAGE->set_url(new moodle_url('/files/converter/oxidocs/testoxidocs.php'));
$PAGE->set_context(context_system::instance());

require_login();
require_capability('moodle/site:config', context_system::instance());

$heading = get_string('test_oxidocs', 'fileconverter_oxidocs');
$PAGE->set_heading($heading);
$PAGE->set_title($heading);

if ($sendpdf) {
    require_sesskey();
    (new \fileconverter_oxidocs\converter())->serve_test_document();
    die();
}

$result = \fileconverter_oxidocs\converter::test_oxidocs_path();
if ($result->status === \fileconverter_oxidocs\converter::PATH_OK) {
    $msg = $OUTPUT->notification(get_string('test_oxidocsok', 'fileconverter_oxidocs', s($result->message)), 'success');
    $msg .= html_writer::link(
        new moodle_url($PAGE->url, ['sendpdf' => 1, 'sesskey' => sesskey()]),
        get_string('test_oxidocsdownload', 'fileconverter_oxidocs')
    );
} else if ($result->status === \fileconverter_oxidocs\converter::PATH_ERROR) {
    $msg = $OUTPUT->notification(get_string('test_oxidocserror', 'fileconverter_oxidocs', s($result->message)), 'warning');
} else {
    $msg = $OUTPUT->notification(get_string('test_oxidocs' . $result->status, 'fileconverter_oxidocs'), 'warning');
}
$msg .= $OUTPUT->continue_button(new moodle_url('/admin/settings.php', ['section' => 'fileconverteroxidocs']));

echo $OUTPUT->header();
echo $OUTPUT->box($msg, 'generalbox');
echo $OUTPUT->footer();
