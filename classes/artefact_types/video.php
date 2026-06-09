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
use local_byblos\file_manager;

/**
 * Video artefact type — recorded or uploaded video held as rich content.
 *
 * The body is HTML (with a <video> element from the satsrecorder or an
 * uploaded clip); media lives in the 'artefact' file area keyed by artefact id.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class video extends artefact_type {
    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'video';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_video', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'f/video';
    }

    /**
     * Render a video artefact to HTML.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $usercontext = \context_user::instance((int) $artefact->userid);
        $content = file_rewrite_pluginfile_urls(
            $artefact->content ?? '',
            'pluginfile.php',
            $usercontext->id,
            'local_byblos',
            file_manager::FILEAREA_ARTEFACT,
            (int) $artefact->id
        );
        $html = format_text($content, FORMAT_HTML, [
            'noclean' => false,
            'context' => \context_system::instance(),
        ]);

        return \html_writer::div($html, 'byblos-artefact byblos-artefact-video');
    }
}
