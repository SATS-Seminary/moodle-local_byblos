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
 * List all artefacts with type filter.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\artefact;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$type = optional_param('type', '', PARAM_ALPHANUMEXT);

$PAGE->set_url(new moodle_url('/local/byblos/artefacts.php', ['type' => $type]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('myartefacts', 'local_byblos'));
$PAGE->set_heading(get_string('myartefacts', 'local_byblos'));

$artefacts = artefact::list_by_user($USER->id, $type);

$artdata = [];
foreach ($artefacts as $a) {
    $artdata[] = [
        'id'          => $a->id,
        'title'       => format_string($a->title, true, ['escape' => false]),
        'type'        => $a->artefacttype,
        'typelabel'   => get_string('artefacttype_' . $a->artefacttype, 'local_byblos'),
        'description' => format_text($a->description, FORMAT_HTML),
        'viewurl'     => (new moodle_url('/local/byblos/artefact.php', ['id' => $a->id]))->out(false),
        'editurl'     => (new moodle_url('/local/byblos/artefact.php', ['id' => $a->id, 'action' => 'edit']))->out(false),
        'deleteurl'   => (new moodle_url('/local/byblos/delete.php'))->out(false),
        'timecreated' => userdate($a->timecreated),
    ];
}

// Type filter tabs.
$alltypes = ['', 'text', 'image', 'file', 'link', 'audio', 'video', 'embed',
    'badge', 'course_completion', 'blog_entry'];
$typefilters = [];
foreach ($alltypes as $t) {
    $typefilters[] = [
        'value'  => $t,
        'label'  => ($t === '') ? get_string('type_all', 'local_byblos')
            : get_string('artefacttype_' . $t, 'local_byblos'),
        'active' => ($t === $type),
        'url'    => (new moodle_url('/local/byblos/artefacts.php', $t ? ['type' => $t] : []))->out(false),
    ];
}

$data = [
    'artefacts'      => $artdata,
    'has_artefacts'  => !empty($artdata),
    'typefilters'    => $typefilters,
    'newartefacturl' => (new moodle_url('/local/byblos/artefact.php', ['action' => 'edit']))->out(false),
    'dashurl'        => (new moodle_url('/local/byblos/view.php'))->out(false),
    'sesskey'        => sesskey(),
];

$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/artefacts_list', $data);
echo $OUTPUT->footer();
