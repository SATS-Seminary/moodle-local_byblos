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
 * View a single portfolio page.
 *
 * Loads the page, checks access (owner or shared), and renders the
 * page with its sections. Shows edit/share/export buttons for the owner.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\collection;
use local_byblos\page;
use local_byblos\section;
use local_byblos\share;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$id = required_param('id', PARAM_INT);
$preview = (bool) optional_param('preview', 0, PARAM_BOOL);

$PAGE->set_url(new moodle_url(
    '/local/byblos/page.php',
    $preview ? ['id' => $id, 'preview' => 1] : ['id' => $id]
));
$PAGE->set_context($context);

$pagerecord = page::get($id);
if (!$pagerecord) {
    throw new moodle_exception('pagenotfound', 'local_byblos');
}

// Access check: owner, or a share record grants access (page-level or via collection membership).
$isowner = ((int) $pagerecord->userid === (int) $USER->id);
if (!$isowner) {
    require_capability('local/byblos:viewshared', $context);
    if (!share::can_view_page((int) $USER->id, (int) $pagerecord->id)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('viewpage', 'local_byblos'));
    }
}

$PAGE->set_title(format_string($pagerecord->title));
$PAGE->set_heading(format_string($pagerecord->title));
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-pageview');
if ($preview) {
    // Chrome-free preview — no Moodle nav, no owner action buttons.
    $PAGE->set_pagelayout('embedded');
    $PAGE->add_body_class('byblos-body-embedded');
}

// Render the full themed portfolio HTML server-side (same path the snapshot
// viewer uses), then hand it to the template as rendered_content.
$hassections = \local_byblos\section::get_by_page($id);
$renderedcontent = \local_byblos\renderer::render_page($pagerecord, (int) $pagerecord->userid);

// Collection nav strip — shown to viewers (shared users) and in preview mode.
// Hidden from the owner in non-preview view because the dashboard/collection
// index already surface every page they own.
$collectionnav = [
    'has_nav'          => false,
    'collection_title' => '',
    'collection_url'   => '',
    'pages'            => [],
];
$shownav = $preview || !$isowner;
if ($shownav) {
    $primary = collection::get_primary_for_page((int) $pagerecord->id);
    if ($primary) {
        $pages = collection::get_pages((int) $primary->id);
        $navpages = [];
        foreach ($pages as $p) {
            // Preview = simulate a collection-share viewer, so every sibling is shown.
            // Non-preview (non-owner) = filter by what this viewer can actually see.
            $visible = $preview || share::can_view_page((int) $USER->id, (int) $p->id);
            if (!$visible) {
                continue;
            }
            $pageurl = new moodle_url(
                '/local/byblos/page.php',
                $preview ? ['id' => $p->id, 'preview' => 1] : ['id' => $p->id]
            );
            $navpages[] = [
                'id'         => (int) $p->id,
                'title'      => format_string($p->title, true, ['escape' => false]),
                'url'        => $pageurl->out(false),
                'is_current' => ((int) $p->id === (int) $pagerecord->id),
            ];
        }
        if (!empty($navpages)) {
            $collurl = new moodle_url('/local/byblos/collection.php', ['id' => $primary->id]);
            $collectionnav = [
                'has_nav'          => true,
                'collection_title' => format_string($primary->title, true, ['escape' => false]),
                'collection_url'   => $collurl->out(false),
                'pages'            => $navpages,
            ];
        }
    }
}

// Can the current viewer post announcements in at least one course? If so we
// surface the "Get announcement link" picker. (Cheap probe — the full list is
// fetched lazily by the AMD module when the popover opens.)
$canannounce = false;
if ($isowner && !$preview) {
    foreach (enrol_get_my_courses(['id']) as $c) {
        if ((int) $c->id === SITEID) {
            continue;
        }
        $ccontext = context_course::instance((int) $c->id, IGNORE_MISSING);
        if ($ccontext && has_capability('moodle/course:update', $ccontext)) {
            $canannounce = true;
            break;
        }
    }
}

