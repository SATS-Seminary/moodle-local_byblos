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
 * Link artefact type — a bookmark to an external URL (stored in content).
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link extends artefact_type {
    /**
     * Get the machine-readable type name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'link';
    }

    /**
     * Get the localised display name.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('artefacttype_link', 'local_byblos');
    }

    /**
     * Get the icon identifier.
     *
     * @return string
     */
    public function get_icon(): string {
        return 'i/externallink';
    }

    /**
     * Render a link artefact to HTML.
     *
     * @param \stdClass $artefact The artefact record.
     * @return string HTML output.
     */
    public function render(\stdClass $artefact): string {
        $url = trim($artefact->content ?? '');
        $label = ($artefact->title !== '') ? $artefact->title : $url;

        if ($url !== '') {
            $content = \html_writer::link(
                $url,
                s($label),
                ['class' => 'byblos-link', 'target' => '_blank', 'rel' => 'noopener noreferrer']
            );
        } else {
            $content = \html_writer::span(s($label), 'byblos-link-title');
        }

        return \html_writer::div($content, 'byblos-artefact byblos-artefact-link');
    }
}
