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
 * Convert Word, PowerPoint and Excel files to PDF with oxidocs.
 *
 * @package    fileconverter_oxidocs
 * @copyright  2026 Ryuji Yasukouchi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace fileconverter_oxidocs;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

use core_files\conversion;

/**
 * Document converter that runs the oxidocs program on the Moodle server.
 *
 * oxidocs lays a document out the way Word, PowerPoint or Excel does and writes the
 * PDF itself: there is no office suite to install and nothing leaves the
 * server. With the strict setting on, a document holding content oxidocs does
 * not draw is reported as failed rather than converted with that content
 * missing, and Moodle's converter manager hands it to the next enabled
 * converter.
 */
class converter implements \core_files\converter_interface {
    /** The path is set and runs. */
    const PATH_OK = 'ok';
    /** The path is not set. */
    const PATH_EMPTY = 'empty';
    /** Nothing at the path. */
    const PATH_DOESNOTEXIST = 'doesnotexist';
    /** The path is a folder. */
    const PATH_ISDIR = 'isdir';
    /** The file is not executable. */
    const PATH_NOTEXECUTABLE = 'notexecutable';
    /** The program did not run as oxidocs. */
    const PATH_ERROR = 'error';

    /** Where the release archives put the program, and the setting's default. */
    const DEFAULT_PATH = '/usr/local/bin/oxidocs';

    /** oxidocs's exit status for a document holding content it does not draw (strict only). */
    const EXIT_UNSUPPORTED = 3;

    /** Word formats, converted with `docx-to-pdf`. */
    const WORD_FORMATS = ['docx', 'docm', 'dotx', 'dotm'];

    /** PowerPoint formats, converted with `pptx-to-pdf` by oxidocs builds that have it. */
    const SLIDE_FORMATS = ['pptx', 'pptm', 'ppsx', 'ppsm', 'potx', 'potm'];

    /** Excel formats, converted with `xlsx-to-pdf` by oxidocs builds that have it. */
    const SHEET_FORMATS = ['xlsx', 'xlsm', 'xltx', 'xltm'];

    /** The command for each group of formats, in the order they are offered. */
    const COMMANDS = [
        'docx-to-pdf' => self::WORD_FORMATS,
        'pptx-to-pdf' => self::SLIDE_FORMATS,
        'xlsx-to-pdf' => self::SHEET_FORMATS,
    ];

    /** @var bool|null Whether the requirements are met, once checked. */
    protected static $requirementsmet = null;

    /** @var string[]|null Source formats this oxidocs converts, once asked. */
    protected static $sourceformats = null;

    /**
     * The configured path to oxidocs.
     *
     * @return string
     */
    public static function get_path(): string {
        $path = get_config('fileconverter_oxidocs', 'pathtooxidocs');
        // Not saved yet (as during install): the setting's default applies.
        return $path === false ? self::DEFAULT_PATH : trim($path);
    }

    /**
     * Convert the document, synchronously: oxidocs takes well under a second for most documents.
     *
     * @param conversion $conversion The conversion to perform.
     * @return $this
     */
    public function start_document_conversion(conversion $conversion) {
        $file = $conversion->get_sourcefile();
        $from = \core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $to = \core_text::strtolower($conversion->get('targetformat'));

        if (!self::are_requirements_met() || !self::supports($from, $to)) {
            $conversion->set('status', conversion::STATUS_FAILED);
            return $this;
        }

        $dir = make_unique_writable_directory(make_temp_directory('core_file/conversions'));
        // Moodle 5.0 renamed the shutdown manager into the core namespace.
        $shutdown = class_exists('\\core\\shutdown_manager') ? '\\core\\shutdown_manager' : '\\core_shutdown_manager';
        $shutdown::register_function('remove_dir', [$dir]);
        $source = $dir . '/source.' . $from;
        $target = $dir . '/output.pdf';
        if ($file->copy_content_to($source) === false) {
            $conversion->set('status', conversion::STATUS_FAILED);
            debugging("oxidocs: could not copy file {$file->get_id()} to {$source}", DEBUG_DEVELOPER);
            return $this;
        }

        $command = self::command_for($from);
        $args = [self::get_path(), $command, '--quiet'];
        if (get_config('fileconverter_oxidocs', 'strict') !== '0') {
            $args[] = '--strict';
        }
        $args[] = $source;
        $args[] = $target;

        $timeout = (int) get_config('fileconverter_oxidocs', 'timeout');
        [$status, $stderr] = self::run($args, $timeout > 0 ? $timeout : 60);

        if ($status === self::EXIT_UNSUPPORTED) {
            // Strict: the document holds content oxidocs does not draw, listed as JSON on stderr.
            // Failing hands it to the next converter.
            $conversion->set('status', conversion::STATUS_FAILED);
            debugging("oxidocs: file {$file->get_id()} handed on, content not drawn: " . trim($stderr), DEBUG_DEVELOPER);
            return $this;
        }
        if ($status !== 0 || !is_file($target) || filesize($target) === 0) {
            $conversion->set('status', conversion::STATUS_FAILED);
            debugging("oxidocs: converting file {$file->get_id()} ({$from}) failed with status {$status}: " .
                \core_text::substr(trim($stderr), 0, 500), DEBUG_NORMAL);
            return $this;
        }

        $conversion
            ->store_destfile_from_path($target)
            ->set('status', conversion::STATUS_COMPLETE)
            ->update();
        return $this;
    }

