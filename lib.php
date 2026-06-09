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
 * Library of core hooks for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the global navigation tree to add "My Portfolio" to the user menu.
 *
 * @param global_navigation $nav The global navigation instance.
 * @return void
 */
function local_byblos_extend_navigation(global_navigation $nav): void {
    global $USER;

    if (!get_config('local_byblos', 'enabled')) {
        return;
    }

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();
    if (!has_capability('local/byblos:use', $context)) {
        return;
    }

    $node = $nav->add(
        get_string('nav_myportfolio', 'local_byblos'),
        new moodle_url('/local/byblos/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_byblos_portfolio',
        new pix_icon('i/portfolio', get_string('nav_myportfolio', 'local_byblos'))
    );
    $node->showinflatnavigation = true;
}

/**
 * Extends course navigation to add "Course Portfolios" link.
 *
 * @param navigation_node $nav   The course navigation node.
 * @param stdClass        $course The course object.
 * @param context_course  $context The course context.
 * @return void
 */
function local_byblos_extend_navigation_course(navigation_node $nav, stdClass $course, context_course $context): void {
    global $USER;

    if (!get_config('local_byblos', 'enabled')) {
        return;
    }

    $systemcontext = context_system::instance();
    if (!has_capability('local/byblos:use', $systemcontext)) {
        return;
    }

    // Only surface the link when there is something to show, otherwise it is just
    // clutter in every course. This mirrors the visibility rules in
    // courseportfolios.php: a reviewer (viewshared) needs any page linked to the
    // course; a learner needs a page they own or one shared with them.
    $pages = \local_byblos\page::get_pages_for_course((int) $course->id);
    if (empty($pages)) {
        return;
    }
    if (!has_capability('local/byblos:viewshared', $systemcontext)) {
        $hasvisible = false;
        foreach ($pages as $p) {
            if (
                (int) $p->userid === (int) $USER->id
                    || \local_byblos\share::can_view_page((int) $USER->id, (int) $p->id)
            ) {
                $hasvisible = true;
                break;
            }
        }
        if (!$hasvisible) {
            return;
        }
    }

    $nav->add(
        get_string('nav_course_portfolios', 'local_byblos'),
        new moodle_url('/local/byblos/courseportfolios.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_byblos_course_portfolios',
        new pix_icon('i/portfolio', get_string('nav_course_portfolios', 'local_byblos'))
    );
}

/**
 * Serves files for the local_byblos plugin.
 *
 * Handles file areas: 'images', 'exports', 'artefact'.
 *
 * For the `images` filearea, files are stored under a user context with
 * itemid = pageid. Access is granted if:
 *  - The requesting user is the file owner, OR
 *  - The page status is 'published', OR
 *  - The requesting user has an active share record, OR
 *  - The requesting user has `local/byblos:manageall`.
 *
 * @param stdClass        $course        The course object (or 0 for system context).
 * @param stdClass|null   $cm            The course module object (unused).
 * @param context         $context       The context.
 * @param string          $filearea      The file area name.
 * @param array           $args          The remaining path components.
 * @param bool            $forcedownload Whether to force download.
 * @param array           $options       Additional options.
 * @return bool False if the file is not found or access denied.
 */
function local_byblos_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = [],
): bool {
    global $USER, $DB;

    if (!get_config('local_byblos', 'enabled')) {
        return false;
    }

    $allowedareas = ['images', 'exports', 'artefact', 'reflection'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    // Require login for non-public file areas.
    require_login();

    if (!has_capability('local/byblos:use', context_system::instance())) {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    // For the images filearea, enforce page-level access control.
    if ($filearea === 'images' && $context->contextlevel === CONTEXT_USER) {
        $pageid = $itemid;
        $page = \local_byblos\page::get($pageid);
        if (!$page) {
            return false;
        }

        $fileownerid = $context->instanceid;
        if ((int) $USER->id !== (int) $fileownerid) {
            $ismanager = has_capability('local/byblos:manageall', context_system::instance());
            $ispublished = (isset($page->status) && $page->status === 'published');

            if (!$ismanager && !$ispublished) {
                $hasshare = $DB->record_exists('local_byblos_share', [
                    'pageid' => $pageid,
                    'targetuserid' => $USER->id,
                ]);
                if (!$hasshare) {
                    return false;
                }
            }
        }
    }

    // For the reflection filearea, resolve the page via the owning section and
    // enforce the same gate as page viewing (owner / published / active share).
    // (itemid is the section id; files live in the page owner's user context.)
    if ($filearea === 'reflection' && $context->contextlevel === CONTEXT_USER) {
        $section = \local_byblos\section::get($itemid);
        $page = $section ? \local_byblos\page::get((int) $section->pageid) : null;
        if (!$page) {
            return false;
        }
        if ((int) $USER->id !== (int) $page->userid) {
            $ismanager = has_capability('local/byblos:manageall', context_system::instance());
            $ispublished = (isset($page->status) && $page->status === 'published');
            $canview = \local_byblos\share::can_view_page((int) $USER->id, (int) $page->id);
            if (!$ismanager && !$ispublished && !$canview) {
                return false;
            }
        }
    }

    // For the artefact filearea, files live in the owner's user context with
    // itemid = artefact id. Served to the owner, a manager, or a teacher who can
    // view shared work (artefacts are evidence teachers review).
    if ($filearea === 'artefact' && $context->contextlevel === CONTEXT_USER) {
        $artefactrec = \local_byblos\artefact::get($itemid);
        if (!$artefactrec || (int) $context->instanceid !== (int) $artefactrec->userid) {
            return false;
        }
        if ((int) $USER->id !== (int) $artefactrec->userid) {
            $ismanager = has_capability('local/byblos:manageall', context_system::instance());
            $isteacher = has_capability('local/byblos:viewshared', context_system::instance());
            if (!$ismanager && !$isteacher) {
                return false;
            }
        }
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'local_byblos',
        $filearea,
        $itemid,
        $filepath,
        $filename
    );

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * Add byblos-related nodes to the settings navigation.
 *
 * Used by Moodle core (`settings_navigation::load_local_plugin_settings()`) to let
 * local plugins decorate the settings tree. We add a "Manage peer reviewers"
 * link to the module-settings node of any assignment course module that has
 * the byblos submission plugin enabled with peer review turned on. Moodle's
 * secondary navigation then surfaces this as a tab on the assignment page.
 *
 * @param settings_navigation $settingsnav
 * @param \context|null       $context
 * @return void
 */
function local_byblos_extend_settings_navigation(settings_navigation $settingsnav, ?\context $context): void {
    global $PAGE, $DB, $USER;

    if (!$context || $context->contextlevel !== CONTEXT_MODULE) {
        return;
    }
    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'assign') {
        return;
    }

    $assignid = (int) $cm->instance;

    // Byblos enabled on this assignment?
    $enabled = $DB->get_record('assign_plugin_config', [
        'assignment' => $assignid,
        'plugin'     => 'byblos',
        'subtype'    => 'assignsubmission',
        'name'       => 'enabled',
    ]);
    if (!$enabled || $enabled->value !== '1') {
        return;
    }
    $peerenabled = $DB->get_record('assign_plugin_config', [
        'assignment' => $assignid,
        'plugin'     => 'byblos',
        'subtype'    => 'assignsubmission',
        'name'       => 'peerenabled',
    ]);
    if (!$peerenabled || $peerenabled->value !== '1') {
        return;
    }

    $modsettings = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modsettings) {
        return;
    }
    // Student-reviewer branch — show "My peer reviews" if the current user
    // has any peer_assignment rows on this assignment. Count includes both
    // pending and complete so reviewers can revisit comments they've left.
    $reviewerrows = $DB->get_records('local_byblos_peer_assignment', [
        'assignmentid' => $assignid,
        'reviewerid'   => $USER->id,
    ]);
    if (!empty($reviewerrows)) {
        $pending = 0;
        foreach ($reviewerrows as $r) {
            if ($r->status === 'pending') {
                $pending++;
            }
        }
        $label = get_string('myreviews_nav', 'local_byblos');
        if ($pending > 0) {
            $label .= ' (' . $pending . ')';
        }
        $myurl = new moodle_url(
            '/local/byblos/view.php',
            ['tab' => 'reviews', 'assignmentid' => $assignid]
        );
        $mynode = $modsettings->add(
            $label,
            $myurl,
            navigation_node::TYPE_SETTING,
            null,
            'byblos_my_peerreviews',
            new pix_icon('i/feedback', '')
        );
        $mynode->showinflatnavigation = true;
    }
    // Teacher branch — "Manage peer reviewers" for graders.
    if (!has_capability('mod/assign:grade', $context)) {
        return;
    }

    $url = new moodle_url('/local/byblos/peerassign.php', ['assignmentid' => $assignid]);
    $node = navigation_node::create(
        get_string('manage_peer_reviewers', 'assignsubmission_byblos'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'byblos_peerreview',
        new pix_icon('i/users', '')
    );
    $node->showinflatnavigation = true;

    // Place it just before "Advanced grading" ('advgrading') so it sits with the
    // grading tools rather than at the bottom of the actions/"More" menu. Themes
    // that render the settings tree directly (e.g. the cog/More menu) honour this
    // order; Boost's secondary navigation orders core nodes by its own weight map,
    // so there the node appears among the trailing third-party items.
    $beforekey = in_array('advgrading', $modsettings->get_children_key_list(), true)
        ? 'advgrading'
        : null;
    $modsettings->add_node($node, $beforekey);
}

/**
 * Adds a portfolio link to the user profile page.
 *
 * @param \core_user\output\myprofile\tree $tree         The profile tree.
 * @param stdClass                          $user         The user whose profile is being viewed.
 * @param bool                              $iscurrentuser Whether viewing own profile.
 * @param stdClass|null                     $course       The course context (or null).
 * @return void
 */
function local_byblos_myprofile_navigation(
    \core_user\output\myprofile\tree $tree,
    stdClass $user,
    bool $iscurrentuser,
    ?stdClass $course,
): void {
    if (!get_config('local_byblos', 'enabled')) {
        return;
    }

    $context = context_system::instance();

    // Show portfolio link for the current user, or for managers.
    if ($iscurrentuser && has_capability('local/byblos:use', $context)) {
        $url = new moodle_url('/local/byblos/index.php');
        $node = new \core_user\output\myprofile\node(
            'miscellaneous',
            'local_byblos',
            get_string('nav_profile_portfolio', 'local_byblos'),
            null,
            $url,
        );
        $tree->add_node($node);
    } else if (!$iscurrentuser && has_capability('local/byblos:viewshared', $context)) {
        // Teachers/managers can view this user's shared portfolio.
        $url = new moodle_url('/local/byblos/view.php', ['userid' => $user->id]);
        $node = new \core_user\output\myprofile\node(
            'miscellaneous',
            'local_byblos',
            get_string('nav_profile_portfolio', 'local_byblos'),
            null,
            $url,
        );
        $tree->add_node($node);
    }
}

/**
 * Build the seeded HTML scaffold for a reflection framework — prompt labels as
 * H4 headings, each followed by an empty paragraph for the student to fill in.
 *
 * @param string $framework One of freewrite|wsnw|gibbs|deal|kolb.
 * @return string Seeded HTML (empty for freewrite).
 */
function local_byblos_reflection_scaffold(string $framework): string {
    $prompts = [
        'wsnw'  => ['reflection_prompt_wsnw_1', 'reflection_prompt_wsnw_2', 'reflection_prompt_wsnw_3'],
        'gibbs' => [
            'reflection_prompt_gibbs_1', 'reflection_prompt_gibbs_2', 'reflection_prompt_gibbs_3',
            'reflection_prompt_gibbs_4', 'reflection_prompt_gibbs_5', 'reflection_prompt_gibbs_6',
        ],
        'deal'  => ['reflection_prompt_deal_1', 'reflection_prompt_deal_2', 'reflection_prompt_deal_3'],
        'kolb'  => [
            'reflection_prompt_kolb_1', 'reflection_prompt_kolb_2',
            'reflection_prompt_kolb_3', 'reflection_prompt_kolb_4',
        ],
    ];
    if (!isset($prompts[$framework])) {
        return '';
    }

    $html = '';
    foreach ($prompts[$framework] as $key) {
        $html .= '<h4>' . get_string($key, 'local_byblos') . '</h4><p></p>';
    }
    return $html;
}

/**
 * Fragment callback: render the reflection-section editor form (a moodleform
 * with an `editor` element, which attaches TinyMCE + the satsrecorder plugin
 * and a draft file area). Loaded into a modal by amd/src/editor.js.
 *
 * @param array $args Fragment args: { context, sectionid }.
 * @return string Rendered form HTML (with inline editor bootstrap JS).
 */
function local_byblos_output_fragment_reflection_editor(array $args): string {
    global $USER, $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $sectionid = (int) ($args['sectionid'] ?? 0);
    $section = \local_byblos\section::get($sectionid);
    if (!$section) {
        return '';
    }
    $page = \local_byblos\page::get((int) $section->pageid);
    if (!$page) {
        return '';
    }
    // Reflection is self-authored: only the page owner (or a manager) may edit.
    if (
        (int) $page->userid !== (int) $USER->id
            && !has_capability('local/byblos:manageall', context_system::instance())
    ) {
        return '';
    }

    $cfg = json_decode($section->configdata ?? '{}', true) ?: [];
    $framework = $cfg['framework'] ?? 'gibbs';
    if (!in_array($framework, ['freewrite', 'wsnw', 'gibbs', 'deal', 'kolb'], true)) {
        $framework = 'gibbs';
    }
    $bodyhtml = (string) ($cfg['bodyhtml'] ?? '');
    if (
        trim(strip_tags($bodyhtml)) === '' && stripos($bodyhtml, '<audio') === false
            && stripos($bodyhtml, '<video') === false
    ) {
        $bodyhtml = local_byblos_reflection_scaffold($framework);
    }

    $usercontext = context_user::instance((int) $page->userid);
    $editoroptions = [
        'maxfiles'              => EDITOR_UNLIMITED_FILES,
        'maxbytes'              => \local_byblos\file_manager::MAX_REFLECTION_BYTES,
        'context'               => context_system::instance(),
        'subdirs'               => 0,
        'enable_filemanagement' => true,
    ];
    $draftid = 0;
    $bodyhtml = file_prepare_draft_area(
        $draftid,
        $usercontext->id,
        'local_byblos',
        \local_byblos\file_manager::FILEAREA_REFLECTION,
        $sectionid,
        $editoroptions,
        $bodyhtml
    );

    $mform = new \local_byblos\form\reflection_form(null, [
        'sectionid'     => $sectionid,
        'editoroptions' => $editoroptions,
    ]);
    $mform->set_data([
        'sectionid'       => $sectionid,
        'heading'         => $cfg['heading'] ?? '',
        'framework'       => $framework,
        'intro'           => $cfg['intro'] ?? '',
        'bodyhtml_editor' => ['text' => $bodyhtml, 'format' => FORMAT_HTML, 'itemid' => $draftid],
    ]);

    return $mform->render();
}

/**
 * Tag-area callback: return this user's own artefacts carrying a given tag.
 *
 * Powers Moodle's core tag pages (/tag/index.php) for the
 * local_byblos/local_byblos_artefact area. Artefacts are private to their
 * owner, so the query is always scoped to the current user: a tag page can
 * never surface another learner's artefacts, regardless of the context passed.
 *
 * @param \core_tag_tag $tag           The tag being viewed.
 * @param bool          $exclusivemode Whether only this area's items are shown.
 * @param int           $fromctx       Origin context (unused; artefacts are not course-scoped).
 * @param int           $ctx           Context filter (unused; owner scoping supersedes it).
 * @param bool          $rec           Whether to recurse into subcontexts (unused).
 * @param int           $page          Zero-based page number.
 * @return \core_tag\output\tagindex|null Rendered tag index, or null when empty.
 */
function local_byblos_get_tagged_artefacts(
    $tag,
    $exclusivemode = false,
    $fromctx = 0,
    $ctx = 0,
    $rec = true,
    $page = 0
) {
    global $DB, $USER, $OUTPUT;

    $perpage = $exclusivemode ? 20 : 5;

    // Owner-scoped: a learner only ever sees their own artefacts on a tag page.
    $sql = "SELECT a.id, a.title, a.artefacttype
              FROM {local_byblos_artefact} a
              JOIN {tag_instance} tt ON tt.itemid = a.id
             WHERE tt.itemtype = :itemtype
               AND tt.component = :component
               AND tt.tagid = :tagid
               AND a.userid = :userid
          ORDER BY a.timemodified DESC, a.id DESC";
    $params = [
        'itemtype'  => 'local_byblos_artefact',
        'component' => 'local_byblos',
        'tagid'     => $tag->id,
        'userid'    => $USER->id,
    ];

    $totalpages = $page + 1;
    $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage + 1);
    if (count($records) > $perpage) {
        $totalpages = $page + 2; // Signal that a next page exists without an exact count.
        array_pop($records);
    }

    if (empty($records)) {
        return null;
    }

    $systemcontext = context_system::instance();
    $tagfeed = new \core_tag\output\tagfeed();
    foreach ($records as $record) {
        $url = new moodle_url('/local/byblos/artefact.php', ['id' => $record->id]);
        $name = format_string($record->title, true, ['context' => $systemcontext]);
        $typelabel = get_string('artefacttype_' . $record->artefacttype, 'local_byblos');
        $handler = \local_byblos\artefact_type::get($record->artefacttype);
        $iconname = $handler ? $handler->get_icon() : 'f/unknown';
        $icon = html_writer::link($url, $OUTPUT->pix_icon($iconname, $typelabel));
        $tagfeed->add($icon, html_writer::link($url, $name), $typelabel);
    }

    $content = $OUTPUT->render_from_template('core_tag/tagfeed', $tagfeed->export_for_template($OUTPUT));

    return new \core_tag\output\tagindex(
        $tag,
        'local_byblos',
        'local_byblos_artefact',
        $content,
        $exclusivemode,
        $fromctx,
        $ctx,
        $rec,
        $page,
        $totalpages
    );
}
