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

/**
 * Reflection-section editor form.
 *
 * The decisive element is the `editor` field: rendering it makes Moodle attach
 * a managed TinyMCE instance (with the tiny_satsrecorder plugin) and a draft
 * file area, which is the only way satsrecorder's recording buttons appear.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reflection_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata;

        $mform->addElement('hidden', 'sectionid', $custom['sectionid'] ?? 0);
        $mform->setType('sectionid', PARAM_INT);

        $mform->addElement(
            'text',
            'heading',
            get_string('reflection_heading', 'local_byblos'),
            ['size' => 48, 'maxlength' => 255]
        );
        $mform->setType('heading', PARAM_TEXT);

        $mform->addElement(
            'select',
            'framework',
            get_string('reflection_framework', 'local_byblos'),
            self::framework_options()
        );
        $mform->setDefault('framework', 'gibbs');

        $mform->addElement(
            'textarea',
            'intro',
            get_string('reflection_intro', 'local_byblos'),
            ['rows' => 2, 'style' => 'width:100%;']
        );
        $mform->setType('intro', PARAM_TEXT);

        $mform->addElement(
            'editor',
            'bodyhtml_editor',
            get_string('reflection_body', 'local_byblos'),
            null,
            $custom['editoroptions'] ?? []
        );
        $mform->setType('bodyhtml_editor', PARAM_RAW);
    }

    /**
     * The reflective-framework options.
     *
     * @return array
     */
    public static function framework_options(): array {
        return [
            'freewrite' => get_string('reflection_framework_freewrite', 'local_byblos'),
            'wsnw'      => get_string('reflection_framework_wsnw', 'local_byblos'),
            'gibbs'     => get_string('reflection_framework_gibbs', 'local_byblos'),
            'deal'      => get_string('reflection_framework_deal', 'local_byblos'),
            'kolb'      => get_string('reflection_framework_kolb', 'local_byblos'),
        ];
    }
}
