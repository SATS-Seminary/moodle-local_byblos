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

namespace local_byblos\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_byblos\artefact_type;

/**
 * Type-aware artefact create/edit form.
 *
 * One server-rendered form whose fields switch by the selected type using
 * Moodle's native hideIf: a rich editor (with the satsrecorder, for recording
 * audio/video) for text/audio/video, a file picker for image/file, and a URL
 * field for link/embed.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class artefact_form extends \moodleform {
    /**
     * Editor options for the rich-content field (enables satsrecorder via maxfiles).
     *
     * @return array
     */
    public static function editor_options(): array {
        global $USER;

        return [
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'maxbytes'  => 200 * 1024 * 1024,
            'context'   => \context_user::instance($USER->id),
            'subdirs'   => 0,
            'trusttext' => false,
        ];
    }

    /**
     * File picker options for an image artefact.
     *
     * @return array
     */
    public static function image_options(): array {
        return ['maxbytes' => 20 * 1024 * 1024, 'maxfiles' => 1, 'accepted_types' => ['web_image']];
    }

    /**
     * File picker options for a generic file artefact.
     *
     * @return array
     */
    public static function file_options(): array {
        return ['maxbytes' => 100 * 1024 * 1024, 'maxfiles' => 1, 'accepted_types' => '*'];
    }

    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Type selector (only manually-creatable types).
        $options = [];
        foreach (artefact_type::creatable_types() as $type) {
            $handler = artefact_type::get($type);
            $options[$type] = $handler ? $handler->get_display_name() : $type;
        }
        $mform->addElement('select', 'type', get_string('artefacttype', 'local_byblos'), $options);
        $mform->setDefault('type', 'text');

        $mform->addElement(
            'text',
            'title',
            get_string('artefacttitle', 'local_byblos'),
            ['maxlength' => 255, 'size' => 60]
        );
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'description',
            get_string('artefactdesc', 'local_byblos'),
            ['rows' => 3, 'style' => 'width:100%;']
        );
        $mform->setType('description', PARAM_TEXT);

        // Audio/video recorder hint, shown as a prominent alert above the editor.
        $mform->addElement(
            'static',
            'content_help',
            '',
            '<div class="alert alert-primary" role="alert">'
                . get_string('artefact_content_help', 'local_byblos')
                . '</div>'
        );
        $mform->hideIf('content_help', 'type', 'in', ['text', 'image', 'file', 'link', 'embed']);

        // Rich content (text / audio / video). The editor carries the satsrecorder.
        $mform->addElement(
            'editor',
            'content_editor',
            get_string('artefactcontent', 'local_byblos'),
            null,
            self::editor_options()
        );
        $mform->setType('content_editor', PARAM_RAW);
        $mform->hideIf('content_editor', 'type', 'in', ['image', 'file', 'link', 'embed']);

        // Image upload.
        $mform->addElement(
            'filepicker',
            'imagefile',
            get_string('artefact_image', 'local_byblos'),
            null,
            self::image_options()
        );
        $mform->hideIf(
            'imagefile',
            'type',
            'in',
            ['text', 'audio', 'video', 'file', 'link', 'embed']
        );

        // Generic file upload.
        $mform->addElement(
            'filepicker',
            'attachment',
            get_string('artefact_attachment', 'local_byblos'),
            null,
            self::file_options()
        );
        $mform->hideIf(
            'attachment',
            'type',
            'in',
            ['text', 'audio', 'video', 'image', 'link', 'embed']
        );

        // URL (link / embed).
        $mform->addElement(
            'text',
            'url',
            get_string('artefact_url', 'local_byblos'),
            ['size' => 60, 'placeholder' => 'https://...']
        );
        $mform->setType('url', PARAM_URL);
        $mform->addElement('static', 'url_help', '', get_string('artefact_url_help', 'local_byblos'));
        $mform->hideIf('url', 'type', 'in', ['text', 'audio', 'video', 'image', 'file']);
        $mform->hideIf('url_help', 'type', 'in', ['text', 'audio', 'video', 'image', 'file']);

        $this->add_action_buttons(true, get_string('saveartefact', 'local_byblos'));
    }
}
