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
 * Share management page for a portfolio page or collection.
 *
 * URL: /local/byblos/share.php?id=X&type=page|collection
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\share;
use local_byblos\page;
use local_byblos\collection;
use local_byblos\event\page_shared;

require_login();

$id   = required_param('id', PARAM_INT);
$type = required_param('type', PARAM_ALPHA); // Page or collection.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$context = context_system::instance();
require_capability('local/byblos:share', $context);

// Validate the item exists and belongs to the current user.
if ($type === 'page') {
    $item = page::get($id);
    if (!$item || (int) $item->userid !== (int) $USER->id) {
        throw new moodle_exception('error_page_not_found', 'local_byblos');
    }
    $shares = share::list_for_page($id);
    $itemtitle = $item->title;
} else if ($type === 'collection') {
    $item = collection::get($id);
    if (!$item || (int) $item->userid !== (int) $USER->id) {
        throw new moodle_exception('error_collection_not_found', 'local_byblos');
    }
    $shares = share::list_for_collection($id);
    $itemtitle = $item->title;
} else {
    throw new moodle_exception('error_invalid_share_type', 'local_byblos');
}

$pageurl = new moodle_url('/local/byblos/share.php', ['id' => $id, 'type' => $type]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('share_manage', 'local_byblos') . ': ' . $itemtitle);
$PAGE->set_heading(get_string('share_manage', 'local_byblos'));

