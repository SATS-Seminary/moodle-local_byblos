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

namespace local_byblos\artefact_types;

use local_byblos\artefact_type;

/**
 * File artefact type — a downloadable file attachment.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file extends artefact_type {
    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'file';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_file', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'f/document';
    }

    /**
     * Render a file artefact to HTML.
     *
     * Displays the title as a download link. Falls back to description
     * if no file is attached.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        $content = '';

        if (!empty($artefact->fileid)) {
            $fs = get_file_storage();
            $file = $fs->get_file_by_id($artefact->fileid);
            if ($file) {
                $url = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename(),
                );
                $content = \html_writer::link(
                    $url,
                    s($artefact->title),
                    ['class' => 'byblos-file-link'],
                );
            }
        }

        if (empty($content)) {
            $content = \html_writer::span(s($artefact->title), 'byblos-file-title');
        }

        return \html_writer::div($content, 'byblos-artefact byblos-artefact-file');
    }
}
