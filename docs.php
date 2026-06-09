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
 * In-plugin documentation hub.
 *
 * Renders the static help pages: a landing page plus per-topic guides and
 * use-case walkthroughs. The requested topic comes from ?topic=… and is
 * validated against \local_byblos\docs::topics(). Each topic's prose lives in
 * a templates/docs/<topic>.mustache partial; this controller wraps it in the
 * shared docs shell (sidebar nav + prev/next).
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\docs;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$topic = optional_param('topic', 'index', PARAM_ALPHANUMEXT);
if (!docs::exists($topic)) {
    $topic = 'index';
}

$topics = docs::topics();
$pagetitle = get_string($topics[$topic]['title'], 'local_byblos');

$PAGE->set_url(docs::topic_url($topic));
$PAGE->set_context($context);
$PAGE->set_title(get_string('docs_title', 'local_byblos') . ': ' . $pagetitle);
$PAGE->set_heading(get_string('docs_title', 'local_byblos'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-docs');

// Resolve documentation screenshots (pix/docs/*) to URLs the templates embed as
// <img src="{{pix.<name>}}">. Globbing means new screenshots wire themselves in
// as soon as the file is dropped in; a figure whose screenshot is not yet present
// simply keeps its dashed placeholder.
$pix = [];
foreach (glob($CFG->dirroot . '/local/byblos/pix/docs/*.{png,jpg,jpeg,gif,svg,webp}', GLOB_BRACE) as $imgfile) {
    $key = pathinfo($imgfile, PATHINFO_FILENAME);
    $pix[$key] = $OUTPUT->image_url('docs/' . $key, 'local_byblos')->out(false);
}

// Render the topic body. Content templates receive cross-link URLs plus a couple
// of common destination URLs so prose can deep-link into the live UI.
$body = $OUTPUT->render_from_template('local_byblos/docs/' . $topic, [
    'links'      => docs::topiclinks(),
    'dashurl'    => (new moodle_url('/local/byblos/view.php'))->out(false),
    'newpageurl' => (new moodle_url('/local/byblos/newpage.php'))->out(false),
    'pix'        => $pix,
]);

$adjacent = docs::adjacent($topic);

$data = [
    'title'      => $pagetitle,
    'istopic'    => ($topic !== 'index'),
    'docshome'   => docs::topic_url('index')->out(false),
    'dashurl'    => (new moodle_url('/local/byblos/view.php'))->out(false),
    'navgroups'  => docs::nav($topic),
    'content'    => $body,
    'hasprev'    => !empty($adjacent['prev']),
    'prev'       => $adjacent['prev'],
    'hasnext'    => !empty($adjacent['next']),
    'next'       => $adjacent['next'],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/docs', $data);
echo $OUTPUT->footer();