    /**
     * Run oxidocs without a shell, with a time limit.
     *
     * @param string[] $args Program and arguments.
     * @param int $timeout Seconds before the process is killed.
     * @return array [exit status, or -1 when it could not run or was killed; stderr]
     */
    protected static function run(array $args, int $timeout): array {
        $pipes = [];
        $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [-1, 'could not start ' . $args[0]];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stderr = '';
        $deadline = microtime(true) + $timeout;
        $exit = -1;
        while (true) {
            $stderr .= stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            $state = proc_get_status($process);
            if (!$state['running']) {
                $exit = $state['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process, 9);
                $stderr .= "\ntimed out after {$timeout} s";
                break;
            }
            usleep(20000);
        }
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return [$exit, $stderr];
    }

    /**
     * Conversions are synchronous, so there is nothing to poll.
     *
     * @param conversion $conversion The conversion.
     * @return $this
     */
    public function poll_conversion_status(conversion $conversion) {
        return $this;
    }

    /**
     * Convert the bundled test document and send the PDF to the browser.
     */
    public function serve_test_document() {
        $record = [
            'contextid' => \context_system::instance()->id,
            'component' => 'test',
            'filearea' => 'fileconverter_oxidocs',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'oxidocs_test.docx',
        ];
        $fs = get_file_storage();
        $docx = $fs->get_file(
            $record['contextid'],
            $record['component'],
            $record['filearea'],
            $record['itemid'],
            $record['filepath'],
            $record['filename']
        );
        if (!$docx) {
            $docx = $fs->create_file_from_pathname($record, dirname(__DIR__) . '/tests/fixtures/source.docx');
        }
        foreach (conversion::get_conversions_for_file($docx, 'pdf') as $old) {
            if ($old->get('id')) {
                $old->delete();
            }
        }
        $conversion = new conversion(0, (object) ['sourcefileid' => $docx->get_id(), 'targetformat' => 'pdf']);
        $conversion->create();
        $this->start_document_conversion($conversion);
        if ($conversion->get('status') !== conversion::STATUS_COMPLETE) {
            throw new \moodle_exception('test_oxidocserror', 'fileconverter_oxidocs', '', 'conversion failed');
        }
        readfile_accel($conversion->get_destfile(), 'application/pdf', true);
    }

    /**
     * Whether oxidocs is configured and runs.
     *
     * @return bool
     */
    public static function are_requirements_met() {
        if (self::$requirementsmet === null) {
            self::$requirementsmet = self::test_oxidocs_path()->status === self::PATH_OK;
        }
        return self::$requirementsmet;
    }

    /**
     * Check the configured path, and that the program answers as oxidocs.
     *
     * @return \stdClass status (one of the PATH_ constants) and message (the version, or the error)
     */
    public static function test_oxidocs_path() {
        $path = self::get_path();
        $result = (object) ['status' => self::PATH_OK, 'message' => null];
        if ($path === '') {
            $result->status = self::PATH_EMPTY;
        } else if (!file_exists($path)) {
            $result->status = self::PATH_DOESNOTEXIST;
        } else if (is_dir($path)) {
            $result->status = self::PATH_ISDIR;
        } else if (!file_is_executable($path)) {
            $result->status = self::PATH_NOTEXECUTABLE;
        } else {
            [$status, $output] = self::capture([$path, '--version']);
            if ($status !== 0 || strpos($output, 'oxidocs') === false) {
                $result->status = self::PATH_ERROR;
                $result->message = trim($output) ?: "exit status {$status}";
            } else {
                $result->message = trim($output);
            }
        }
        return $result;
    }

    /**
     * Run a short command and return its exit status and combined output.
     *
     * @param string[] $args Program and arguments.
     * @return array [exit status, stdout and stderr]
     */
    protected static function capture(array $args): array {
        $pipes = [];
        $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [-1, ''];
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    /**
     * Source formats this oxidocs converts.
     *
     * Each group of formats is offered when the build has its command: the
     * usage text oxidocs prints lists them. Word is in every build; PowerPoint
     * and Excel arrived later, so an older build keeps working and simply
     * declines them, leaving them to the next converter.
     *
     * @return string[]
     */
    protected static function source_formats(): array {
        if (self::$sourceformats === null) {
            [, $usage] = self::capture([self::get_path()]);
            $formats = [];
            foreach (self::COMMANDS as $command => $group) {
                if ($command === 'docx-to-pdf' || strpos($usage, $command) !== false) {
                    $formats = array_merge($formats, $group);
                }
            }
            self::$sourceformats = $formats;
        }
        return self::$sourceformats;
    }

    /**
     * The oxidocs command that converts files of this type.
     *
     * @param string $from Source file extension, lower case.
     * @return string
     */
    protected static function command_for(string $from): string {
        foreach (self::COMMANDS as $command => $group) {
            if (in_array($from, $group)) {
                return $command;
            }
        }
        return 'docx-to-pdf';
    }

    /**
     * Forget what was learnt about the configured oxidocs, after the setting changes.
     */
    public static function reset_caches(): void {
        self::$requirementsmet = null;
        self::$sourceformats = null;
    }

    /**
     * Whether this converter turns files of type $from into $to.
     *
     * @param string $from Source file extension.
     * @param string $to Target file extension.
     * @return bool
     */
    public static function supports($from, $to) {
        $from = trim(\core_text::strtolower($from));
        $to = trim(\core_text::strtolower($to));
        return $to === 'pdf' && in_array($from, self::source_formats());
    }

    /**
     * The conversions offered, for the admin page.
     *
     * @return string
     */
    public function get_supported_conversions() {
        return implode(', ', self::source_formats()) . ' → pdf';
    }
}
