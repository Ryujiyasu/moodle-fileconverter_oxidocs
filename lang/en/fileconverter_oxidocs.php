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
 * Strings for the Oxi document converter.
 *
 * @package    fileconverter_oxidocs
 * @copyright  2026 Ryuji Yasukouchi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pathtooxidocs'] = 'Path to oxidocs';
$string['pathtooxidocs_help'] = 'The oxidocs program, from https://github.com/Ryujiyasu/oxi/releases (static Linux builds need nothing else installed). It converts Word, PowerPoint and Excel files to PDF on this server, without LibreOffice or Microsoft Office, so that teachers can annotate submissions.';
$string['pluginname'] = 'Oxi (Word, PowerPoint and Excel, no LibreOffice)';
$string['privacy:metadata'] = 'The Oxi document converter converts files on the Moodle server and does not store or send any personal data.';
$string['strict'] = 'Hand over what Oxi cannot fully draw';
$string['strict_help'] = 'When a document holds content Oxi does not draw yet (some chart types, for example), report the conversion as failed instead of producing a PDF with that content missing. Moodle then tries the next enabled document converter, so place Oxi above it in Manage document converters.';
$string['test_oxidocs'] = 'Test the oxidocs path';
$string['test_oxidocsdoesnotexist'] = 'The oxidocs path does not point to a file. Please review the setting.';
$string['test_oxidocsdownload'] = 'Download the converted PDF test file.';
$string['test_oxidocsempty'] = 'The oxidocs path is not set. Please review the setting.';
$string['test_oxidocserror'] = 'oxidocs did not run: {$a}';
$string['test_oxidocsisdir'] = 'The oxidocs path points to a folder. Please include the program itself in the path.';
$string['test_oxidocsnotexecutable'] = 'The oxidocs path points to a file that is not executable.';
$string['test_oxidocsok'] = 'oxidocs is configured ({$a}).';
$string['timeout'] = 'Time limit';
$string['timeout_help'] = 'A conversion that takes longer than this is stopped and reported as failed, so that the next converter can try.';
