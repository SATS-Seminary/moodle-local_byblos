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
 * Section-based page renderer for portfolio pages.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos;

/**
 * Renders portfolio pages by iterating over sections and applying theme styling.
 *
 * Each section type has a dedicated render method that returns themed HTML
 * using Bootstrap 4 grid classes. The page wrapper applies the theme CSS class.
 */
class renderer {
    /**
     * Renders a complete portfolio page.
     *
     * Loads all sections for the page, renders each one using the appropriate
     * per-type method, and wraps the result in the page's theme class.
     *
     * @param \stdClass $page The page record from local_byblos_page.
     * @param int $userid The page owner's user ID (for badge/completion queries).
     * @return string Full HTML for the rendered page.
     */
    public static function render_page(\stdClass $page, int $userid): string {
        global $DB;

        $sections = array_values(
            $DB->get_records('local_byblos_section', ['pageid' => $page->id], 'sortorder ASC')
        );

        return self::render_page_from_parts($page, $sections, $userid);
    }

    /**
     * Render a page from already-loaded parts (used for both live and snapshotted views).
     *
     * @param \stdClass   $page      Page record.
     * @param \stdClass[] $sections  Section records (ordered).
     * @param int         $userid    Owner user id (for badges/completions lookups).
     * @param bool        $anchor    If true, wrap each section with a data-anchor="section:{id}"
     *                               attribute so JS can bind inline-comment UI to it.
     * @return string
     */
    public static function render_page_from_parts(
        \stdClass $page,
        array $sections,
        int $userid,
        bool $anchor = false,
    ): string {
        $themedef = theme::get($page->themekey ?? 'clean');

        $html = '';
        if (empty($sections)) {
            $html .= \html_writer::div(
                get_string('nosections', 'local_byblos'),
                'alert alert-info'
            );
        } else {
            $hostpageid = (int) ($page->id ?? 0);
            foreach ($sections as $section) {
                $rendered = self::render_section($section, $page->themekey ?? 'clean', $userid, $hostpageid);
                $width = in_array($section->width ?? 'full', ['full', 'half', 'third'], true)
                    ? ($section->width ?? 'full')
                    : 'full';
                $attrs = ['class' => 'byblos-section byblos-section-w-' . $width
                    . ' byblos-section-type-' . s($section->sectiontype)];
                if ($anchor && !empty($section->id)) {
                    $attrs['data-anchor'] = 'section:' . (int) $section->id;
                }
                $html .= \html_writer::tag('div', $rendered, $attrs);
            }
        }

        return \html_writer::div($html, 'byblos-page byblos-sections ' . s($themedef['css_class']));
    }

    /**
     * Render a portfolio from a snapshot payload.
     *
     * Handles both v1 (single page) and v2 (multi-page collection) payloads.
     * Multi-page renders each page sequentially with a heading anchor so
     * comment overlays keep working and reviewers can scan the full submission.
     *
     * @param array $payload The decoded snapshot JSON (from snapshot::payload()).
     * @param bool  $anchor  If true, decorate sections with data-anchor attributes.
     * @return string
     */
    public static function render_snapshot(array $payload, bool $anchor = false): string {
        $version = (int) ($payload['version'] ?? 1);
        if ($version >= 2 && !empty($payload['pages'])) {
            $parts = [];
            foreach ($payload['pages'] as $entry) {
                $pagerec = (object) ($entry['page'] ?? []);
                $sections = array_map(static fn(array $s): \stdClass => (object) $s, $entry['sections'] ?? []);
                $userid = (int) ($pagerec->userid ?? 0);
                $title = s((string) ($pagerec->title ?? ''));
                $parts[] = '<section class="byblos-snapshot-page" data-pageid="'
                    . (int) ($pagerec->id ?? 0) . '">'
                    . '<h2 class="byblos-snapshot-page-title mb-3">' . $title . '</h2>'
                    . self::render_page_from_parts($pagerec, $sections, $userid, $anchor)
                    . '</section>';
            }
            return '<div class="byblos-snapshot-collection">' . implode("\n", $parts) . '</div>';
        }

        // Legacy v1 single-page payload.
        $pagerec = (object) ($payload['page'] ?? []);
        $sections = array_map(static fn(array $s): \stdClass => (object) $s, $payload['sections'] ?? []);
        $userid = (int) ($pagerec->userid ?? 0);
        return self::render_page_from_parts($pagerec, $sections, $userid, $anchor);
    }

