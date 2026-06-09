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
 * View and manage a single collection.
 *
 * Group-aware: the creator can rename/delete; any group member can contribute
 * their own pages and reorder; page owners can remove their own pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\collection;
use local_byblos\page;
use local_byblos\share;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$id = required_param('id', PARAM_INT);

$PAGE->set_url(new moodle_url('/local/byblos/collection.php', ['id' => $id]));
$PAGE->set_context($context);

$col = collection::get($id);
if (!$col) {
    throw new moodle_exception('collectionnotfound', 'local_byblos');
}

$userid       = (int) $USER->id;
$iscreator    = collection::can_manage_metadata($userid, $col);
$cancontribute = collection::can_contribute($userid, $col);
$canview      = share::can_view_collection($userid, $id);

if (!$canview) {
    require_capability('local/byblos:viewshared', $context);
    if (!share::can_view_collection($userid, $id)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('viewpage', 'local_byblos'));
    }
}

// Handle POST actions (add/remove/reorder pages).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    require_capability('local/byblos:createpage', $context);

    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'addpage' && $cancontribute) {
        $pageid = required_param('pageid', PARAM_INT);
        $p = page::get($pageid);
        // Contributors can only add their OWN pages.
        if ($p && (int) $p->userid === $userid) {
            $nextorder = collection::count_pages($id);
            collection::add_page($id, $pageid, $nextorder);
        }
    } else if ($action === 'removepage') {
        $pageid = required_param('pageid', PARAM_INT);
        $p = page::get($pageid);
        // Creator can remove any page; other contributors only their own.
        if ($p && ($iscreator || (int) $p->userid === $userid)) {
            collection::remove_page($id, $pageid);
        }
    } else if (($action === 'movepage' || $action === 'sethomepage') && $cancontribute) {
        $pageid    = required_param('pageid', PARAM_INT);
        $direction = ($action === 'sethomepage')
            ? 'top'
            : required_param('direction', PARAM_ALPHA);
        collection::move_page($id, $pageid, $direction);
    }

    redirect(new moodle_url('/local/byblos/collection.php', ['id' => $id]));
}

$PAGE->set_title(format_string($col->title));
$PAGE->set_heading(format_string($col->title));

// Group metadata for the template.
$groupinfo = ['is_group' => false, 'group_name' => ''];
if (collection::is_group_collection($col)) {
    $group = $DB->get_record('groups', ['id' => (int) $col->groupid], 'id, name');
    $groupinfo = [
        'is_group'   => true,
        'group_name' => $group ? format_string($group->name) : '',
    ];
}

// Load collection pages.
$colpages = collection::get_pages($id);
$lastindex = count($colpages) - 1;
$ownercache = [];
$pagedata = [];
foreach ($colpages as $i => $p) {
    $pageownerid = (int) $p->userid;
    if (!isset($ownercache[$pageownerid])) {
        $u = $DB->get_record('user', ['id' => $pageownerid], 'id, firstname, lastname');
        $ownercache[$pageownerid] = $u ? fullname($u) : '';
    }
    $ismine = ($pageownerid === $userid);
    $pagedata[] = [
        'id'         => $p->id,
        'title'      => format_string($p->title, true, ['escape' => false]),
        'status'     => $p->status,
        'viewurl'    => (new moodle_url('/local/byblos/page.php', ['id' => $p->id]))->out(false),
        'is_home'    => ($i === 0),
        'can_up'     => ($i > 0) && $cancontribute,
        'can_down'   => ($i < $lastindex) && $cancontribute,
        'can_remove' => $iscreator || $ismine,
        'owner_name' => $ownercache[$pageownerid],
        'is_mine'    => $ismine,
    ];
}

// The Preview-collection button lands on the first page (sortorder) in viewer/preview layout.
$previewurl = '';
if (!empty($colpages)) {
    $previewurl = (new moodle_url(
        '/local/byblos/page.php',
        ['id' => (int) $colpages[0]->id, 'preview' => 1]
    ))->out(false);
}

// Available pages the current contributor can add — only their own, not already in.
$availablepages = [];
if ($cancontribute) {
    $userpages = page::list_by_user($userid);
    $incolids = array_map('intval', array_column($colpages, 'id'));
    foreach ($userpages as $up) {
        if (!in_array((int) $up->id, $incolids, true)) {
            $availablepages[] = [
                'id'    => $up->id,
                'title' => format_string($up->title),
            ];
        }
    }
}

$data = [
    'collection'     => [
        'id'          => $col->id,
        'title'       => format_string($col->title),
        'description' => format_text($col->description, FORMAT_HTML),
        'timecreated' => userdate($col->timecreated),
    ],
    'group'          => $groupinfo,
    'pages'          => $pagedata,
    'has_pages'      => !empty($pagedata),
    'iscreator'      => $iscreator,
    'cancontribute'  => $cancontribute,
    // Kept for template back-compat: any owner-gated chrome (share, delete) uses this.
    'isowner'        => $iscreator,
    'availablepages' => $availablepages,
    'has_available'  => !empty($availablepages) && $cancontribute,
    'actionurl'      => (new moodle_url('/local/byblos/collection.php', ['id' => $id]))->out(false),
    'deleteurl'      => (new moodle_url('/local/byblos/delete.php'))->out(false),
    'shareurl'       => (new moodle_url('/local/byblos/share.php', ['id' => $id, 'type' => 'collection']))->out(false),
    'previewurl'     => $previewurl,
    'has_preview'    => $previewurl !== '',
    'dashurl'        => (new moodle_url('/local/byblos/view.php', ['tab' => 'collections']))->out(false),
    'sesskey'        => sesskey(),
];

$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/collection_view', $data);
echo $OUTPUT->footer();