// After add/remove, return to the hub if a (local) return URL was supplied.
$aftersave = ($returnurl !== '') ? new moodle_url($returnurl) : $pageurl;

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'add') {
        $sharetype  = required_param('sharetype', PARAM_ALPHA);
        $sharevalue = optional_param('sharevalue', '', PARAM_RAW);

        // Validate share type.
        $validtypes = ['user', 'course', 'group', 'public'];
        if (!in_array($sharetype, $validtypes, true)) {
            throw new moodle_exception('error_invalid_share_type', 'local_byblos');
        }

        // Public sharing requires capability and admin setting.
        if ($sharetype === 'public') {
            if (!get_config('local_byblos', 'allowpublic')) {
                throw new moodle_exception('error_public_sharing_disabled', 'local_byblos');
            }
            require_capability('local/byblos:sharepublic', $context);
            $sharevalue = ''; // Public shares don't need a value.
        } else {
            // Non-public shares must resolve to something in the owner's scope.
            $sharevalue = (string) (int) $sharevalue;
            if ((int) $sharevalue <= 0) {
                throw new moodle_exception('error_invalid_share_value', 'local_byblos');
            }
            $mycourseids = array_keys(enrol_get_my_courses(['id'], 'id ASC'));
            if ($sharetype === 'course') {
                if (!in_array((int) $sharevalue, $mycourseids, true)) {
                    throw new moodle_exception('error_invalid_share_value', 'local_byblos');
                }
            } else if ($sharetype === 'group') {
                $group = $DB->get_record('groups', ['id' => (int) $sharevalue], 'id, courseid');
                if (!$group || !in_array((int) $group->courseid, $mycourseids, true)) {
                    throw new moodle_exception('error_invalid_share_value', 'local_byblos');
                }
            } else if ($sharetype === 'user') {
                $sharedscope = false;
                foreach ($mycourseids as $cid) {
                    $ccontext = context_course::instance((int) $cid, IGNORE_MISSING);
                    if ($ccontext && is_enrolled($ccontext, (int) $sharevalue)) {
                        $sharedscope = true;
                        break;
                    }
                }
                if (!$sharedscope) {
                    throw new moodle_exception('error_invalid_share_value', 'local_byblos');
                }
            }
        }

        $pageid = ($type === 'page') ? $id : 0;
        $collectionid = ($type === 'collection') ? $id : 0;

        $shareid = share::create($pageid, $collectionid, $sharetype, $sharevalue);

        // Fire the page_shared event.
        $eventdata = [
            'context'  => $context,
            'objectid' => $shareid,
            'userid'   => $USER->id,
            'other'    => [
                'sharetype'  => $sharetype,
                'sharevalue' => $sharevalue,
                'pageid'     => $pageid,
                'collectionid' => $collectionid,
            ],
        ];
        $event = page_shared::create($eventdata);
        $event->trigger();

        redirect($aftersave, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'remove') {
        $shareid = required_param('shareid', PARAM_INT);
        // The share must belong to the item we already verified the user owns,
        // otherwise a share id alone could revoke a share on someone else's item.
        $sharerec = $DB->get_record('local_byblos_share', ['id' => $shareid]);
        $belongs = $sharerec && (
            ($type === 'page' && (int) $sharerec->pageid === (int) $id)
            || ($type === 'collection' && (int) $sharerec->collectionid === (int) $id)
        );
        if (!$belongs) {
            throw new moodle_exception('error_invalid_share_value', 'local_byblos');
        }
        share::delete($shareid);
        redirect($aftersave, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Build template data.
$canpublic = has_capability('local/byblos:sharepublic', $context)
             && get_config('local_byblos', 'allowpublic');

$sharedata = [];
$publicurl = '';
foreach ($shares as $s) {
    $display = new stdClass();
    $display->id = $s->id;
    $display->sharetype = $s->sharetype;
    $display->sharevalue = $s->sharevalue;
    $display->timecreated = userdate($s->timecreated);
    $display->is_public = ($s->sharetype === 'public');

    // Resolve display names.
    if ($s->sharetype === 'user') {
        $shareuser = $DB->get_record('user', ['id' => (int) $s->sharevalue], 'id, firstname, lastname, email');
        $display->displayname = $shareuser ? fullname($shareuser) : get_string('no_results', 'local_byblos');
    } else if ($s->sharetype === 'course') {
        $course = $DB->get_record('course', ['id' => (int) $s->sharevalue], 'id, fullname');
        $display->displayname = $course ? $course->fullname : get_string('no_results', 'local_byblos');
    } else if ($s->sharetype === 'group') {
        $group = $DB->get_record('groups', ['id' => (int) $s->sharevalue], 'id, name');
        $display->displayname = $group ? $group->name : get_string('no_results', 'local_byblos');
    } else if ($s->sharetype === 'public') {
        $display->displayname = get_string('share_public', 'local_byblos');
        $publicurl = (new moodle_url('/local/byblos/publicview.php', ['token' => $s->token]))->out(false);
        $display->publicurl = $publicurl;
    }

    $sharedata[] = $display;
}

// Build scoped pickers from the owner's current course enrolments so the UI
// never asks the user to track down an ID.
$mycourseobjs = enrol_get_my_courses(['id', 'fullname', 'shortname'], 'fullname ASC');

$existingcourseids = [];
$existinguserids   = [];
$existinggroupids  = [];
foreach ($sharedata as $s) {
    if ($s->sharetype === 'course') {
        $existingcourseids[(int) $s->sharevalue] = true;
    } else if ($s->sharetype === 'user') {
        $existinguserids[(int) $s->sharevalue] = true;
    } else if ($s->sharetype === 'group') {
        $existinggroupids[(int) $s->sharevalue] = true;
    }
}

$coursepicker = [];
$grouppicker  = [];
foreach ($mycourseobjs as $c) {
    $cid = (int) $c->id;
    if ($cid === SITEID) {
        continue;
    }
    $coursepicker[] = [
        'id'        => $cid,
        'label'     => format_string($c->fullname),
        'disabled'  => !empty($existingcourseids[$cid]),
    ];
    $ccontext = context_course::instance($cid, IGNORE_MISSING);
    if (!$ccontext) {
        continue;
    }
    foreach (groups_get_all_groups($cid) as $g) {
        $gid = (int) $g->id;
        if (isset($grouppicker[$gid])) {
            continue;
        }
        $grouppicker[$gid] = [
            'id'       => $gid,
            'label'    => format_string($g->name) . ' — ' . format_string($c->shortname),
            'disabled' => !empty($existinggroupids[$gid]),
        ];
    }
}
usort($coursepicker, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
$grouppicker = array_values($grouppicker);
usort($grouppicker, fn($a, $b) => strnatcasecmp($a['label'], $b['label']));

$templatedata = [
    'itemtitle'      => $itemtitle,
    'itemid'         => $id,
    'itemtype'       => $type,
    'shares'         => $sharedata,
    'hasshares'      => !empty($sharedata),
    'canpublic'      => $canpublic,
    'publicurl'      => $publicurl,
    'sesskey'        => sesskey(),
    'actionurl'      => $pageurl->out(false),
    'courses'        => $coursepicker,
    'has_courses'    => !empty($coursepicker),
    'groups'         => $grouppicker,
    'has_groups'     => !empty($grouppicker),
];

$PAGE->requires->js_call_amd('local_byblos/share', 'init', [[
    'sharedusers' => array_values(array_map('intval', array_keys($existinguserids))),
    'strings'     => [
        'pickperson'    => get_string('share_user_pickperson', 'local_byblos'),
        'alreadyshared' => get_string('share_already', 'local_byblos'),
        'noneincourse'  => get_string('share_user_noneincourse', 'local_byblos'),
        'loading'       => get_string('share_user_loading', 'local_byblos'),
    ],
]]);
$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/share', $templatedata);
echo $OUTPUT->footer();
