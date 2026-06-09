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
 * Tag areas defined by local_byblos.
 *
 * Registers the artefact table as a taggable area so learners can tag their
 * artefacts with free or standard tags, reusing Moodle's core tag UI
 * (autocomplete, tag pages). Tagged-item lookups are owner-scoped by the
 * callback so a tag page never exposes another user's private artefacts.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tagareas = [
    [
        'itemtype'     => 'local_byblos_artefact',
        'component'    => 'local_byblos',
        'callback'     => 'local_byblos_get_tagged_artefacts',
        'callbackfile' => '/local/byblos/lib.php',
    ],
];