    /**
     * Dispatches rendering to the appropriate per-type method.
     *
     * @param \stdClass $section    The section record from local_byblos_section.
     * @param string    $themekey   The page's theme key.
     * @param int       $userid     The page owner's user ID.
     * @param int       $hostpageid Host page id (for section types that need
     *                              to know which page they're rendered on,
     *                              e.g. pagenav for active-state detection).
     * @return string Rendered HTML for this section.
     */
    public static function render_section(
        \stdClass $section,
        string $themekey,
        int $userid,
        int $hostpageid = 0
    ): string {
        $config = [];
        if (!empty($section->configdata)) {
            $config = json_decode($section->configdata, true) ?? [];
        }

        return match ($section->sectiontype) {
            'hero' => self::render_hero_section($config, $themekey),
            'text' => self::render_text_section($config),
            'text_image' => self::render_text_image_section($config),
            'gallery' => self::render_gallery_section($config),
            'skills' => self::render_skills_section($config, $themekey),
            'timeline' => self::render_timeline_section($config, $themekey),
            'badges' => self::render_badges_section($config, $userid),
            'completions' => self::render_completions_section($config, $userid),
            'social' => self::render_social_section($config),
            'cta' => self::render_cta_section($config, $themekey),
            'divider' => self::render_divider_section($config),
            'custom' => self::render_custom_section($section),
            'chart' => self::render_chart_section($config),
            'cloud' => self::render_cloud_section($config),
            'quote' => self::render_quote_section($config),
            'stats' => self::render_stats_section($config, $themekey),
            'citations' => self::render_citations_section($config),
            'files' => self::render_files_section($config),
            'youtube' => self::render_youtube_section($config),
            'pagenav' => self::render_pagenav_section($config, $hostpageid),
            'reflection' => section_helpers::render_reflection(
                $config,
                (int) $section->id,
                \context_user::instance($userid)->id
            ),
            'alignment' => section_helpers::render_alignment($config, $userid),
            'goals' => section_helpers::render_goals($config, $userid),
            default => \html_writer::div(
                get_string('unknownsectiontype', 'local_byblos', s($section->sectiontype)),
                'alert alert-warning'
            ),
        };
    }

