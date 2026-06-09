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
 * Portfolio dashboard — main entry point for the ePortfolio.
 *
 * Tabbed interface showing My Pages, My Collections, and My Artefacts.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\page;
use local_byblos\collection;
use local_byblos\artefact;
use local_byblos\goal;
use local_byblos\share;
use local_byblos\peer as byblos_peer;
use local_byblos\submission as byblos_submission;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$PAGE->set_url(new moodle_url('/local/byblos/view.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard', 'local_byblos'));
$PAGE->set_heading(get_string('dashboard', 'local_byblos'));
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-dashboard');
$PAGE->set_pagelayout('standard');

// Default landing tab follows the documented best-practice workflow: set goals,
// then curate artefacts, then build pages. So the dashboard opens on Goals.
$tab = optional_param('tab', 'goals', PARAM_ALPHA);
$filterassignmentid = optional_param('assignmentid', 0, PARAM_INT);

// Handle new collection creation (POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'collections') {
    require_sesskey();
    require_capability('local/byblos:createpage', $context);

    $coltitle = required_param('coltitle', PARAM_TEXT);
    $coldesc  = optional_param('coldesc', '', PARAM_TEXT);
    $colgroup = optional_param('colgroupid', 0, PARAM_INT);

    if ($colgroup > 0 && !groups_is_member($colgroup, (int) $USER->id)) {
        throw new moodle_exception('error:notgroupmember', 'local_byblos');
    }

    collection::create((int) $USER->id, $coltitle, $coldesc, $colgroup);

    redirect(new moodle_url('/local/byblos/view.php', ['tab' => 'collections']));
}

// Auto-import badges and completions if setting is enabled.
$autoimport = get_config('local_byblos', 'autoimport');
$importmsgs = [];
if ($autoimport) {
    $badgecount = artefact::auto_import_badges($USER->id);
    if ($badgecount > 0) {
        $importmsgs[] = get_string('importedbadges', 'local_byblos', $badgecount);
    }
    $compcount = artefact::auto_import_completions($USER->id);
    if ($compcount > 0) {
        $importmsgs[] = get_string('importedcompletions', 'local_byblos', $compcount);
    }
}

// Load data for each tab.
$pages = page::list_by_user($USER->id);
$pagedata = [];
foreach ($pages as $p) {
    $pagedata[] = [
        'id'          => $p->id,
        'title'       => format_string($p->title, true, ['escape' => false]),
        'description' => format_text($p->description, FORMAT_HTML),
        'status'      => $p->status,
        'statuslabel' => get_string('status_' . $p->status, 'local_byblos'),
        'is_draft'    => ($p->status === 'draft'),
        'viewurl'     => (new moodle_url('/local/byblos/page.php', ['id' => $p->id]))->out(false),
        'previewurl'  => (new moodle_url('/local/byblos/page.php', ['id' => $p->id, 'preview' => 1]))->out(false),
        'editurl'     => (new moodle_url('/local/byblos/editpage.php', ['id' => $p->id]))->out(false),
        'deleteurl'   => (new moodle_url('/local/byblos/delete.php'))->out(false),
        'timecreated' => userdate($p->timecreated),
    ];
}

// Personal + group collections the user can contribute to.
$collections = collection::list_contributable_for_user((int) $USER->id);
$coldata = [];
$groupnames = [];
$groupidsforlookup = array_unique(array_filter(array_map(fn($c) => (int) ($c->groupid ?? 0), $collections)));
if ($groupidsforlookup) {
    [$insql, $params] = $DB->get_in_or_equal($groupidsforlookup, SQL_PARAMS_NAMED, 'gid');
    foreach ($DB->get_records_select('groups', "id $insql", $params, '', 'id, name') as $g) {
        $groupnames[(int) $g->id] = format_string($g->name);
    }
}
foreach ($collections as $c) {
    $pcount = collection::count_pages($c->id);
    $coldata[] = [
        'id'          => $c->id,
        'title'       => format_string($c->title, true, ['escape' => false]),
        'description' => format_text($c->description, FORMAT_HTML),
        'pagecount'   => get_string('pagecount', 'local_byblos', $pcount),
        'viewurl'     => (new moodle_url('/local/byblos/collection.php', ['id' => $c->id]))->out(false),
        'deleteurl'   => (new moodle_url('/local/byblos/delete.php'))->out(false),
        'timecreated' => userdate($c->timecreated),
        'is_group'    => !empty($c->groupid),
        'is_creator'  => !empty($c->is_creator),
        'group_name'  => !empty($c->groupid) ? ($groupnames[(int) $c->groupid] ?? '') : '',
    ];
}

