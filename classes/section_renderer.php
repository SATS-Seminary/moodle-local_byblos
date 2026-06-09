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

namespace local_byblos;

/**
 * Server-side section renderer for the portfolio page editor.
 *
 * Each section type has a render method that produces an HTML fragment
 * for inline preview in the editor. Ported from MoodleGo's section_render.go.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_renderer {
    /**
     * Render a single section as an HTML fragment.
     *
     * @param \stdClass $section  Section record from the DB.
     * @param string    $themekey Page theme key.
     * @return string HTML fragment.
     */
    public static function render(\stdClass $section, string $themekey = 'clean'): string {
        $cfg = json_decode($section->configdata ?? '{}', true) ?: [];
        $content = $section->content ?? '';

        $cfgstr = function (string $key, string $fallback = '') use ($cfg): string {
            return isset($cfg[$key]) && is_string($cfg[$key]) ? $cfg[$key] : $fallback;
        };
        $cfgbool = function (string $key, bool $fallback = false) use ($cfg): bool {
            return isset($cfg[$key]) ? (bool) $cfg[$key] : $fallback;
        };
        $cfgint = function (string $key, int $fallback = 0) use ($cfg): int {
            return isset($cfg[$key]) ? (int) $cfg[$key] : $fallback;
        };

        switch ($section->sectiontype) {
            case 'hero':
                return self::render_hero($cfg, $cfgstr, $themekey);
            case 'text':
                return self::render_text($cfgstr);
            case 'text_image':
                return self::render_text_image($cfgstr, $cfgbool);
            case 'gallery':
                return self::render_gallery($cfg, $cfgint);
            case 'skills':
                return self::render_skills($cfg, $cfgstr, $themekey);
            case 'timeline':
                return self::render_timeline($cfg, $cfgstr, $themekey);
            case 'badges':
                return self::render_badges($cfgstr, $cfgbool);
            case 'completions':
                return self::render_completions($cfgstr, $cfgbool);
            case 'social':
                return self::render_social($cfg);
            case 'cta':
                return self::render_cta($cfgstr);
            case 'divider':
                return self::render_divider($cfgstr);
            case 'custom':
                return self::render_custom($content, $cfg);
            case 'chart':
                return section_helpers::render_chart($cfg);
            case 'cloud':
                return section_helpers::render_cloud($cfg);
            case 'quote':
                return section_helpers::render_quote($cfg);
            case 'stats':
                return section_helpers::render_stats($cfg, $themekey);
            case 'citations':
                return section_helpers::render_citations($cfg);
            case 'files':
                return section_helpers::render_files($cfg);
            case 'youtube':
                return section_helpers::render_youtube($cfg);
            case 'pagenav':
                return section_helpers::render_pagenav($cfg, (int) ($section->pageid ?? 0));
            case 'reflection':
                $rowner = self::resolve_owner_id($section);
                return section_helpers::render_reflection(
                    $cfg,
                    (int) $section->id,
                    \context_user::instance($rowner)->id
                );
            case 'alignment':
                return section_helpers::render_alignment($cfg, self::resolve_owner_id($section));
            case 'goals':
                return section_helpers::render_goals($cfg, self::resolve_owner_id($section));
            default:
                return '<div class="alert alert-warning">Unknown section type: '
                    . s($section->sectiontype) . '</div>';
        }
    }

    /**
     * Resolve the page-owner user id for a section (falls back to the current user).
     *
     * @param \stdClass $section
     * @return int
     */
    private static function resolve_owner_id(\stdClass $section): int {
        global $USER;

        $pageid = (int) ($section->pageid ?? 0);
        if ($pageid) {
            $page = page::get($pageid);
            if ($page) {
                return (int) $page->userid;
            }
        }
        return (int) $USER->id;
    }

    /**
     * Render the hero section.
     *
     * @param array $cfg Decoded configdata.
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @param string $themekey Page theme key.
     * @return string Rendered HTML.
     */
    private static function render_hero(array $cfg, callable $cfgstr, string $themekey): string {
        $name     = $cfgstr('name', '');
        $title    = $cfgstr('title', '');
        $subtitle = $cfgstr('subtitle', '');
        $bgcolor  = $cfgstr('bg_color', '#2c3e50');
        $bgimage  = $cfgstr('bg_image', '');
        $photourl = $cfgstr('photo_url', '');

        if ($bgimage !== '') {
            $bgstyle = "background:linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)),"
                . "url('" . s($bgimage) . "') center/cover no-repeat !important;";
        } else {
            $bgstyle = "background-color:" . s($bgcolor) . " !important;";
        }

        $html = '<div class="eportfolio-section-hero text-center" style="'
            . $bgstyle . ' color:#fff !important; padding:3rem 2rem !important; border-radius:0.5rem !important;">';

        if ($photourl !== '') {
            $html .= '<div class="mb-3"><img src="' . s($photourl) . '" alt="' . s($name)
                . '" style="width:120px !important; height:120px !important; border-radius:50% !important;'
                . ' object-fit:cover !important; border:3px solid rgba(255,255,255,0.8) !important;"></div>';
        }
        if ($name !== '') {
            $html .= '<h1 style="color:#fff !important; font-size:2.5rem !important;'
                . ' font-weight:700 !important; margin-bottom:0.25rem !important;">' . s($name) . '</h1>';
        }
        if ($title !== '') {
            $html .= '<h3 style="color:rgba(255,255,255,0.9) !important;'
                . ' font-weight:400 !important; margin-bottom:0.25rem !important;">' . s($title) . '</h3>';
        }
        if ($subtitle !== '') {
            $html .= '<p style="color:rgba(255,255,255,0.8) !important;'
                . ' font-size:1.1rem !important; margin-bottom:0 !important;">' . s($subtitle) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the text section.
     *
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @return string Rendered HTML.
     */
    private static function render_text(callable $cfgstr): string {
        $heading = $cfgstr('heading', '');
        $body    = $cfgstr('body', '');

        $html = '<div class="eportfolio-section-text" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 style="margin-bottom:0.75rem !important;">' . s($heading) . '</h2>';
        }
        if ($body !== '') {
            $html .= '<div class="section-body">' . section_helpers::clean_body($body) . '</div>';
        } else {
            $html .= '<p class="text-muted"><em>Click Edit to add content...</em></p>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render the text image section.
     *
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @param callable $cfgbool Shortcut returning a boolean config value.
     * @return string Rendered HTML.
     */
    private static function render_text_image(callable $cfgstr, callable $cfgbool): string {
        $heading  = $cfgstr('heading', '');
        $body     = $cfgstr('body', '');
        $imageurl = $cfgstr('image_url', '');
        $imagealt = $cfgstr('image_alt', '');
        $reversed = $cfgbool('reversed', false);

        if ($imageurl !== '') {
            $imagehtml = '<img src="' . s($imageurl) . '" alt="' . s($imagealt)
                . '" class="img-fluid rounded" style="max-width:100% !important;">';
        } else {
            $imagehtml = '<div class="text-center text-muted py-5" style="background:#f0f0f0 !important;'
                . ' border-radius:0.5rem !important;"><i class="fa fa-image fa-3x mb-2"'
                . ' style="display:block !important;"></i>Add an image</div>';
        }

        $textorder  = $reversed ? 'order-2' : 'order-1';
        $imageorder = $reversed ? 'order-1' : 'order-2';

        $html = '<div class="eportfolio-section-text-image" style="padding:1.5rem 0 !important;">';
        $html .= '<div class="row align-items-center">';
        $html .= '<div class="col-md-6 ' . $textorder . '" style="padding:1rem !important;">';
        if ($heading !== '') {
            $html .= '<h3 style="margin-bottom:0.5rem !important;">' . s($heading) . '</h3>';
        }
        if ($body !== '') {
            $html .= '<div>' . section_helpers::clean_body($body) . '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="col-md-6 ' . $imageorder . '" style="padding:1rem !important;">'
            . $imagehtml . '</div>';
        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render the gallery section.
     *
     * @param array $cfg Decoded configdata.
     * @param callable $cfgint Shortcut returning an int config value, with fallback.
     * @return string Rendered HTML.
     */
    private static function render_gallery(array $cfg, callable $cfgint): string {
        $columns = $cfgint('columns', 3);
        $columns = max(1, min(4, $columns));
        $colclass = 'col-md-' . intval(12 / $columns);

        $items = [];
        if (isset($cfg['items']) && is_array($cfg['items'])) {
            $items = $cfg['items'];
        }

        $html = '<div class="eportfolio-section-gallery" style="padding:1.5rem 0 !important;">';
        $html .= '<div class="row">';

        if (empty($items)) {
            $html .= '<div class="col-12"><p class="text-muted text-center py-3">'
                . '<em>No gallery items yet. Click Edit to add artefacts or images.</em></p></div>';
        } else {
            foreach ($items as $item) {
                $html .= '<div class="' . $colclass . ' mb-3">';
                $html .= '<div class="card h-100">';
                $imgurl = $item['image_url'] ?? '';
                $title  = $item['title'] ?? '';
                $desc   = $item['description'] ?? '';
                if ($imgurl !== '') {
                    $html .= '<img src="' . s($imgurl) . '" class="card-img-top" alt="' . s($title)
                        . '" style="max-height:200px !important; object-fit:cover !important;">';
                }
                $html .= '<div class="card-body">';
                if ($title !== '') {
                    $html .= '<h5 class="card-title">' . s($title) . '</h5>';
                }
                if ($desc !== '') {
                    $html .= '<p class="card-text text-muted small">' . s($desc) . '</p>';
                }
                $html .= '</div></div></div>';
            }
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render the skills section.
     *
     * @param array $cfg Decoded configdata.
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @param string $themekey Page theme key.
     * @return string Rendered HTML.
     */
    private static function render_skills(array $cfg, callable $cfgstr, string $themekey): string {
        $heading = $cfgstr('heading', 'Skills');

        $barcolor = '#0d6efd';
        switch ($themekey) {
            case 'academic':
                $barcolor = '#1a365d';
                break;
            case 'modern-dark':
                $barcolor = '#f38ba8';
                break;
            case 'creative':
                $barcolor = '#7c3aed';
                break;
            case 'corporate':
                $barcolor = '#0ea5e9';
                break;
        }

        $skills = [];
        if (isset($cfg['skills']) && is_array($cfg['skills'])) {
            $skills = $cfg['skills'];
        }

        $html = '<div class="eportfolio-section-skills" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 style="margin-bottom:1rem !important;">' . s($heading) . '</h2>';
        }

        if (empty($skills)) {
            $html .= '<p class="text-muted"><em>No skills added yet.</em></p>';
        } else {
            foreach ($skills as $sk) {
                $name  = s($sk['name'] ?? '');
                $level = renderer::clamp_skill_level((int) ($sk['level'] ?? 1));
                // Map 1..5 onto a 20/40/60/80/100 percent bar fill.
                $pct = $level * 20;
                $levellabel = s(get_string('skill_level_' . $level, 'local_byblos'));
                $html .= '<div class="mb-2">';
                $html .= '<div class="d-flex justify-content-between mb-1" style="font-size:0.85rem !important;">';
                $html .= '<span>' . $name . '</span><span class="byblos-skill-level-name">'
                    . $levellabel . '</span></div>';
                $html .= '<div class="progress" style="height:0.6rem !important;">';
                $html .= '<div class="progress-bar" style="width:' . $pct . '% !important; background-color:'
                    . $barcolor . ' !important;"></div></div></div>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the timeline section.
     *
     * @param array $cfg Decoded configdata.
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @param string $themekey Page theme key.
     * @return string Rendered HTML.
     */
    private static function render_timeline(array $cfg, callable $cfgstr, string $themekey): string {
        $heading = $cfgstr('heading', 'Timeline');

        $dotcolor  = '#0d6efd';
        $linecolor = '#dee2e6';
        switch ($themekey) {
            case 'academic':
                $dotcolor = '#1a365d';
                $linecolor = '#c9b99a';
                break;
            case 'modern-dark':
                $dotcolor = '#f38ba8';
                $linecolor = '#45475a';
                break;
            case 'creative':
                $dotcolor = '#7c3aed';
                $linecolor = '#ddd6fe';
                break;
            case 'corporate':
                $dotcolor = '#0ea5e9';
                $linecolor = '#e2e8f0';
                break;
        }

        $items = [];
        if (isset($cfg['items']) && is_array($cfg['items'])) {
            $items = $cfg['items'];
        }

        $html = '<div class="eportfolio-section-timeline" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 style="margin-bottom:1rem !important;">' . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>No timeline entries yet.</em></p>';
        } else {
            $html .= '<div style="position:relative !important; padding-left:2rem !important;">';
            $html .= '<div style="position:absolute !important; left:0.6rem !important; top:0 !important;'
                . ' bottom:0 !important; width:2px !important; background:' . $linecolor . ' !important;"></div>';
            foreach ($items as $it) {
                $html .= '<div style="position:relative !important; margin-bottom:1.25rem !important;">';
                $html .= '<div style="position:absolute !important; left:-1.7rem !important; top:0.25rem !important;'
                    . ' width:12px !important; height:12px !important; border-radius:50% !important; background:'
                    . $dotcolor . ' !important; border:2px solid #fff !important;"></div>';
                $date  = $it['date'] ?? '';
                $title = $it['title'] ?? '';
                $desc  = $it['description'] ?? '';
                if ($date !== '') {
                    $html .= '<small class="text-muted">' . s($date) . '</small>';
                }
                $html .= '<div style="font-weight:600 !important;">' . s($title) . '</div>';
                if ($desc !== '') {
                    $html .= '<div style="font-size:0.85rem !important; color:#666 !important;">'
                        . s($desc) . '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the badges section.
     *
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @param callable $cfgbool Shortcut returning a boolean config value.
     * @return string Rendered HTML.
     */
    private static function render_badges(callable $cfgstr, callable $cfgbool): string {
        $heading = $cfgstr('heading', 'Badges');
        $show    = $cfgbool('show', true);
        if (!$show) {
            return '';
        }

        // In the editor preview, show placeholder. Actual badges render on the view page.
        $html = '<div class="eportfolio-section-badges" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 style="margin-bottom:1rem !important;">' . s($heading) . '</h2>';
        }
        $html .= '<p class="text-muted"><em><i class="fa fa-certificate text-warning"></i>'
            . ' Your earned badges will appear here automatically.</em></p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Render the completions section.
     *
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @param callable $cfgbool Shortcut returning a boolean config value.
     * @return string Rendered HTML.
     */
    private static function render_completions(callable $cfgstr, callable $cfgbool): string {
        $heading = $cfgstr('heading', 'Completed Courses');
        $show    = $cfgbool('show', true);
        if (!$show) {
            return '';
        }

        $html = '<div class="eportfolio-section-completions" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 style="margin-bottom:1rem !important;">' . s($heading) . '</h2>';
        }
        $html .= '<p class="text-muted"><em><i class="fa fa-graduation-cap text-success"></i>'
            . ' Your course completions will appear here automatically.</em></p>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Render the social section.
     *
     * @param array $cfg Decoded configdata.
     * @return string Rendered HTML.
     */
    private static function render_social(array $cfg): string {
        $iconmap = [
            'linkedin'  => 'fa-linkedin',
            'github'    => 'fa-github',
            'twitter'   => 'fa-twitter',
            'facebook'  => 'fa-facebook',
            'instagram' => 'fa-instagram',
            'youtube'   => 'fa-youtube',
            'globe'     => 'fa-globe',
        ];

        $links = [];
        if (isset($cfg['links']) && is_array($cfg['links'])) {
            $links = $cfg['links'];
        }

        $haslinks = false;
        foreach ($links as $l) {
            if (!empty($l['url'])) {
                $haslinks = true;
                break;
            }
        }

        $html = '<div class="eportfolio-section-social text-center" style="padding:1.5rem 0 !important;">';
        if (!$haslinks) {
            $html .= '<p class="text-muted"><em>Add your social links in the editor.</em></p>';
        } else {
            $html .= '<div class="d-flex justify-content-center flex-wrap" style="font-size:1.8rem !important;">';
            foreach ($links as $l) {
                if (empty($l['url'])) {
                    continue;
                }
                $platform = $l['platform'] ?? '';
                $icon     = $iconmap[$platform] ?? 'fa-link';
                $html .= '<a href="' . s($l['url']) . '" target="_blank" rel="noopener" title="'
                    . s($platform) . '" style="color:inherit !important; margin:0 0.75rem !important;">'
                    . '<i class="fa ' . $icon . '"></i></a>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render the cta section.
     *
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @return string Rendered HTML.
     */
    private static function render_cta(callable $cfgstr): string {
        $heading    = $cfgstr('heading', '');
        $body       = $cfgstr('body', '');
        $buttontext = $cfgstr('button_text', 'Learn More');
        $buttonurl  = $cfgstr('button_url', '#');
        $bgcolor    = $cfgstr('bg_color', '#0d6efd');

        $html = '<div class="eportfolio-section-cta text-center" style="background-color:' . s($bgcolor)
            . ' !important; color:#fff !important; padding:2.5rem 2rem !important;'
            . ' border-radius:0.5rem !important;">';
        if ($heading !== '') {
            $html .= '<h2 style="color:#fff !important; margin-bottom:0.5rem !important;">'
                . s($heading) . '</h2>';
        }
        if ($body !== '') {
            $html .= '<p style="color:rgba(255,255,255,0.9) !important; margin-bottom:1rem !important;">'
                . s($body) . '</p>';
        }
        if ($buttontext !== '') {
            $html .= '<a href="' . s($buttonurl) . '" class="btn btn-light btn-lg"'
                . ' style="font-weight:600 !important;">' . s($buttontext) . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render the divider section.
     *
     * @param callable $cfgstr Shortcut returning a string config value, with fallback.
     * @return string Rendered HTML.
     */
    private static function render_divider(callable $cfgstr): string {
        $style   = $cfgstr('style', 'line');
        $spacing = $cfgstr('spacing', '2rem');

        if ($style === 'space') {
            return '<div class="eportfolio-section-divider" style="height:' . s($spacing) . ' !important;"></div>';
        }
        return '<div class="eportfolio-section-divider" style="padding:' . s($spacing) . ' 0 !important;">'
            . '<hr style="border-top:1px solid #dee2e6 !important; margin:0 !important;"></div>';
    }

    /**
     * Render the custom section.
     *
     * @param string $content Section raw content.
     * @param array $cfg Decoded configdata.
     * @return string Rendered HTML.
     */
    private static function render_custom(string $content, array $cfg): string {
        if ($content === '') {
            $content = $cfg['html'] ?? '';
        }
        if ($content === '') {
            return '<div class="eportfolio-section-custom text-muted">'
                . '<em>Empty custom section. Click Edit to add HTML content.</em></div>';
        }
        // Same sanitisation as the public renderer — see renderer.php for the
        // threat model the HTMLPurifier policy is closing.
        $clean = format_text($content, FORMAT_HTML, [
            'noclean' => false,
            'context' => \context_system::instance(),
            'allowid' => false,
            'overflowdiv' => false,
        ]);
        return '<div class="eportfolio-section-custom">' . $clean . '</div>';
    }
}