    /**
     * Renders a hero banner section.
     *
     * Full-width banner with background colour/image, optional photo, name, title, subtitle.
     *
     * @param array $config Decoded configdata.
     * @param string $themekey The page's theme key.
     * @return string Rendered HTML.
     */
    public static function render_hero_section(array $config, string $themekey): string {
        $name = $config['name'] ?? '';
        $title = $config['title'] ?? '';
        $subtitle = $config['subtitle'] ?? '';
        $bgcolor = $config['bg_color'] ?? '#2c3e50';
        $bgimage = $config['bg_image'] ?? '';
        $photourl = $config['photo_url'] ?? '';

        $bgstyle = !empty($bgimage)
            ? "background:linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)),url('" . s($bgimage)
                . "') center/cover no-repeat !important;"
            : "background-color:" . s($bgcolor) . " !important;";

        // Inline styles on every element so the hero looks identical regardless of the
        // page theme's h1/h3/p/img rules. Theme rules are !important too, and a
        // descendant selector like `.byblos-theme-academic h1` outranks a single-class
        // selector on the child. Inline style wins against both.
        $html = '<div class="byblos-section-hero text-center" style="' . $bgstyle
            . ' color:#fff !important; padding:3rem 2rem !important; border-radius:0.5rem !important;">';

        if (!empty($photourl)) {
            $html .= '<div class="mb-3"><img class="byblos-hero-photo" src="' . s($photourl)
                . '" alt="' . s($name)
                . '" style="width:120px !important; height:120px !important;'
                . ' border-radius:50% !important; object-fit:cover !important;'
                . ' border:3px solid rgba(255,255,255,0.8) !important;"></div>';
        }
        if ($name !== '') {
            $html .= '<h1 class="byblos-hero-name" style="color:#fff !important;'
                . ' font-size:2.5rem !important; font-weight:700 !important;'
                . ' margin-bottom:0.25rem !important; border-bottom:none !important;'
                . ' padding-bottom:0 !important; background:none !important;'
                . ' -webkit-text-fill-color:#fff !important;">' . s($name) . '</h1>';
        }
        if ($title !== '') {
            $html .= '<h3 class="byblos-hero-title" style="color:rgba(255,255,255,0.9) !important;'
                . ' font-weight:400 !important; margin-bottom:0.25rem !important;'
                . ' border-bottom:none !important; padding-bottom:0 !important;'
                . ' background:none !important; -webkit-text-fill-color:rgba(255,255,255,0.9) !important;">'
                . s($title) . '</h3>';
        }
        if ($subtitle !== '') {
            $html .= '<p class="byblos-hero-subtitle" style="color:rgba(255,255,255,0.8) !important;'
                . ' font-size:1.1rem !important; margin-bottom:0 !important;">'
                . s($subtitle) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Renders a text section.
     *
     * Heading and rich-text body.
     *
     * @param array $config Decoded configdata.
     * @return string Rendered HTML.
     */
    public static function render_text_section(array $config): string {
        $heading = $config['heading'] ?? '';
        $body = $config['body'] ?? '';

        $html = '';
        if ($heading !== '') {
            $html .= \html_writer::tag('h2', s($heading), ['class' => 'byblos-text-heading']);
        }
        if ($body !== '') {
            $html .= \html_writer::div(section_helpers::clean_body($body), 'byblos-text-body');
        } else {
            $html .= \html_writer::tag(
                'p',
                \html_writer::tag('em', get_string('emptytext', 'local_byblos')),
                ['class' => 'text-muted']
            );
        }

        return \html_writer::div($html, 'byblos-section-text');
    }

    /**
     * Renders a text + image section.
     *
     * Two-column layout with text on one side and image on the other.
     * Can be reversed via the 'reversed' config flag.
     *
     * @param array $config Decoded configdata.
     * @return string Rendered HTML.
     */
    public static function render_text_image_section(array $config): string {
        $heading = $config['heading'] ?? '';
        $body = $config['body'] ?? '';
        $imageurl = $config['image_url'] ?? '';
        $imagealt = $config['image_alt'] ?? '';
        $reversed = !empty($config['reversed']);

        // Text column.
        $textcontent = '';
        if ($heading !== '') {
            $textcontent .= \html_writer::tag('h3', s($heading));
        }
        if ($body !== '') {
            $textcontent .= \html_writer::div(section_helpers::clean_body($body));
        }

        $textorder = $reversed ? 'order-2' : 'order-1';
        $imageorder = $reversed ? 'order-1' : 'order-2';

        $textcol = \html_writer::div($textcontent, "col-md-6 {$textorder} byblos-text-image-text");

        // Image column.
        if (!empty($imageurl)) {
            $imagecontent = \html_writer::img($imageurl, s($imagealt), [
                'class' => 'img-fluid rounded',
            ]);
        } else {
            $imagecontent = \html_writer::div(
                \html_writer::tag('i', '', ['class' => 'fa fa-image fa-3x mb-2 d-block']) .
                get_string('addimage', 'local_byblos'),
                'text-center text-muted py-5 byblos-image-placeholder'
            );
        }
        $imagecol = \html_writer::div($imagecontent, "col-md-6 {$imageorder} byblos-text-image-img");

        $row = \html_writer::div($textcol . $imagecol, 'row align-items-center');
        return \html_writer::div($row, 'byblos-section-text-image');
    }

    /**
     * Renders a gallery section.
     *
     * Grid of image/artefact cards arranged in configurable columns.
     *
     * @param array $config Decoded configdata.
     * @return string Rendered HTML.
     */
    public static function render_gallery_section(array $config): string {
        $columns = max(1, min(4, (int) ($config['columns'] ?? 3)));
        $items = $config['items'] ?? [];

        $colclass = 'col-md-' . (int) (12 / $columns);

        $html = '';
        if (empty($items)) {
            $html .= \html_writer::div(
                \html_writer::tag(
                    'p',
                    \html_writer::tag('em', get_string('emptygallery', 'local_byblos')),
                    ['class' => 'text-muted text-center py-3']
                ),
                'col-12'
            );
        } else {
            foreach ($items as $item) {
                $card = '';
                if (!empty($item['image_url'])) {
                    $card .= \html_writer::img($item['image_url'], s($item['title'] ?? ''), [
                        'class' => 'card-img-top byblos-gallery-img',
                    ]);
                }
                $cardbody = '';
                if (!empty($item['title'])) {
                    $cardbody .= \html_writer::tag('h5', s($item['title']), ['class' => 'card-title']);
                }
                if (!empty($item['description'])) {
                    $cardbody .= \html_writer::tag('p', s($item['description']), ['class' => 'card-text text-muted small']);
                }
                $card .= \html_writer::div($cardbody, 'card-body');
                $html .= \html_writer::div(
                    \html_writer::div($card, 'card h-100 byblos-gallery-card'),
                    $colclass . ' mb-3'
                );
            }
        }

        return \html_writer::div(
            \html_writer::div($html, 'row'),
            'byblos-section-gallery'
        );
    }

    /**
     * Renders a skills progress bar section.
     *
     * Each skill is shown as a labelled progress bar coloured by the theme accent.
     *
     * @param array $config Decoded configdata.
     * @param string $themekey The page's theme key.
     * @return string Rendered HTML.
     */
    /**
     * Clamp a skill level into the 1..5 named-level range.
     *
     * Pre-Mar-2026 pages stored a 0..100 percentage in this column. Treat any
     * value above 5 as the top level (5 = Expert) so legacy pages still render
     * a recognisable bar rather than overflowing.
     *
     * @param int $level Stored level value.
     * @return int 1..5 inclusive.
     */
    public static function clamp_skill_level(int $level): int {
        if ($level > 5) {
            return 5;
        }
        return max(1, min(5, $level));
    }

    /**
     * Render a skills section using the 1..5 named-proficiency scale.
     *
     * @param array $config Decoded configdata.
     * @param string $themekey Page theme key.
     * @return string Rendered HTML.
     */
    public static function render_skills_section(array $config, string $themekey): string {
        $heading = $config['heading'] ?? get_string('skills', 'local_byblos');
        $skills = $config['skills'] ?? [];
        $accentcolor = theme::get_accent_color($themekey);

        $html = '';
        if ($heading !== '') {
            $html .= \html_writer::tag('h2', s($heading), ['class' => 'byblos-skills-heading']);
        }

        if (empty($skills)) {
            $html .= \html_writer::tag(
                'p',
                \html_writer::tag('em', get_string('noskills', 'local_byblos')),
                ['class' => 'text-muted']
            );
        } else {
            foreach ($skills as $skill) {
                $level = self::clamp_skill_level((int) ($skill['level'] ?? 1));
                // Map 1..5 onto a 20/40/60/80/100 percent bar fill.
                $pct = $level * 20;
                $levellabel = get_string('skill_level_' . $level, 'local_byblos');
                $label = \html_writer::div(
                    \html_writer::tag('span', s($skill['name'] ?? '')) .
                    \html_writer::tag('span', $levellabel, ['class' => 'byblos-skill-level-name']),
                    'd-flex justify-content-between mb-1 byblos-skill-label'
                );
                $bar = \html_writer::div(
                    \html_writer::div('', 'progress-bar', [
                        'style' => "width:{$pct}% !important; background-color:{$accentcolor} !important;",
                        'role' => 'progressbar',
                        'aria-valuenow' => (string) $level,
                        'aria-valuemin' => '1',
                        'aria-valuemax' => '5',
                        'aria-label' => $levellabel,
                    ]),
                    'progress byblos-skill-bar'
                );
                $html .= \html_writer::div($label . $bar, 'mb-2');
            }
        }

        return \html_writer::div($html, 'byblos-section-skills');
    }

    /**
     * Renders a timeline section.
     *
     * Vertical timeline with themed dot colours and date/title/description items.
     *
     * @param array $config Decoded configdata.
     * @param string $themekey The page's theme key.
     * @return string Rendered HTML.
     */
    public static function render_timeline_section(array $config, string $themekey): string {
        $heading = $config['heading'] ?? get_string('timeline', 'local_byblos');
        $items = $config['items'] ?? [];
        $accentcolor = theme::get_accent_color($themekey);

        $html = '';
        if ($heading !== '') {
            $html .= \html_writer::tag('h2', s($heading), ['class' => 'byblos-timeline-heading']);
        }

        if (empty($items)) {
            $html .= \html_writer::tag(
                'p',
                \html_writer::tag('em', get_string('notimeline', 'local_byblos')),
                ['class' => 'text-muted']
            );
        } else {
            $entries = '';
            foreach ($items as $item) {
                $entry = '';
                // The dot is rendered via CSS pseudo-element; accent passed as data attribute.
                if (!empty($item['date'])) {
                    $entry .= \html_writer::tag('small', s($item['date']), ['class' => 'text-muted byblos-timeline-date']);
                }
                $entry .= \html_writer::div(s($item['title'] ?? ''), 'byblos-timeline-title');
                if (!empty($item['description'])) {
                    $entry .= \html_writer::div(s($item['description']), 'byblos-timeline-desc');
                }
                $entries .= \html_writer::div($entry, 'byblos-timeline-entry', [
                    'data-accent' => $accentcolor,
                ]);
            }
            $html .= \html_writer::div($entries, 'byblos-timeline-track', [
                'data-accent' => $accentcolor,
            ]);
        }

        return \html_writer::div($html, 'byblos-section-timeline');
    }

    /**
     * Renders a badges section.
     *
     * Queries the badge_issued table for the page owner and displays badge cards.
     *
     * @param array $config Decoded configdata.
     * @param int $userid The page owner's user ID.
     * @return string Rendered HTML.
     */
    public static function render_badges_section(array $config, int $userid): string {
        global $DB;

        $heading = $config['heading'] ?? get_string('badges', 'local_byblos');
        $show = $config['show'] ?? true;
        if (!$show) {
            return '';
        }

        $html = '';
        if ($heading !== '') {
            $html .= \html_writer::tag('h2', s($heading), ['class' => 'byblos-badges-heading']);
        }

        // Query badge_issued joined with badge for the user.
        $sql = "SELECT b.id, b.name, b.description
                  FROM {badge_issued} bi
                  JOIN {badge} b ON b.id = bi.badgeid
                 WHERE bi.userid = :userid
                   AND b.status != 4
              ORDER BY bi.dateissued DESC";
        $badges = $DB->get_records_sql($sql, ['userid' => $userid]);

        if (empty($badges)) {
            $html .= \html_writer::tag(
                'p',
                \html_writer::tag('em', get_string('nobadges', 'local_byblos')),
                ['class' => 'text-muted']
            );
        } else {
            $cards = '';
            foreach ($badges as $badge) {
                $card = \html_writer::div(
                    \html_writer::tag('i', '', ['class' => 'fa fa-certificate fa-3x text-warning mb-2 d-block']) .
                    \html_writer::tag('h6', s($badge->name), ['class' => 'card-title mb-1']) .
                    (!empty($badge->description)
                        ? \html_writer::tag(
                            'p',
                            s(shorten_text($badge->description, 80)),
                            ['class' => 'card-text text-muted small mb-0']
                        )
                        : ''),
                    'card-body'
                );
                $cards .= \html_writer::div(
                    \html_writer::div($card, 'card h-100 text-center byblos-badge-card'),
                    'col-md-3 col-sm-4 col-6 mb-3'
                );
            }
            $html .= \html_writer::div($cards, 'row');
        }

        return \html_writer::div($html, 'byblos-section-badges');
    }

    /**
     * Renders a course completions section.
     *
     * Queries course_completions for the page owner and displays completion cards.
     *
     * @param array $config Decoded configdata.
     * @param int $userid The page owner's user ID.
     * @return string Rendered HTML.
     */
    public static function render_completions_section(array $config, int $userid): string {
        global $DB;

        $heading = $config['heading'] ?? get_string('completions', 'local_byblos');
        $show = $config['show'] ?? true;
        if (!$show) {
            return '';
        }

        $html = '';
        if ($heading !== '') {
            $html .= \html_writer::tag('h2', s($heading), ['class' => 'byblos-completions-heading']);
        }

        // Query course_completions joined with course for the user.
        $sql = "SELECT cc.id, c.id AS courseid, c.fullname, c.shortname, cc.timecompleted
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                 WHERE cc.userid = :userid
                   AND cc.timecompleted IS NOT NULL
                   AND cc.timecompleted > 0
              ORDER BY cc.timecompleted DESC";
        $completions = $DB->get_records_sql($sql, ['userid' => $userid]);

        if (empty($completions)) {
            $html .= \html_writer::tag(
                'p',
                \html_writer::tag('em', get_string('nocompletions', 'local_byblos')),
                ['class' => 'text-muted']
            );
        } else {
            $cards = '';
            foreach ($completions as $completion) {
                $datestr = userdate($completion->timecompleted, get_string('strftimedatefull', 'langconfig'));
                $card = \html_writer::div(
                    \html_writer::tag(
                        'h6',
                        \html_writer::tag('i', '', ['class' => 'fa fa-graduation-cap text-success']) .
                        ' ' . s($completion->fullname),
                        ['class' => 'card-title']
                    ) .
                    \html_writer::tag('p', $datestr, ['class' => 'card-text text-muted small mb-0']),
                    'card-body'
                );
                $cards .= \html_writer::div(
                    \html_writer::div($card, 'card h-100 byblos-completion-card'),
                    'col-md-4 col-sm-6 mb-3'
                );
            }
            $html .= \html_writer::div($cards, 'row');
        }

        return \html_writer::div($html, 'byblos-section-completions');
    }

    /**
     * Renders a social links section.
     *
     * Displays social media icon links in a centered row.
     *
     * @param array $config Decoded configdata.
     * @return string Rendered HTML.
     */
    public static function render_social_section(array $config): string {
        $links = $config['links'] ?? [];

        $iconmap = [
            'linkedin' => 'fa-linkedin',
            'github' => 'fa-github',
            'twitter' => 'fa-twitter',
            'facebook' => 'fa-facebook',
            'instagram' => 'fa-instagram',
            'youtube' => 'fa-youtube',
            'globe' => 'fa-globe',
        ];

        $haslinks = false;
        foreach ($links as $link) {
            if (!empty($link['url'])) {
                $haslinks = true;
                break;
            }
        }

        $html = '';
        if (!$haslinks) {
            $html .= \html_writer::tag(
                'p',
                \html_writer::tag('em', get_string('nosocial', 'local_byblos')),
                ['class' => 'text-muted']
            );
        } else {
            $icons = '';
            foreach ($links as $link) {
                if (empty($link['url'])) {
                    continue;
                }
                $platform = $link['platform'] ?? '';
                $icon = $iconmap[$platform] ?? 'fa-link';
                $icons .= \html_writer::link(
                    $link['url'],
                    \html_writer::tag('i', '', ['class' => 'fa ' . $icon]),
                    [
                        'target' => '_blank',
                        'rel' => 'noopener',
                        'title' => s($platform),
                        'class' => 'byblos-social-link',
                    ]
                );
            }
            $html .= \html_writer::div($icons, 'd-flex justify-content-center flex-wrap byblos-social-icons');
        }

        return \html_writer::div($html, 'byblos-section-social text-center');
    }

    /**
     * Renders a call-to-action section.
     *
     * Full-width banner with heading, body text, and a button.
     *
     * @param array $config Decoded configdata.
     * @param string $themekey The page's theme key.
     * @return string Rendered HTML.
     */
    public static function render_cta_section(array $config, string $themekey): string {
        $heading = $config['heading'] ?? '';
        $body = $config['body'] ?? '';
        $buttontext = $config['button_text'] ?? get_string('learnmore', 'local_byblos');
        $buttonurl = $config['button_url'] ?? '#';
        $bgcolor = $config['bg_color'] ?? '#0d6efd';

        $inner = '';
        if ($heading !== '') {
            $inner .= \html_writer::tag('h2', s($heading), ['class' => 'byblos-cta-heading']);
        }
        if ($body !== '') {
            $inner .= \html_writer::tag('p', s($body), ['class' => 'byblos-cta-body']);
        }
        if ($buttontext !== '') {
            $inner .= \html_writer::link($buttonurl, s($buttontext), [
                'class' => 'btn btn-light btn-lg byblos-cta-button',
            ]);
        }

        return \html_writer::div($inner, 'byblos-section-cta text-center', [
            'style' => 'background-color:' . s($bgcolor) . ' !important;'
                . ' color:#fff !important;'
                . ' padding:2.5rem 2rem !important;'
                . ' border-radius:0.5rem !important;',
        ]);
    }

    /**
     * Renders a divider section.
     *
     * Either a line or a spacer depending on config.
     *
     * @param array $config Decoded configdata.
     * @return string Rendered HTML.
     */
    public static function render_divider_section(array $config): string {
        $style = $config['style'] ?? 'line';
        $spacing = $config['spacing'] ?? '2rem';

        if ($style === 'space') {
            return \html_writer::div('', 'byblos-section-divider byblos-divider-space', [
                'style' => "height:" . s($spacing) . " !important;",
            ]);
        }

        return \html_writer::div(
            \html_writer::empty_tag('hr', ['class' => 'byblos-divider-line']),
            'byblos-section-divider',
            ['style' => "padding:" . s($spacing) . " 0 !important;"]
        );
    }

    /**
     * Renders a custom HTML section.
     *
     * Displays freeform HTML from the section content or configdata html field.
     *
     * @param \stdClass $section The section record.
     * @return string Rendered HTML.
     */
    public static function render_custom_section(\stdClass $section): string {
        $content = $section->content ?? '';

        if ($content === '') {
            $config = [];
            if (!empty($section->configdata)) {
                $config = json_decode($section->configdata, true) ?? [];
            }
            $content = $config['html'] ?? '';
        }

        if ($content === '') {
            return \html_writer::div(
                \html_writer::tag('em', get_string('emptycustom', 'local_byblos')),
                'byblos-section-custom text-muted'
            );
        }

        // Pass through Moodle's HTMLPurifier policy. This strips <script>,
        // event handlers, javascript: URLs, iframe/object/embed, meta refresh,
        // and tightens inline style — see the threat-model commentary in the
        // section_external add_section() guard.
        $clean = format_text($content, FORMAT_HTML, [
            'noclean' => false,
            'context' => \context_system::instance(),
            'allowid' => false,
            'overflowdiv' => false,
        ]);

        return \html_writer::div($clean, 'byblos-section-custom');
    }

    /**
     * Render a server-side SVG chart (bar/line/pie/donut).
     *
     * Delegates to {@see section_helpers::render_chart()} so the editor
     * in-place preview and the published view render identically.
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (unused; kept for signature parity).
     * @return string HTML fragment.
     */
    public static function render_chart_section(array $config, string $themekey = ''): string {
        unset($themekey);
        return section_helpers::render_chart($config);
    }

    /**
     * Render a word-cloud of styled flex-wrapped spans.
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (unused; kept for signature parity).
     * @return string HTML fragment.
     */
    public static function render_cloud_section(array $config, string $themekey = ''): string {
        unset($themekey);
        return section_helpers::render_cloud($config);
    }

    /**
     * Render a pull-quote with attribution.
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (unused; kept for signature parity).
     * @return string HTML fragment.
     */
    public static function render_quote_section(array $config, string $themekey = ''): string {
        unset($themekey);
        return section_helpers::render_quote($config);
    }

    /**
     * Render a row of 2–4 big-number stat cards.
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (accent colour for numbers).
     * @return string HTML fragment.
     */
    public static function render_stats_section(array $config, string $themekey = ''): string {
        return section_helpers::render_stats($config, $themekey);
    }

    /**
     * Render a numbered academic citation list.
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (unused; kept for signature parity).
     * @return string HTML fragment.
     */
    public static function render_citations_section(array $config, string $themekey = ''): string {
        unset($themekey);
        return section_helpers::render_citations($config);
    }

    /**
     * Renders a configurable file list (list / tile / thumbs display).
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (unused; kept for signature parity).
     * @return string HTML fragment.
     */
    public static function render_files_section(array $config, string $themekey = ''): string {
        unset($themekey);
        return section_helpers::render_files($config);
    }

    /**
     * Renders an embedded YouTube video.
     *
     * @param array  $config   Decoded configdata.
     * @param string $themekey Page theme key (unused; kept for signature parity).
     * @return string HTML fragment.
     */
    public static function render_youtube_section(array $config, string $themekey = ''): string {
        unset($themekey);
        return section_helpers::render_youtube($config);
    }

    /**
     * Renders a page-navigation widget (tabs / pills / cards / next-prev).
     *
     * @param array $config        Decoded configdata.
     * @param int   $hostpageid    Host page id for active-state / next-prev logic.
     * @return string HTML fragment.
     */
    public static function render_pagenav_section(array $config, int $hostpageid = 0): string {
        return section_helpers::render_pagenav($config, $hostpageid);
    }
}