// The "My Artefacts" tab is the full library: enriched cards plus the type and
// tag filter sets, all driven client-side by the local_byblos/artefacts module.
$librarydata = \local_byblos\artefact_library::data((int) $USER->id);

// Learning goals for the current user (self-regulated-learning loop).
$goals = goal::list_by_user((int) $USER->id);
$goaldata = [];
foreach ($goals as $g) {
    $goaldata[] = [
        'id'          => (int) $g->id,
        'title'       => format_string($g->title, true, ['escape' => false]),
        'description' => format_text($g->description ?? '', FORMAT_HTML),
        'status'      => $g->status,
        'statuslabel' => get_string('goalstatus_' . $g->status, 'local_byblos'),
        'is_active'   => ($g->status === 'active'),
        'is_achieved' => ($g->status === 'achieved'),
        'is_archived' => ($g->status === 'archived'),
        'progress'    => (int) $g->progress,
        'hastarget'   => !empty($g->targetdate),
        'targetdate'  => !empty($g->targetdate) ? userdate((int) $g->targetdate, get_string('strftimedate', 'langconfig')) : '',
        'links'       => goal::get_links((int) $g->id),
        'haslinks'    => !empty(goal::get_links((int) $g->id)),
    ];
}
$cancreategoals = has_capability('local/byblos:managegoals', $context);
$goaltemplates = $cancreategoals ? goal::quickstart_templates() : [];

// Pending peer reviews assigned to the current user. Optionally filtered by
// assignmentid so a link from a specific mod_assign page lands on a focused list.
$pendingrows = byblos_peer::all_pending_for_reviewer((int) $USER->id);
if ($filterassignmentid > 0) {
    $pendingrows = array_filter(
        $pendingrows,
        static fn(\stdClass $r): bool => (int) $r->assignmentid === $filterassignmentid
    );
}
$reviewdata = [];
foreach ($pendingrows as $pa) {
    $assign = $DB->get_record('assign', ['id' => $pa->assignmentid], 'id, name');
    $reviewee = $DB->get_record('user', ['id' => $pa->revieweeuserid]);
    $submissionid = (int) ($pa->submissionid ?? 0);
    if (!$submissionid) {
        // Attempt to resolve a submission if it exists now.
        $existing = byblos_submission::get_by_assign_submission(0);
        $sub = $DB->get_record('local_byblos_submission', [
            'assignmentid' => $pa->assignmentid,
            'userid'       => $pa->revieweeuserid,
        ]);
        if ($sub) {
            $submissionid = (int) $sub->id;
        }
    }
    $reviewdata[] = [
        'rowid'         => (int) $pa->id,
        'assignmentname' => $assign ? format_string($assign->name) : '#' . $pa->assignmentid,
        'revieweename'  => $reviewee ? fullname($reviewee) : '#' . $pa->revieweeuserid,
        'timeassigned'  => userdate((int) $pa->timeassigned),
        'hasurl'        => $submissionid > 0,
        'reviewurl'     => $submissionid > 0
            ? (new moodle_url('/local/byblos/review.php', ['submissionid' => $submissionid]))->out(false)
            : '',
    ];
}

// Groups the user is in — for the "new collection" picker on the dashboard.
$usergroupids = [];
foreach (groups_get_user_groups(0, (int) $USER->id) as $coursegroups) {
    foreach ($coursegroups as $gid) {
        $usergroupids[(int) $gid] = true;
    }
}
$usergroupsdata = [];
if ($usergroupids) {
    [$ugsql, $ugparams] = $DB->get_in_or_equal(array_keys($usergroupids), SQL_PARAMS_NAMED, 'gid');
    $ugroups = $DB->get_records_select('groups', "id $ugsql", $ugparams, 'name ASC', 'id, courseid, name');
    $ugcourseids = array_unique(array_map(fn($g) => (int) $g->courseid, $ugroups));
    $ugcourses = [];
    if ($ugcourseids) {
        [$ucinsql, $ucparams] = $DB->get_in_or_equal($ugcourseids, SQL_PARAMS_NAMED, 'cid');
        foreach ($DB->get_records_select('course', "id $ucinsql", $ucparams, '', 'id, shortname') as $c) {
            $ugcourses[(int) $c->id] = format_string($c->shortname);
        }
    }
    foreach ($ugroups as $g) {
        $usergroupsdata[] = [
            'id'         => (int) $g->id,
            'name'       => format_string($g->name),
            'coursecode' => $ugcourses[(int) $g->courseid] ?? '',
        ];
    }
}

