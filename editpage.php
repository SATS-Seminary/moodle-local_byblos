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
 * Section-based page editor for local_byblos ePortfolio.
 *
 * URL: /local/byblos/editpage.php?id=X
 * Loads a portfolio page and its sections, renders the editor template,
 * and boots the AMD module for AJAX-driven inline editing.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\page;
use local_byblos\section;
use local_byblos\section_renderer;
use local_byblos\artefact;

require_login();

$pageid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_capability('local/byblos:createpage', $context);

// Load the page.
$pagerecord = page::get($pageid);
if (!$pagerecord) {
    throw new moodle_exception('error_page_not_found', 'local_byblos');
}

// Verify ownership (or manageall).
if ((int) $pagerecord->userid !== (int) $USER->id && !has_capability('local/byblos:manageall', $context)) {
    throw new moodle_exception('error_no_permission', 'local_byblos');
}

// Page setup.
$PAGE->set_url(new moodle_url('/local/byblos/editpage.php', ['id' => $pageid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('editpagetitle', 'local_byblos', $pagerecord->title));
$PAGE->set_heading(get_string('editpagetitle', 'local_byblos', $pagerecord->title));
$PAGE->add_body_class('byblos-body');
$PAGE->add_body_class('byblos-body-editor');
$PAGE->set_pagelayout('standard');

// Load sections.
$sections = section::list_for_page($pageid);

// Render each section for preview.
$sectionsdata = [];
foreach ($sections as $idx => $sec) {
    $rendered = section_renderer::render($sec, $pagerecord->themekey);
    $sectionsdata[] = [
        'id'          => (int) $sec->id,
        'sectiontype' => $sec->sectiontype,
        'sortorder'   => (int) $sec->sortorder,
        'configdata'  => $sec->configdata ?? '{}',
        'content'     => $sec->content ?? '',
        'rendered'    => $rendered,
    ];
}

// Section type definitions for the picker (each row is a compact tuple of name + description lookups).
// phpcs:disable moodle.Files.LineLength
$sectiontypes = [
    ['type' => 'hero', 'name' => get_string('sectiontype_hero', 'local_byblos'), 'description' => get_string('sectiontype_hero_desc', 'local_byblos'), 'icon' => 'fa-picture-o'],
    ['type' => 'text', 'name' => get_string('sectiontype_text', 'local_byblos'), 'description' => get_string('sectiontype_text_desc', 'local_byblos'), 'icon' => 'fa-file-text'],
    ['type' => 'text_image', 'name' => get_string('sectiontype_text_image', 'local_byblos'), 'description' => get_string('sectiontype_text_image_desc', 'local_byblos'), 'icon' => 'fa-columns'],
    ['type' => 'gallery', 'name' => get_string('sectiontype_gallery', 'local_byblos'), 'description' => get_string('sectiontype_gallery_desc', 'local_byblos'), 'icon' => 'fa-th'],
    ['type' => 'skills', 'name' => get_string('sectiontype_skills', 'local_byblos'), 'description' => get_string('sectiontype_skills_desc', 'local_byblos'), 'icon' => 'fa-bar-chart'],
    ['type' => 'timeline', 'name' => get_string('sectiontype_timeline', 'local_byblos'), 'description' => get_string('sectiontype_timeline_desc', 'local_byblos'), 'icon' => 'fa-clock-o'],
    ['type' => 'badges', 'name' => get_string('sectiontype_badges', 'local_byblos'), 'description' => get_string('sectiontype_badges_desc', 'local_byblos'), 'icon' => 'fa-certificate'],
    ['type' => 'completions', 'name' => get_string('sectiontype_completions', 'local_byblos'), 'description' => get_string('sectiontype_completions_desc', 'local_byblos'), 'icon' => 'fa-graduation-cap'],
    ['type' => 'social', 'name' => get_string('sectiontype_social', 'local_byblos'), 'description' => get_string('sectiontype_social_desc', 'local_byblos'), 'icon' => 'fa-share-alt'],
    ['type' => 'cta', 'name' => get_string('sectiontype_cta', 'local_byblos'), 'description' => get_string('sectiontype_cta_desc', 'local_byblos'), 'icon' => 'fa-bullhorn'],
    ['type' => 'divider', 'name' => get_string('sectiontype_divider', 'local_byblos'), 'description' => get_string('sectiontype_divider_desc', 'local_byblos'), 'icon' => 'fa-minus'],
    ['type' => 'custom', 'name' => get_string('sectiontype_custom', 'local_byblos'), 'description' => get_string('sectiontype_custom_desc', 'local_byblos'), 'icon' => 'fa-code'],
    ['type' => 'chart', 'name' => get_string('sectiontype_chart', 'local_byblos'), 'description' => get_string('sectiontype_chart_desc', 'local_byblos'), 'icon' => 'fa-bar-chart'],
    ['type' => 'cloud', 'name' => get_string('sectiontype_cloud', 'local_byblos'), 'description' => get_string('sectiontype_cloud_desc', 'local_byblos'), 'icon' => 'fa-cloud'],
    ['type' => 'quote', 'name' => get_string('sectiontype_quote', 'local_byblos'), 'description' => get_string('sectiontype_quote_desc', 'local_byblos'), 'icon' => 'fa-quote-left'],
    ['type' => 'stats', 'name' => get_string('sectiontype_stats', 'local_byblos'), 'description' => get_string('sectiontype_stats_desc', 'local_byblos'), 'icon' => 'fa-pie-chart'],
    ['type' => 'citations', 'name' => get_string('sectiontype_citations', 'local_byblos'), 'description' => get_string('sectiontype_citations_desc', 'local_byblos'), 'icon' => 'fa-book'],
    ['type' => 'files', 'name' => get_string('sectiontype_files', 'local_byblos'), 'description' => get_string('sectiontype_files_desc', 'local_byblos'), 'icon' => 'fa-folder-open-o'],
    ['type' => 'youtube', 'name' => get_string('sectiontype_youtube', 'local_byblos'), 'description' => get_string('sectiontype_youtube_desc', 'local_byblos'), 'icon' => 'fa-youtube-play'],
    ['type' => 'pagenav', 'name' => get_string('sectiontype_pagenav', 'local_byblos'), 'description' => get_string('sectiontype_pagenav_desc', 'local_byblos'), 'icon' => 'fa-sitemap'],
    ['type' => 'reflection', 'name' => get_string('sectiontype_reflection', 'local_byblos'), 'description' => get_string('sectiontype_reflection_desc', 'local_byblos'), 'icon' => 'fa-pencil-square-o'],
    ['type' => 'alignment', 'name' => get_string('sectiontype_alignment', 'local_byblos'), 'description' => get_string('sectiontype_alignment_desc', 'local_byblos'), 'icon' => 'fa-bullseye'],
    ['type' => 'goals', 'name' => get_string('sectiontype_goals', 'local_byblos'), 'description' => get_string('sectiontype_goals_desc', 'local_byblos'), 'icon' => 'fa-flag-checkered'],
];
// phpcs:enable moodle.Files.LineLength

// The custom-HTML tile is hidden for users who don't hold the gating capability
// (default: editingteacher + manager). The WS add_section enforces the same
// check, so this is purely a UI niceness.
if (!has_capability('local/byblos:editcustomhtml', $context)) {
    $sectiontypes = array_values(array_filter($sectiontypes, fn($s) => $s['type'] !== 'custom'));
}

// Theme definitions for the theme picker — derived from theme::get_all() so the picker
// stays in sync with the canonical theme registry.
$themes = [];
foreach (\local_byblos\theme::get_all() as $tdef) {
    $themes[] = [
        'key'         => $tdef['key'],
        'name'        => get_string($tdef['name'], 'local_byblos'),
        'preview_css' => 'background:' . $tdef['bg_color'] . '; border:1px solid rgba(0,0,0,0.1);',
        'selected'    => ($tdef['key'] === $pagerecord->themekey),
    ];
}

// Theme CSS class applied to the editor wrapper so theme styling is visible during editing.
$themedef = \local_byblos\theme::get($pagerecord->themekey ?? 'clean');
$themecssclass = $themedef['css_class'];

// Load user artefacts for the gallery artefact picker.
$artefacts = artefact::list_by_user((int) $pagerecord->userid);
$artefactsdata = [];
foreach ($artefacts as $a) {
    $artefactsdata[] = [
        'id'    => (int) $a->id,
        'type'  => $a->artefacttype ?? '',
        'title' => $a->title,
    ];
}

// Template context.
$templatecontext = [
    'pageid'        => $pageid,
    'title'         => $pagerecord->title,
    'description'   => $pagerecord->description ?? '',
    'status'        => $pagerecord->status,
    'theme_key'     => $pagerecord->themekey,
    'theme_css_class' => $themecssclass,
    'layout_key'    => $pagerecord->layoutkey,
    'has_sections'  => !empty($sectionsdata),
    'sections'      => $sectionsdata,
    'section_types' => $sectiontypes,
    'themes'        => $themes,
    'viewurl'       => (new moodle_url('/local/byblos/page.php', ['id' => $pageid]))->out(false),
    'dashboardurl'  => (new moodle_url('/local/byblos/index.php'))->out(false),
    'publishurl'    => (new moodle_url('/local/byblos/publish.php'))->out(false),
    'is_draft'      => ($pagerecord->status === 'draft'),
    'sesskey'       => sesskey(),
];

// Boot the AMD editor module.
$PAGE->requires->js_call_amd(
    'local_byblos/editor',
    'init',
    [$pageid]
);
$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/editor', $templatecontext);
echo $OUTPUT->footer();