// Page feedback (logged-in, owner-moderated). Never shown in preview/embedded mode.
$feedbackmode = $pagerecord->feedback ?? 'off';
$canleavefeedback = (!$isowner) && !$preview
    && share::can_leave_feedback((int) $USER->id, (int) $pagerecord->id);
$feedbackvisible = !$preview && ($feedbackmode !== 'off')
    && ($isowner || share::can_view_page((int) $USER->id, (int) $pagerecord->id));
$feedbackrows = [];
if ($feedbackvisible) {
    foreach (\local_byblos\feedback::list_for_page((int) $pagerecord->id, $isowner) as $row) {
        $author = $DB->get_record('user', ['id' => $row->authorid]);
        $feedbackrows[] = [
            'id'            => (int) $row->id,
            'authorname'    => $author ? fullname($author) : '',
            'is_teacher'    => ($row->authorrole === 'teacher'),
            'body'          => format_text($row->body, FORMAT_PLAIN),
            'is_hidden'     => ((int) $row->visible === 0),
            'iscurrentuser' => ((int) $row->authorid === (int) $USER->id),
            'timecreated'   => userdate((int) $row->timecreated),
        ];
    }
}

$data = [
    'page'        => [
        'id'          => $pagerecord->id,
        'title'       => format_string($pagerecord->title, true, ['escape' => false]),
        'description' => format_text($pagerecord->description, FORMAT_HTML),
        'status'      => $pagerecord->status,
        'statuslabel' => get_string('status_' . $pagerecord->status, 'local_byblos'),
        'is_draft'    => ($pagerecord->status === 'draft'),
        'themekey'    => $pagerecord->themekey,
        'timecreated' => userdate($pagerecord->timecreated),
    ],
    'collection_nav'   => $collectionnav,
    'rendered_content' => $renderedcontent,
    'has_sections' => !empty($hassections),
    'isowner'     => $isowner && !$preview,
    'can_announce' => $canannounce,
    'ispreview'   => $preview,
    'previewurl'  => (new moodle_url('/local/byblos/page.php', ['id' => $id, 'preview' => 1]))->out(false),
    'editurl'     => (new moodle_url('/local/byblos/editpage.php', ['id' => $id]))->out(false),
    'shareurl'    => (new moodle_url('/local/byblos/share.php', ['id' => $id, 'type' => 'page']))->out(false),
    'exporturl'   => (new moodle_url('/local/byblos/page.php', ['id' => $id]))->out(false),
    'deleteurl'   => (new moodle_url('/local/byblos/delete.php'))->out(false),
    'publishurl'  => (new moodle_url('/local/byblos/publish.php'))->out(false),
    'dashurl'     => (new moodle_url('/local/byblos/view.php'))->out(false),
    'sesskey'     => sesskey(),
    'feedback_enabled'       => ($feedbackmode !== 'off'),
    'feedback_mode_off'      => ($feedbackmode === 'off'),
    'feedback_mode_teachers' => ($feedbackmode === 'teachers'),
    'feedback_mode_cohort'   => ($feedbackmode === 'cohort'),
    'can_set_feedback_mode'  => ($isowner && !$preview),
    'can_leave_feedback'     => $canleavefeedback,
    'feedback_visible'       => $feedbackvisible,
    'feedback'               => $feedbackrows,
    'has_feedback'           => !empty($feedbackrows),
    'show_feedback_panel'    => (($isowner && !$preview) || $feedbackvisible || $canleavefeedback),
];

if ($canannounce) {
    $PAGE->requires->js_call_amd('local_byblos/announce_link', 'init', [[
        'pageid'  => (int) $pagerecord->id,
        'wwwroot' => $CFG->wwwroot,
    ]]);
}

if ((($isowner && !$preview) || $canleavefeedback)) {
    $PAGE->requires->js_call_amd('local_byblos/feedback', 'init', [[
        'pageid'   => (int) $pagerecord->id,
        'isowner'  => ($isowner && !$preview),
        'canleave' => $canleavefeedback,
    ]]);
}
$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/page_view', $data);
echo $OUTPUT->footer();