// Items other people have shared with this user (the "Shared with me" tab).
$shared = share::list_shared_with_user((int) $USER->id);
$sharedpagecards = [];
foreach ($shared['pages'] as $sp) {
    $owner = $DB->get_record('user', ['id' => $sp->userid], 'id, firstname, lastname');
    $sharedpagecards[] = [
        'title'       => format_string($sp->title),
        'description' => shorten_text(strip_tags($sp->description ?? ''), 120),
        'owner'       => $owner ? fullname($owner) : '',
        'status'      => $sp->status,
        'viewurl'     => (new moodle_url('/local/byblos/page.php', ['id' => $sp->id]))->out(false),
        'timecreated' => userdate($sp->timecreated),
    ];
}
$sharedcollectioncards = [];
foreach ($shared['collections'] as $sc) {
    $owner = $DB->get_record('user', ['id' => $sc->userid], 'id, firstname, lastname');
    $sharedcollectioncards[] = [
        'title'       => format_string($sc->title),
        'description' => shorten_text(strip_tags($sc->description ?? ''), 120),
        'owner'       => $owner ? fullname($owner) : '',
        'viewurl'     => (new moodle_url('/local/byblos/collection.php', ['id' => $sc->id]))->out(false),
        'timecreated' => userdate($sc->timecreated),
    ];
}

$data = [
    'tab_pages'       => ($tab === 'pages'),
    'tab_collections' => ($tab === 'collections'),
    'tab_artefacts'   => ($tab === 'artefacts'),
    'tab_goals'       => ($tab === 'goals'),
    'tab_reviews'     => ($tab === 'reviews'),
    'tab_shared'      => ($tab === 'shared'),
    'sharedpages'           => $sharedpagecards,
    'has_sharedpages'       => !empty($sharedpagecards),
    'sharedcollections'     => $sharedcollectioncards,
    'has_sharedcollections' => !empty($sharedcollectioncards),
    'has_shared'            => !empty($sharedpagecards) || !empty($sharedcollectioncards),
    'reviews'         => $reviewdata,
    'has_reviews'     => !empty($reviewdata),
    'goals'           => $goaldata,
    'has_goals'       => !empty($goaldata),
    'cancreategoals'  => $cancreategoals,
    'goaltemplates'   => $goaltemplates,
    'pages'           => $pagedata,
    'has_pages'       => !empty($pagedata),
    'collections'     => $coldata,
    'has_collections' => !empty($coldata),
    'usergroups'      => $usergroupsdata,
    'has_usergroups'  => !empty($usergroupsdata),
    'artefacts'       => $librarydata['artefacts'],
    'has_artefacts'   => $librarydata['has_artefacts'],
    'typefilters'     => $librarydata['typefilters'],
    'tagfilters'      => $librarydata['tagfilters'],
    'hastags'         => $librarydata['hastags'],
    'newpageurl'      => (new moodle_url('/local/byblos/newpage.php'))->out(false),
    'newartefacturl'  => (new moodle_url('/local/byblos/artefact.php', ['action' => 'edit']))->out(false),
    'actionurl'       => (new moodle_url('/local/byblos/view.php', ['tab' => 'collections']))->out(false),
    'docsurl'         => (new moodle_url('/local/byblos/docs.php'))->out(false),
    'sesskey'         => sesskey(),
    'importmsgs'      => $importmsgs,
    'has_importmsgs'  => !empty($importmsgs),
];

$PAGE->requires->js_call_amd('local_byblos/goals', 'init', [[
    'userid'    => (int) $USER->id,
    'sesskey'   => sesskey(),
    'templates' => $goaltemplates,
]]);
$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');
$PAGE->requires->js_call_amd('local_byblos/artefacts', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/dashboard', $data);
echo $OUTPUT->footer();
