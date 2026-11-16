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

namespace fileconverter_oxidocs;

use core_files\conversion;

/**
 * Tests for the Oxi document converter.
 *
 * The conversion tests need oxidocs at the configured path (in CI: downloaded
 * from the Oxi release); they are skipped where it is missing.
 *
 * @package    fileconverter_oxidocs
 * @copyright  2026 Ryuji Yasukouchi
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \fileconverter_oxidocs\converter
 */
final class converter_test extends \advanced_testcase {
    /**
     * Point the plugin at oxidocs, or skip.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $path = getenv('OXIDOCS_PATH') ?: converter::DEFAULT_PATH;
        set_config('pathtooxidocs', $path, 'fileconverter_oxidocs');
        converter::reset_caches();
        if (!is_executable($path)) {
            $this->markTestSkipped("oxidocs not found at {$path}");
        }
    }

    /**
     * Store a fixture as a file and run a conversion of it to PDF.
     *
     * @param string $fixture File name under tests/fixtures.
     * @return conversion
     */
    protected function convert(string $fixture): conversion {
        $fs = get_file_storage();
        $file = $fs->create_file_from_pathname([
            'contextid' => \context_system::instance()->id, 'component' => 'test', 'filearea' => 'oxidocs',
            'itemid' => 0, 'filepath' => '/', 'filename' => $fixture,
        ], __DIR__ . '/fixtures/' . $fixture);
        $conversion = new conversion(0, (object) ['sourcefileid' => $file->get_id(), 'targetformat' => 'pdf']);
        $conversion->create();
        (new converter())->start_document_conversion($conversion);
        return $conversion;
    }

    public function test_the_configured_program_answers(): void {
        $this->assertSame(converter::PATH_OK, converter::test_oxidocs_path()->status);
    }

    public function test_supports_word_to_pdf_only(): void {
        $this->assertTrue(converter::supports('docx', 'pdf'));
        $this->assertTrue(converter::supports('DOCX', 'PDF'));
        $this->assertFalse(converter::supports('docx', 'png'));
        $this->assertFalse(converter::supports('odt', 'pdf'));
    }

    public function test_converts_a_word_document(): void {
        $conversion = $this->convert('source.docx');
        $this->assertSame(conversion::STATUS_COMPLETE, $conversion->get('status'));
        $this->assertStringStartsWith('%PDF-', $conversion->get_destfile()->get_content());
    }

    public function test_a_missing_program_fails_instead_of_converting(): void {
        set_config('pathtooxidocs', '/nonexistent/oxidocs', 'fileconverter_oxidocs');
        $this->assertSame(converter::PATH_DOESNOTEXIST, converter::test_oxidocs_path()->status);
    }

    public function test_an_unreadable_file_fails(): void {
        $conversion = $this->convert('broken.docx');
        // The failure is reported through debugging(); older Moodle test runs flag that unless cleared.
        $this->resetDebugging();
        $this->assertSame(conversion::STATUS_FAILED, $conversion->get('status'));
    }

    public function test_each_format_goes_to_its_command_when_the_build_has_it(): void {
        // A stand-in that advertises all three commands and writes the one it was given into the PDF.
        set_config('pathtooxidocs', __DIR__ . '/fixtures/fake-oxidocs', 'fileconverter_oxidocs');
        converter::reset_caches();
        $this->assertTrue(converter::supports('xlsx', 'pdf'));
        $this->assertTrue(converter::supports('pptx', 'pdf'));
        $this->assertStringContainsString('docx-to-pdf', $this->convert('source.docx')->get_destfile()->get_content());
        $this->assertStringContainsString('xlsx-to-pdf', $this->convert('sheet.xlsx')->get_destfile()->get_content());
    }

    public function test_formats_the_build_lacks_are_declined(): void {
        // The stand-in in Word-only mode, as builds before PowerPoint and Excel support answer.
        set_config('pathtooxidocs', __DIR__ . '/fixtures/fake-oxidocs-word-only', 'fileconverter_oxidocs');
        converter::reset_caches();
        $this->assertTrue(converter::supports('docx', 'pdf'));
        $this->assertFalse(converter::supports('pptx', 'pdf'));
        $this->assertFalse(converter::supports('xlsx', 'pdf'));
    }
}
