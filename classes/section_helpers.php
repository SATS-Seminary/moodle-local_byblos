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
 * Shared HTML builders for academic-focused portfolio section types.
 *
 * These are invoked identically from the public renderer (classes/renderer.php)
 * and the editor preview renderer (classes/section_renderer.php) so the editing
 * preview matches the published view exactly.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos;

/**
 * Renders the academic section types (chart, cloud, quote, stats, citations).
 *
 * All methods produce fully self-contained HTML fragments that do not depend on
 * theme CSS to look correct — presentation-critical rules use inline styles and
 * `!important`. Every wrapper receives a `byblos-section-{type}` class so theme
 * authors can still hook further customisations.
 */
class section_helpers {
    /**
     * Parse a hex colour (#rrggbb or #rgb) into an array of 0–255 RGB integers.
     *
     * @param string $hex Input colour string (tolerant of missing '#').
     * @param array  $fallback Fallback triple if parsing fails.
     * @return int[] [r, g, b]
     */
    private static function hex_to_rgb(string $hex, array $fallback = [13, 110, 253]): array {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Return a hex colour string from an RGB triple (each 0–255).
     *
     * @param int $r Red 0–255.
     * @param int $g Green 0–255.
     * @param int $b Blue 0–255.
     * @return string `#rrggbb`.
     */
    private static function rgb_to_hex(int $r, int $g, int $b): string {
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Shift a hex colour by a deterministic amount, clamped to byte range.
     * Used to produce pie/chart slice variants from one base colour.
     *
     * @param string $hex   Base colour.
     * @param int    $index Index of the variant (0-based).
     * @param int    $count Total number of variants we're generating (for spread).
     * @return string Adjusted hex colour.
     */
    public static function variant_color(string $hex, int $index, int $count): string {
        [$r, $g, $b] = self::hex_to_rgb($hex);
        $count = max(1, $count);
        // Spread hues around the base: alternate lighten/darken, widening by index.
        $step  = (int) (70 * ($index / $count));
        $sign  = ($index % 2 === 0) ? 1 : -1;
        $r2 = $r + $sign * $step;
        $g2 = $g - $sign * (int) ($step * 0.6);
        $b2 = $b + $sign * (int) ($step * 0.3);
        return self::rgb_to_hex($r2, $g2, $b2);
    }
    // Chart section.

    /**
     * Render a server-side SVG chart (bar, line, pie, donut).
     *
     * Accepted configdata:
     *  - heading      (string) Section heading.
     *  - type         (string) bar | line | pie | donut.
     *  - color        (string) Base hex colour. Per-item `color` overrides this.
     *  - orientation  (string) Bar only: horizontal | vertical.
     *  - series       (string[]) Optional series names. When >1, each item is
     *                            expected to expose `values: [...]` matching
     *                            the series count. Pie/donut ignore series.
     *  - items        (array)    [{label, value|values[], color?}].
     *  - x_label      (string)   Axis label (bar/line only).
     *  - y_label      (string)   Axis label (bar/line only).
     *  - unit_suffix  (string)   Appended to displayed values (e.g. "%", "hrs").
     *  - caption      (string)   Italic footer (source / context).
     *  - show_values  (bool)     Show numeric values on bars / next to slices.
     *  - sort         (string)   input | asc | desc. Reorders items by the
     *                            first series value when set to asc / desc.
     *
     * @param array $config Decoded configdata.
     * @return string HTML fragment.
     */
    public static function render_chart(array $config): string {
        $heading     = (string) ($config['heading'] ?? '');
        $type        = (string) ($config['type'] ?? 'bar');
        $color       = (string) ($config['color'] ?? '#0d6efd');
        $orientation = (string) ($config['orientation'] ?? 'horizontal');
        $xlabel      = (string) ($config['x_label'] ?? '');
        $ylabel      = (string) ($config['y_label'] ?? '');
        $unit        = (string) ($config['unit_suffix'] ?? '');
        $caption     = (string) ($config['caption'] ?? '');
        $showvalues  = !isset($config['show_values']) || (bool) $config['show_values'];
        $sort        = (string) ($config['sort'] ?? 'input');
        $series      = is_array($config['series'] ?? null) ? array_values($config['series']) : [];
        $rawitems    = is_array($config['items'] ?? null) ? $config['items'] : [];

        if (!in_array($type, ['bar', 'line', 'pie', 'donut'], true)) {
            $type = 'bar';
        }
        if (!in_array($orientation, ['horizontal', 'vertical'], true)) {
            $orientation = 'horizontal';
        }
        if (!in_array($sort, ['input', 'asc', 'desc'], true)) {
            $sort = 'input';
        }

        // Normalise items into a uniform multi-series shape: each item has
        // label, values (array), and optional override color.
        $seriescount = max(1, count($series));
        $items = [];
        foreach ($rawitems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $values = [];
            if (isset($it['values']) && is_array($it['values'])) {
                foreach ($it['values'] as $v) {
                    $values[] = (float) $v;
                }
            } else if (isset($it['value'])) {
                $values[] = (float) $it['value'];
            }
            while (count($values) < $seriescount) {
                $values[] = 0.0;
            }
            $items[] = [
                'label'  => (string) ($it['label'] ?? ''),
                'values' => array_slice($values, 0, $seriescount),
                'color'  => isset($it['color']) ? (string) $it['color'] : '',
            ];
        }

        if ($sort !== 'input' && !empty($items)) {
            usort($items, function ($a, $b) use ($sort) {
                $av = (float) ($a['values'][0] ?? 0);
                $bv = (float) ($b['values'][0] ?? 0);
                if ($av === $bv) {
                    return 0;
                }
                return ($sort === 'asc') ? ($av <=> $bv) : ($bv <=> $av);
            });
        }

        $html = '<div class="byblos-section-chart" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-chart-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>' . get_string('nochart', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $opts = [
            'series'      => $series,
            'unit'        => $unit,
            'xlabel'      => $xlabel,
            'ylabel'      => $ylabel,
            'showvalues'  => $showvalues,
            'orientation' => $orientation,
        ];

        switch ($type) {
            case 'line':
                $html .= self::render_chart_line($items, $color, $opts);
                break;
            case 'pie':
                $html .= self::render_chart_pie($items, $color, false, $unit);
                break;
            case 'donut':
                $html .= self::render_chart_pie($items, $color, true, $unit);
                break;
            case 'bar':
            default:
                $html .= self::render_chart_bar($items, $color, $opts);
                break;
        }

        if ($caption !== '') {
            $html .= '<p class="byblos-chart-caption text-muted"'
                . ' style="font-size:0.85rem !important; margin-top:0.5rem !important; font-style:italic !important;">'
                . s($caption) . '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Format a numeric chart value with the user-supplied unit suffix.
     * Drops the decimal portion when the value is integral.
     *
     * @param float $val Value.
     * @param string $unit Unit suffix.
     * @return string
     */
    private static function chart_format_value(float $val, string $unit): string {
        $str = (abs($val - round($val)) < 0.0001) ? (string) (int) round($val) : (string) $val;
        return $str . $unit;
    }

    /**
     * SVG bar chart. Supports single or multi-series, horizontal or vertical
     * orientation, value labels on each bar, axis labels, and per-item colour
     * overrides.
     *
     * @param array  $items Normalised items: [{label, values[], color}, ...].
     * @param string $color Base bar colour (hex).
     * @param array  $opts  Layout / labelling options.
     *                      keys: series[], unit, xlabel, ylabel,
     *                            showvalues (bool), orientation.
     * @return string SVG wrapped in a div.
     */
    private static function render_chart_bar(array $items, string $color, array $opts): string {
        $series      = $opts['series'] ?? [];
        $seriescount = max(1, count($series));
        $unit        = (string) ($opts['unit'] ?? '');
        $xlabel      = (string) ($opts['xlabel'] ?? '');
        $ylabel      = (string) ($opts['ylabel'] ?? '');
        $showvalues  = !empty($opts['showvalues']);
        $orientation = (string) ($opts['orientation'] ?? 'horizontal');

        // Compute the global maximum across every value in every series for scaling.
        $max = 0.0;
        foreach ($items as $it) {
            foreach (($it['values'] ?? []) as $v) {
                if ((float) $v > $max) {
                    $max = (float) $v;
                }
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }

        $count = count($items);

        if ($orientation === 'vertical') {
            // Vertical (column) bars.
            $chartw  = 720;
            $padleft = $ylabel !== '' ? 56 : 36;
            $padr    = 20;
            $padtop  = ($showvalues ? 24 : 12);
            $padbot  = 56 + ($xlabel !== '' ? 18 : 0);
            $ploth   = 240;
            $h       = $padtop + $ploth + $padbot;
            $plotw   = $chartw - $padleft - $padr;
            $groupw  = $plotw / max(1, $count);
            $gappx   = 6;
            $barw    = max(2, ($groupw - $gappx * ($seriescount + 1)) / $seriescount);

            $svg = self::chart_svg_open($chartw, $h);
            $svg .= self::chart_axes($padleft, $padtop, $plotw, $ploth);
            $svg .= self::chart_y_grid($padleft, $padtop, $plotw, $ploth, $max, $unit);

            foreach (array_values($items) as $i => $it) {
                $label    = (string) ($it['label'] ?? '');
                $values   = (array) ($it['values'] ?? []);
                $override = (string) ($it['color'] ?? '');
                $gx       = $padleft + $i * $groupw;
                foreach ($values as $s => $val) {
                    $val = (float) $val;
                    $bh  = ($val / $max) * $ploth;
                    $bx  = $gx + $gappx + $s * ($barw + $gappx);
                    $by  = $padtop + $ploth - $bh;
                    $fill = $override !== '' && $seriescount === 1
                        ? $override
                        : self::variant_color($color, $s, $seriescount);
                    $svg .= '<rect x="' . round($bx, 2) . '" y="' . round($by, 2)
                        . '" width="' . round($barw, 2) . '" height="' . round($bh, 2)
                        . '" rx="2" ry="2" fill="' . s($fill) . '"></rect>';
                    if ($showvalues) {
                        $svg .= '<text x="' . round($bx + $barw / 2, 2) . '" y="' . round($by - 4, 2)
                            . '" text-anchor="middle" font-size="11" fill="#333">'
                            . s(self::chart_format_value($val, $unit)) . '</text>';
                    }
                }
                // Category label below the group.
                $svg .= '<text x="' . round($gx + $groupw / 2, 2) . '" y="' . ($padtop + $ploth + 16)
                    . '" text-anchor="middle" font-size="12" fill="#444">' . s($label) . '</text>';
            }

            $svg .= self::chart_axis_labels($xlabel, $ylabel, $chartw, $h, $padleft, $padtop, $ploth);
            $svg .= self::chart_legend($series, $color, $padleft, $h - 12, $seriescount);
            $svg .= '</svg>';
            return '<div class="byblos-chart-canvas">' . $svg . '</div>';
        }

        // Horizontal bars (default).
        $rowh    = 34;
        $labelw  = 150;
        $chartw  = 720;
        $padtop  = 16;
        $padbot  = ($xlabel !== '' ? 36 : 12) + ($seriescount > 1 ? 28 : 0);
        $padr    = 60;
        $rowspacing = 2;

        // Each item occupies one "row group". When there are multiple series
        // we stack them inside the row group with a small gap.
        $groupheight = $seriescount * $rowh + $rowspacing;
        $h           = $padtop + $groupheight * $count + $padbot;
        $barmax      = $chartw - $labelw - $padr;

        $svg = self::chart_svg_open($chartw, $h);

        foreach (array_values($items) as $i => $it) {
            $label    = (string) ($it['label'] ?? '');
            $values   = (array) ($it['values'] ?? []);
            $override = (string) ($it['color'] ?? '');
            $groupy   = $padtop + $i * $groupheight;

            // Category label centred on the group.
            $svg .= '<text x="' . ($labelw - 8) . '" y="' . round($groupy + ($groupheight / 2) + 4, 2)
                . '" text-anchor="end" font-size="13" fill="#333">' . s($label) . '</text>';

            foreach ($values as $s => $val) {
                $val  = (float) $val;
                $barw = (int) round(($val / $max) * $barmax);
                $by   = $groupy + $s * $rowh + 6;
                $fill = $override !== '' && $seriescount === 1
                    ? $override
                    : self::variant_color($color, $s, $seriescount);
                $svg .= '<rect x="' . $labelw . '" y="' . $by . '" width="' . $barw
                    . '" height="' . ($rowh - 14) . '" rx="3" ry="3" fill="' . s($fill) . '"></rect>';
                if ($showvalues) {
                    $svg .= '<text x="' . ($labelw + $barw + 6) . '" y="' . ($by + 12)
                        . '" font-size="12" fill="#666">'
                        . s(self::chart_format_value($val, $unit)) . '</text>';
                }
            }
        }

        if ($xlabel !== '') {
            $svg .= '<text x="' . round($labelw + $barmax / 2, 2) . '" y="' . ($h - 14)
                . '" text-anchor="middle" font-size="12" fill="#555" font-style="italic">'
                . s($xlabel) . '</text>';
        }
        if ($ylabel !== '') {
            // Vertical text along the left edge.
            $cy = $padtop + ($groupheight * $count) / 2;
            $svg .= '<text x="14" y="' . round($cy, 2)
                . '" text-anchor="middle" font-size="12" fill="#555" font-style="italic"'
                . ' transform="rotate(-90 14 ' . round($cy, 2) . ')">' . s($ylabel) . '</text>';
        }
        if ($seriescount > 1) {
            $svg .= self::chart_legend($series, $color, $labelw, $h - 14, $seriescount);
        }
        $svg .= '</svg>';
        return '<div class="byblos-chart-canvas">' . $svg . '</div>';
    }

    /**
     * Open the chart's SVG wrapper with standard sizing.
     *
     * @param int $w Viewport width.
     * @param int $h Viewport height.
     * @return string Opening <svg> tag.
     */
    private static function chart_svg_open(int $w, int $h): string {
        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid meet"'
            . ' style="width:100% !important; max-width:100% !important; height:auto !important;"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img">';
    }

    /**
     * Draw the X and Y axis lines for vertical charts.
     *
     * @param int $padleft Left padding.
     * @param int $padtop Top padding.
     * @param float $plotw Plot area width.
     * @param float $ploth Plot area height.
     * @return string SVG fragment.
     */
    private static function chart_axes(int $padleft, int $padtop, float $plotw, float $ploth): string {
        $bottomy = $padtop + $ploth;
        return '<line x1="' . $padleft . '" y1="' . $bottomy . '" x2="' . round($padleft + $plotw, 2)
            . '" y2="' . $bottomy . '" stroke="#ccc" stroke-width="1"/>'
            . '<line x1="' . $padleft . '" y1="' . $padtop . '" x2="' . $padleft
            . '" y2="' . $bottomy . '" stroke="#ccc" stroke-width="1"/>';
    }

    /**
     * Draw four horizontal gridlines with tick labels on the y-axis.
     *
     * @param int $padleft Left padding.
     * @param int $padtop Top padding.
     * @param float $plotw Plot area width.
     * @param float $ploth Plot area height.
     * @param float $max Max value across the plot.
     * @param string $unit Unit suffix appended to tick labels.
     * @return string SVG fragment.
     */
    private static function chart_y_grid(
        int $padleft,
        int $padtop,
        float $plotw,
        float $ploth,
        float $max,
        string $unit
    ): string {
        $out = '';
        for ($i = 1; $i <= 4; $i++) {
            $y = $padtop + ($ploth * (4 - $i) / 4);
            $v = $max * ($i / 4);
            $out .= '<line x1="' . $padleft . '" y1="' . round($y, 2) . '" x2="'
                . round($padleft + $plotw, 2) . '" y2="' . round($y, 2)
                . '" stroke="#eee" stroke-width="1" stroke-dasharray="3,3"/>';
            $out .= '<text x="' . ($padleft - 6) . '" y="' . round($y + 4, 2)
                . '" text-anchor="end" font-size="10" fill="#888">'
                . s(self::chart_format_value($v, $unit)) . '</text>';
        }
        return $out;
    }

    /**
     * Render axis labels (X centred under the plot, Y rotated on the left edge).
     *
     * @param string $xlabel Bottom-axis label.
     * @param string $ylabel Side-axis label.
     * @param int $chartw Full chart width.
     * @param int $h Full chart height.
     * @param int $padleft Left padding.
     * @param int $padtop Top padding.
     * @param float $ploth Plot height.
     * @return string SVG fragment.
     */
    private static function chart_axis_labels(
        string $xlabel,
        string $ylabel,
        int $chartw,
        int $h,
        int $padleft,
        int $padtop,
        float $ploth
    ): string {
        $out = '';
        if ($xlabel !== '') {
            $out .= '<text x="' . round(($chartw + $padleft) / 2, 2) . '" y="' . ($h - 28)
                . '" text-anchor="middle" font-size="12" fill="#555" font-style="italic">'
                . s($xlabel) . '</text>';
        }
        if ($ylabel !== '') {
            $cy = $padtop + $ploth / 2;
            $out .= '<text x="14" y="' . round($cy, 2)
                . '" text-anchor="middle" font-size="12" fill="#555" font-style="italic"'
                . ' transform="rotate(-90 14 ' . round($cy, 2) . ')">' . s($ylabel) . '</text>';
        }
        return $out;
    }

    /**
     * Inline horizontal legend for multi-series charts.
     *
     * @param array $series Series names.
     * @param string $color Base colour from which per-series variants are derived.
     * @param int $x Starting x coordinate.
     * @param int $y Baseline y coordinate.
     * @param int $count Total series count (for variant_color spread).
     * @return string SVG fragment.
     */
    private static function chart_legend(array $series, string $color, int $x, int $y, int $count): string {
        $out = '';
        $cursor = $x;
        foreach (array_values($series) as $s => $name) {
            $fill = self::variant_color($color, $s, max(1, $count));
            $out .= '<rect x="' . $cursor . '" y="' . ($y - 10) . '" width="12" height="12" rx="2" ry="2"'
                . ' fill="' . s($fill) . '"></rect>';
            $out .= '<text x="' . ($cursor + 18) . '" y="' . $y . '" font-size="11" fill="#333">'
                . s($name) . '</text>';
            // Approximate label width: 7px per char + padding.
            $cursor += 28 + 7 * mb_strlen($name);
        }
        return $out;
    }

    /**
     * SVG line chart with data points. Supports multi-series (one polyline
     * per series), axis labels, unit-suffixed value labels, and a y-axis grid.
     *
     * @param array  $items Normalised items: [{label, values[], color}, ...].
     * @param string $color Base line colour. Series 2..N derive from this.
     * @param array  $opts  Layout / labelling options (see render_chart_bar).
     * @return string SVG wrapped in a div.
     */
    private static function render_chart_line(array $items, string $color, array $opts): string {
        $series      = $opts['series'] ?? [];
        $seriescount = max(1, count($series));
        $unit        = (string) ($opts['unit'] ?? '');
        $xlabel      = (string) ($opts['xlabel'] ?? '');
        $ylabel      = (string) ($opts['ylabel'] ?? '');
        $showvalues  = !empty($opts['showvalues']);

        $max = 0.0;
        foreach ($items as $it) {
            foreach (($it['values'] ?? []) as $v) {
                if ((float) $v > $max) {
                    $max = (float) $v;
                }
            }
        }
        if ($max <= 0) {
            $max = 1.0;
        }

        $w       = 720;
        $padleft = $ylabel !== '' ? 56 : 40;
        $padr    = 20;
        $padtop  = 20;
        $padbot  = 48 + ($xlabel !== '' ? 18 : 0) + ($seriescount > 1 ? 24 : 0);
        $ploth   = 240;
        $h       = $padtop + $ploth + $padbot;
        $plotw   = $w - $padleft - $padr;
        $count   = max(1, count($items));

        $svg = self::chart_svg_open($w, $h);
        $svg .= self::chart_axes($padleft, $padtop, $plotw, $ploth);
        $svg .= self::chart_y_grid($padleft, $padtop, $plotw, $ploth, $max, $unit);

        // Draw one polyline + dot set per series.
        for ($s = 0; $s < $seriescount; $s++) {
            $linecolor = self::variant_color($color, $s, $seriescount);
            $points = [];
            foreach (array_values($items) as $i => $it) {
                $val = (float) ($it['values'][$s] ?? 0);
                $x   = $padleft + ($count > 1 ? ($i / ($count - 1)) * $plotw : $plotw / 2);
                $y   = $padtop + $ploth - ($val / $max) * $ploth;
                $points[] = [$x, $y, $val];
            }
            $polyline = '';
            foreach ($points as $p) {
                $polyline .= round($p[0], 2) . ',' . round($p[1], 2) . ' ';
            }
            $svg .= '<polyline points="' . trim($polyline) . '" fill="none" stroke="'
                . s($linecolor) . '" stroke-width="2.5" stroke-linecap="round"'
                . ' stroke-linejoin="round"/>';
            foreach ($points as $p) {
                $svg .= '<circle cx="' . round($p[0], 2) . '" cy="' . round($p[1], 2)
                    . '" r="4" fill="' . s($linecolor) . '"></circle>';
                if ($showvalues) {
                    $svg .= '<text x="' . round($p[0], 2) . '" y="' . (round($p[1], 2) - 8)
                        . '" text-anchor="middle" font-size="11" fill="#333">'
                        . s(self::chart_format_value($p[2], $unit)) . '</text>';
                }
            }
        }

        // Category labels under the x-axis (one per item).
        foreach (array_values($items) as $i => $it) {
            $x = $padleft + ($count > 1 ? ($i / ($count - 1)) * $plotw : $plotw / 2);
            $svg .= '<text x="' . round($x, 2) . '" y="' . ($padtop + $ploth + 18)
                . '" text-anchor="middle" font-size="11" fill="#666">'
                . s((string) ($it['label'] ?? '')) . '</text>';
        }

        $svg .= self::chart_axis_labels($xlabel, $ylabel, $w, $h, $padleft, $padtop, $ploth);
        if ($seriescount > 1) {
            $svg .= self::chart_legend($series, $color, $padleft, $h - 14, $seriescount);
        }
        $svg .= '</svg>';
        return '<div class="byblos-chart-canvas">' . $svg . '</div>';
    }

    /**
     * SVG pie or donut chart with an adjacent legend. Slices are derived from
     * the first series of each item; multi-series isn't meaningful for pie.
     *
     * @param array  $items Normalised items: [{label, values[], color}, ...].
     * @param string $color Base slice colour (others derived).
     * @param bool   $donut If true, render as donut (hollow centre).
     * @param string $unit  Unit suffix appended to the legend value (e.g. "%").
     * @return string SVG wrapped in a div.
     */
    private static function render_chart_pie(array $items, string $color, bool $donut, string $unit = ''): string {
        $total = 0.0;
        foreach ($items as $it) {
            $total += max(0, (float) ($it['values'][0] ?? 0));
        }
        if ($total <= 0) {
            $total = 1.0;
        }

        $w    = 480;
        $h    = 280;
        $cx   = 140;
        $cy   = 140;
        $r    = 120;
        $rin  = $donut ? 60 : 0;
        $count = count($items);

        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid meet"'
            . ' style="width:100% !important; max-width:100% !important; height:auto !important;"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img">';

        $angle = -M_PI_2; // Start at top.
        $idx = 0;
        foreach ($items as $it) {
            $val = max(0, (float) ($it['values'][0] ?? 0));
            $override = (string) ($it['color'] ?? '');
            if ($val <= 0) {
                $idx++;
                continue;
            }
            $slice = ($val / $total) * 2 * M_PI;
            $a1 = $angle;
            $a2 = $angle + $slice;
            $largearc = $slice > M_PI ? 1 : 0;

            $x1 = $cx + $r * cos($a1);
            $y1 = $cy + $r * sin($a1);
            $x2 = $cx + $r * cos($a2);
            $y2 = $cy + $r * sin($a2);

            $slicecolor = $override !== '' ? $override : self::variant_color($color, $idx, max(1, $count));

            if ($donut) {
                $xi1 = $cx + $rin * cos($a1);
                $yi1 = $cy + $rin * sin($a1);
                $xi2 = $cx + $rin * cos($a2);
                $yi2 = $cy + $rin * sin($a2);
                $path = 'M ' . round($x1, 2) . ' ' . round($y1, 2)
                    . ' A ' . $r . ' ' . $r . ' 0 ' . $largearc . ' 1 ' . round($x2, 2) . ' ' . round($y2, 2)
                    . ' L ' . round($xi2, 2) . ' ' . round($yi2, 2)
                    . ' A ' . $rin . ' ' . $rin . ' 0 ' . $largearc . ' 0 ' . round($xi1, 2) . ' ' . round($yi1, 2)
                    . ' Z';
            } else {
                $path = 'M ' . $cx . ' ' . $cy
                    . ' L ' . round($x1, 2) . ' ' . round($y1, 2)
                    . ' A ' . $r . ' ' . $r . ' 0 ' . $largearc . ' 1 ' . round($x2, 2) . ' ' . round($y2, 2)
                    . ' Z';
            }

            $svg .= '<path d="' . $path . '" fill="' . s($slicecolor)
                . '" stroke="#fff" stroke-width="2"></path>';

            $angle = $a2;
            $idx++;
        }

        // Legend on the right.
        $lx = 290;
        $ly = 30;
        $idx = 0;
        foreach ($items as $it) {
            $label    = (string) ($it['label'] ?? '');
            $val      = (float) ($it['values'][0] ?? 0);
            $pct      = round(($val / $total) * 100, 1);
            $override = (string) ($it['color'] ?? '');
            $slicecolor = $override !== '' ? $override : self::variant_color($color, $idx, max(1, $count));

            $svg .= '<rect x="' . $lx . '" y="' . $ly . '" width="14" height="14" rx="2" ry="2"'
                . ' fill="' . s($slicecolor) . '"></rect>';
            // Show "label — value[unit] (pct%)" so the actual magnitude is visible
            // alongside the percentage share.
            $tail = ' — ' . s(self::chart_format_value($val, $unit)) . ' (' . s((string) $pct) . '%)';
            $svg .= '<text x="' . ($lx + 22) . '" y="' . ($ly + 12) . '" font-size="12"'
                . ' fill="#333">' . s($label) . $tail . '</text>';
            $ly += 22;
            $idx++;
        }

        $svg .= '</svg>';
        return '<div class="byblos-chart-canvas">' . $svg . '</div>';
    }
    // Cloud section.

    /**
     * Render a word cloud as a flex-wrapped span list with deterministic per-word sizing.
     *
     * @param array $config Decoded configdata: `heading`, `color`, `items[{text,weight}]`.
     * @return string HTML fragment.
     */
    public static function render_cloud(array $config): string {
        $heading = (string) ($config['heading'] ?? '');
        $color   = (string) ($config['color'] ?? '#0d6efd');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        $html = '<div class="byblos-section-cloud" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-cloud-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>' . get_string('nocloud', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<div class="byblos-cloud-wrap" style="display:flex !important; flex-wrap:wrap !important;'
            . ' justify-content:center !important; align-items:center !important; gap:0.5rem 0.9rem !important;'
            . ' padding:0.5rem !important;">';

        $count = count($items);
        $idx   = 0;
        foreach ($items as $it) {
            $text   = (string) ($it['text'] ?? '');
            $weight = (int) ($it['weight'] ?? 1);
            $weight = max(1, min(10, $weight));
            if ($text === '') {
                continue;
            }

            $fontsize = 0.75 + ($weight * 0.18); // 0.93rem to 2.55rem.
            $opacity  = 0.6 + ($weight * 0.04);  // 0.64 to 1.0.
            $wordcolor = self::variant_color($color, $idx, max(6, $count));

            $html .= '<span class="byblos-cloud-word" style="display:inline-block !important;'
                . ' font-size:' . number_format($fontsize, 2) . 'rem !important;'
                . ' font-weight:' . (500 + $weight * 20) . ' !important;'
                . ' line-height:1.1 !important;'
                . ' color:' . s($wordcolor) . ' !important;'
                . ' opacity:' . number_format(min(1.0, $opacity), 2) . ' !important;">'
                . s($text) . '</span>';

            $idx++;
        }
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
    // Quote section.

    /**
     * Render a pull-quote with attribution (optionally linked to a source URL).
     *
     * @param array $config Decoded configdata: `body` (rich), `attribution`, `source`.
     * @return string HTML fragment.
     */
    public static function render_quote(array $config): string {
        $body        = (string) ($config['body'] ?? '');
        $attribution = (string) ($config['attribution'] ?? '');
        $source      = (string) ($config['source'] ?? '');

        $html = '<div class="byblos-section-quote" style="padding:2rem 1rem !important;">';
        $html .= '<blockquote class="byblos-quote-block" style="position:relative !important;'
            . ' max-width:760px !important; margin:0 auto !important; padding:1rem 2.5rem !important;'
            . ' text-align:center !important; border:none !important; font-style:italic !important;">';

        // Decorative opening quote mark.
        $html .= '<span aria-hidden="true" class="byblos-quote-mark" style="position:absolute !important;'
            . ' top:-0.2em !important; left:0 !important; font-size:4rem !important; line-height:1 !important;'
            . ' color:rgba(0,0,0,0.15) !important; font-family:Georgia,serif !important;">&ldquo;</span>';

        if ($body !== '') {
            $html .= '<div class="byblos-quote-body" style="font-size:1.25rem !important;'
                . ' line-height:1.55 !important; color:#333 !important; margin-bottom:1rem !important;">'
                . self::clean_body($body) . '</div>';
        } else {
            $html .= '<div class="byblos-quote-body text-muted"><em>'
                . get_string('emptyquote', 'local_byblos') . '</em></div>';
        }

        if ($attribution !== '') {
            $attrhtml = s($attribution);
            $cleansource = clean_param($source, PARAM_URL);
            if ($cleansource !== '') {
                $attrhtml = '<a href="' . s($cleansource) . '" target="_blank" rel="noopener"'
                    . ' style="color:inherit !important; text-decoration:underline !important;">'
                    . $attrhtml . '</a>';
            }
            $html .= '<footer class="byblos-quote-attribution" style="font-style:normal !important;'
                . ' font-size:0.95rem !important; color:#777 !important;">&mdash; ' . $attrhtml . '</footer>';
        }

        $html .= '</blockquote>';
        $html .= '</div>';
        return $html;
    }
    // Stats section.

    /**
     * Render a row of 2–4 big-number stat cards.
     *
     * @param array  $config   Decoded configdata: `heading`, `items[{number,label,description}]`.
     * @param string $themekey Page theme key (for accent colour on numbers).
     * @return string HTML fragment.
     */
    public static function render_stats(array $config, string $themekey = ''): string {
        $heading = (string) ($config['heading'] ?? '');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];
        if (count($items) > 4) {
            $items = array_slice($items, 0, 4);
        }

        $accent = $themekey !== ''
            ? theme::get_accent_color($themekey)
            : '#0d6efd';

        $html = '<div class="byblos-section-stats" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-stats-heading" style="margin-bottom:1rem !important;'
                . ' text-align:center !important;">' . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted text-center"><em>'
                . get_string('nostats', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<div class="byblos-stats-grid" style="display:grid !important;'
            . ' grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)) !important;'
            . ' gap:1rem !important;">';

        foreach ($items as $it) {
            $number = (string) ($it['number'] ?? '');
            $label  = (string) ($it['label'] ?? '');
            $desc   = (string) ($it['description'] ?? '');

            $html .= '<div class="byblos-stats-card" style="background:#fff !important;'
                . ' border:1px solid rgba(0,0,0,0.08) !important; border-radius:0.5rem !important;'
                . ' padding:1.5rem 1rem !important; text-align:center !important;'
                . ' box-shadow:0 1px 3px rgba(0,0,0,0.04) !important;">';
            $html .= '<div class="byblos-stats-number" style="font-size:2.5rem !important;'
                . ' font-weight:700 !important; line-height:1 !important;'
                . ' color:' . s($accent) . ' !important; margin-bottom:0.5rem !important;">'
                . s($number) . '</div>';
            if ($label !== '') {
                $html .= '<div class="byblos-stats-label" style="font-size:1rem !important;'
                    . ' font-weight:600 !important; color:#333 !important;'
                    . ' margin-bottom:0.25rem !important;">' . s($label) . '</div>';
            }
            if ($desc !== '') {
                $html .= '<div class="byblos-stats-desc" style="font-size:0.85rem !important;'
                    . ' color:#777 !important;">' . s($desc) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }
    // Citations section.

    /**
     * Render a numbered academic bibliography.
     *
     * @param array $config Decoded configdata: `heading`, `style`, `items[{text,url}]`.
     * @return string HTML fragment.
     */
    public static function render_citations(array $config): string {
        $heading = (string) ($config['heading'] ?? get_string('citations_default_heading', 'local_byblos'));
        $style   = (string) ($config['style'] ?? 'plain');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        if (!in_array($style, ['apa', 'mla', 'chicago', 'plain'], true)) {
            $style = 'plain';
        }

        // Per-style spacing and indent tweaks — kept light.
        $stylecss = [
            'apa'     => 'padding-left:2.5rem !important; text-indent:-1.5rem !important; margin-bottom:0.9rem !important;',
            'mla'     => 'padding-left:2.5rem !important; text-indent:-1.5rem !important; margin-bottom:0.6rem !important;',
            'chicago' => 'padding-left:2rem !important; margin-bottom:0.75rem !important;',
            'plain'   => 'padding-left:1rem !important; margin-bottom:0.5rem !important;',
        ];
        $itemstyle = $stylecss[$style];

        $html = '<div class="byblos-section-citations byblos-citations-style-' . s($style)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-citations-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>'
                . get_string('nocitations', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= '<ol class="byblos-citations-list" style="list-style:decimal !important;'
            . ' padding-left:1.5rem !important; font-size:0.95rem !important;'
            . ' line-height:1.5 !important; color:#333 !important;">';

        foreach ($items as $it) {
            $text = (string) ($it['text'] ?? '');
            $url  = (string) ($it['url'] ?? '');
            if ($text === '') {
                continue;
            }
            $body = s($text);
            $cleanurl = clean_param($url, PARAM_URL);
            if ($cleanurl !== '') {
                $body = '<a href="' . s($cleanurl) . '" target="_blank" rel="noopener"'
                    . ' style="color:inherit !important; text-decoration:underline !important;">'
                    . $body . '</a>';
            }
            $html .= '<li style="' . $itemstyle . '">' . $body . '</li>';
        }

        $html .= '</ol>';
        $html .= '</div>';
        return $html;
    }

    /**
     * FontAwesome icon class matching a file's extension. Returns a generic
     * file icon when nothing matches.
     *
     * @param string $url      The file URL (used to sniff the extension).
     * @param string $typehint Optional explicit type key overriding extension sniff.
     * @return string
     */
    private static function file_icon_class(string $url, string $typehint = ''): string {
        $typemap = [
            'pdf'   => 'fa-file-pdf-o',
            'doc'   => 'fa-file-word-o',
            'docx'  => 'fa-file-word-o',
            'word'  => 'fa-file-word-o',
            'xls'   => 'fa-file-excel-o',
            'xlsx'  => 'fa-file-excel-o',
            'csv'   => 'fa-file-excel-o',
            'excel' => 'fa-file-excel-o',
            'ppt'   => 'fa-file-powerpoint-o',
            'pptx'  => 'fa-file-powerpoint-o',
            'key'   => 'fa-file-powerpoint-o',
            'slides' => 'fa-file-powerpoint-o',
            'jpg'   => 'fa-file-image-o',
            'jpeg'  => 'fa-file-image-o',
            'png'   => 'fa-file-image-o',
            'gif'   => 'fa-file-image-o',
            'svg'   => 'fa-file-image-o',
            'webp'  => 'fa-file-image-o',
            'image' => 'fa-file-image-o',
            'mp4'   => 'fa-file-video-o',
            'mov'   => 'fa-file-video-o',
            'avi'   => 'fa-file-video-o',
            'webm'  => 'fa-file-video-o',
            'video' => 'fa-file-video-o',
            'mp3'   => 'fa-file-audio-o',
            'wav'   => 'fa-file-audio-o',
            'm4a'   => 'fa-file-audio-o',
            'audio' => 'fa-file-audio-o',
            'zip'   => 'fa-file-archive-o',
            'rar'   => 'fa-file-archive-o',
            '7z'    => 'fa-file-archive-o',
            'tar'   => 'fa-file-archive-o',
            'gz'    => 'fa-file-archive-o',
            'archive' => 'fa-file-archive-o',
            'txt'   => 'fa-file-text-o',
            'md'    => 'fa-file-text-o',
            'rtf'   => 'fa-file-text-o',
            'text'  => 'fa-file-text-o',
            'html'  => 'fa-file-code-o',
            'htm'   => 'fa-file-code-o',
            'js'    => 'fa-file-code-o',
            'php'   => 'fa-file-code-o',
            'py'    => 'fa-file-code-o',
            'css'   => 'fa-file-code-o',
            'code'  => 'fa-file-code-o',
        ];

        $hint = strtolower(trim($typehint));
        if ($hint !== '' && isset($typemap[$hint])) {
            return $typemap[$hint];
        }

        // Extension sniff — strip query/fragment, then take after the final dot.
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (preg_match('/\.([a-z0-9]{1,8})$/i', $path, $m)) {
            $ext = strtolower($m[1]);
            if (isset($typemap[$ext])) {
                return $typemap[$ext];
            }
        }
        return 'fa-file-o';
    }

    /**
     * Is this URL likely an image we can render inline as a thumbnail?
     *
     * @param string $url
     * @param string $typehint
     * @return bool
     */
    private static function file_is_image(string $url, string $typehint = ''): bool {
        if (strtolower(trim($typehint)) === 'image') {
            return true;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        return (bool) preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $path);
    }

    /**
     * Derive a display title from the URL's filename if no title was provided.
     *
     * @param string $url
     * @return string
     */
    private static function file_title_from_url(string $url): string {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $base = basename($path);
        return $base !== '' ? $base : $url;
    }

    /**
     * Render a configurable file list: list / tile / thumbs display modes.
     *
     * @param array $config Decoded configdata: `heading`, `display` (list|tile|thumbs),
     *                     `items[{url,title,description,type}]`.
     * @return string HTML fragment.
     */
    public static function render_files(array $config): string {
        $heading = (string) ($config['heading'] ?? get_string('files_default_heading', 'local_byblos'));
        $display = (string) ($config['display'] ?? 'list');
        $items   = is_array($config['items'] ?? null) ? $config['items'] : [];

        if (!in_array($display, ['list', 'tile', 'thumbs'], true)) {
            $display = 'list';
        }

        $html = '<div class="byblos-section-files byblos-files-display-' . s($display)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-files-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($items)) {
            $html .= '<p class="text-muted"><em>'
                . get_string('nofiles', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        $html .= match ($display) {
            'tile'   => self::render_files_tile($items),
            'thumbs' => self::render_files_thumbs($items),
            default  => self::render_files_list($items),
        };

        $html .= '</div>';
        return $html;
    }

    /**
     * Compact vertical list — one row per file with icon, title, description.
     *
     * @param array $items
     * @return string
     */
    private static function render_files_list(array $items): string {
        $html = '<ul class="byblos-files-list" style="list-style:none !important;'
            . ' padding:0 !important; margin:0 !important;">';
        foreach ($items as $it) {
            $url   = (string) ($it['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $title = (string) ($it['title'] ?? '');
            if ($title === '') {
                $title = self::file_title_from_url($url);
            }
            $desc  = (string) ($it['description'] ?? '');
            $icon  = self::file_icon_class($url, (string) ($it['type'] ?? ''));

            $html .= '<li class="byblos-files-list-item" style="display:flex !important;'
                . ' align-items:flex-start !important; gap:0.75rem !important;'
                . ' padding:0.6rem 0 !important; border-bottom:1px solid #eef1f4 !important;">';
            $html .= '<i class="fa ' . s($icon) . '" aria-hidden="true"'
                . ' style="font-size:1.6rem !important; color:#6c757d !important;'
                . ' width:1.8rem !important; text-align:center !important; flex-shrink:0 !important;'
                . ' line-height:1.2 !important;"></i>';
            $html .= '<div style="flex:1 !important; min-width:0 !important;">';
            $html .= '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                . ' style="font-weight:600 !important; text-decoration:none !important;'
                . ' color:#0d6efd !important;">' . s($title) . '</a>';
            if ($desc !== '') {
                $html .= '<div class="byblos-files-item-desc" style="font-size:0.85rem !important;'
                    . ' color:#666 !important; margin-top:0.1rem !important;">' . s($desc) . '</div>';
            }
            $html .= '</div>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Card grid — each file as a card with prominent icon, title, description.
     *
     * @param array $items
     * @return string
     */
    private static function render_files_tile(array $items): string {
        $html = '<div class="byblos-files-tiles row" style="margin:0 !important;">';
        foreach ($items as $it) {
            $url   = (string) ($it['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $title = (string) ($it['title'] ?? '');
            if ($title === '') {
                $title = self::file_title_from_url($url);
            }
            $desc  = (string) ($it['description'] ?? '');
            $icon  = self::file_icon_class($url, (string) ($it['type'] ?? ''));

            $html .= '<div class="col-md-4 col-sm-6 mb-3" style="padding:0.5rem !important;">';
            $html .= '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                . ' class="byblos-files-tile-card"'
                . ' style="display:block !important; text-decoration:none !important; color:inherit !important;'
                . ' background:#ffffff !important; border:1px solid #e9ecef !important;'
                . ' border-radius:0.5rem !important; padding:1.25rem !important;'
                . ' transition:transform 0.15s, box-shadow 0.15s !important; height:100% !important;">';
            $html .= '<i class="fa ' . s($icon) . '" aria-hidden="true"'
                . ' style="display:block !important; font-size:2.5rem !important;'
                . ' color:#0d6efd !important; margin-bottom:0.6rem !important;"></i>';
            $html .= '<div class="byblos-files-tile-title" style="font-weight:600 !important;'
                . ' color:#212529 !important; font-size:0.95rem !important;'
                . ' word-break:break-word !important;">' . s($title) . '</div>';
            if ($desc !== '') {
                $html .= '<div class="byblos-files-tile-desc" style="font-size:0.8rem !important;'
                    . ' color:#666 !important; margin-top:0.35rem !important;">' . s($desc) . '</div>';
            }
            $html .= '</a></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Thumbnail grid — image previews inline, others fall back to big icon.
     *
     * @param array $items
     * @return string
     */
    private static function render_files_thumbs(array $items): string {
        $html = '<div class="byblos-files-thumbs row" style="margin:0 !important;">';
        foreach ($items as $it) {
            $url   = (string) ($it['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $title = (string) ($it['title'] ?? '');
            if ($title === '') {
                $title = self::file_title_from_url($url);
            }
            $desc  = (string) ($it['description'] ?? '');
            $type  = (string) ($it['type'] ?? '');
            $isimage = self::file_is_image($url, $type);
            $icon  = self::file_icon_class($url, $type);

            $html .= '<div class="col-md-3 col-sm-4 col-6 mb-3" style="padding:0.35rem !important;">';
            $html .= '<a href="' . s($url) . '" target="_blank" rel="noopener"'
                . ' class="byblos-files-thumb-card"'
                . ' style="display:block !important; text-decoration:none !important; color:inherit !important;'
                . ' background:#ffffff !important; border:1px solid #e9ecef !important;'
                . ' border-radius:0.5rem !important; overflow:hidden !important;'
                . ' transition:transform 0.15s, box-shadow 0.15s !important;">';

            // Preview area: image or big icon.
            $html .= '<div class="byblos-files-thumb-media"'
                . ' style="aspect-ratio:4/3 !important; width:100% !important;'
                . ' display:flex !important; align-items:center !important; justify-content:center !important;'
                . ' background:#f8f9fa !important; overflow:hidden !important;">';
            if ($isimage) {
                $html .= '<img src="' . s($url) . '" alt="' . s($title) . '"'
                    . ' style="width:100% !important; height:100% !important; object-fit:cover !important;">';
            } else {
                $html .= '<i class="fa ' . s($icon) . '" aria-hidden="true"'
                    . ' style="font-size:3rem !important; color:#6c757d !important;"></i>';
            }
            $html .= '</div>';

            $html .= '<div style="padding:0.6rem 0.75rem !important;">';
            $html .= '<div class="byblos-files-thumb-title" style="font-weight:600 !important;'
                . ' font-size:0.85rem !important; color:#212529 !important;'
                . ' white-space:nowrap !important; overflow:hidden !important;'
                . ' text-overflow:ellipsis !important;">' . s($title) . '</div>';
            if ($desc !== '') {
                $html .= '<div class="byblos-files-thumb-desc" style="font-size:0.75rem !important;'
                    . ' color:#666 !important; white-space:nowrap !important; overflow:hidden !important;'
                    . ' text-overflow:ellipsis !important;">' . s($desc) . '</div>';
            }
            $html .= '</div>';
            $html .= '</a></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Parse a YouTube URL (or bare video id) into its 11-character video id.
     *
     * Accepts all the common forms:
     *   - https://www.youtube.com/watch?v=VIDEO_ID
     *   - https://www.youtube.com/watch?v=VIDEO_ID&t=123
     *   - https://youtu.be/VIDEO_ID
     *   - https://youtu.be/VIDEO_ID?t=60
     *   - https://www.youtube.com/embed/VIDEO_ID
     *   - https://www.youtube.com/shorts/VIDEO_ID
     *   - https://www.youtube.com/live/VIDEO_ID
     *   - VIDEO_ID (raw 11-char id)
     *
     * @param string $input
     * @return string|null 11-character video id, or null if the input can't be parsed.
     */
    public static function parse_youtube_id(string $input): ?string {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // Raw 11-character id.
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }

        // Match: youtu.be short form.
        if (preg_match('~^https?://youtu\.be/([A-Za-z0-9_-]{11})~i', $input, $m)) {
            return $m[1];
        }

        // Match: youtube.com/watch?v=ID.
        if (preg_match('~[?&]v=([A-Za-z0-9_-]{11})~', $input, $m)) {
            return $m[1];
        }

        // Match: youtube.com/embed/ID, /shorts/ID, /live/ID, /v/ID.
        if (
            preg_match(
                '~^https?://(?:www\.)?youtube\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~i',
                $input,
                $m
            )
        ) {
            return $m[1];
        }

        return null;
    }

    /**
     * Render a YouTube embed with optional heading, caption, start offset, and
     * layout (full / center / left / right). Left and right layouts float the
     * video alongside body rich-text.
     *
     * @param array $config Decoded configdata: `url`, `heading`, `description`,
     *                      `start` (seconds), `alignment` (full|center|left|right),
     *                      `body` (rich HTML shown beside video in left/right layouts).
     * @return string HTML fragment.
     */
    public static function render_youtube(array $config): string {
        $url       = (string) ($config['url'] ?? '');
        $heading   = (string) ($config['heading'] ?? '');
        $desc      = (string) ($config['description'] ?? '');
        $start     = (int) ($config['start'] ?? 0);
        $alignment = (string) ($config['alignment'] ?? 'full');
        $body      = (string) ($config['body'] ?? '');

        if (!in_array($alignment, ['full', 'center', 'left', 'right'], true)) {
            $alignment = 'full';
        }

        $html = '<div class="byblos-section-youtube byblos-youtube-align-' . s($alignment)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-youtube-heading" style="margin-bottom:0.75rem !important;">'
                . s($heading) . '</h2>';
        }

        $videoid = self::parse_youtube_id($url);
        if ($videoid === null) {
            $html .= '<div class="alert alert-warning" style="margin:0 !important;">'
                . get_string('youtube_invalid', 'local_byblos') . '</div>';
            $html .= '</div>';
            return $html;
        }

        // Build the embed URL — privacy-enhanced domain + optional start offset.
        $embedurl = 'https://www.youtube-nocookie.com/embed/' . $videoid . '?rel=0';
        if ($start > 0) {
            $embedurl .= '&start=' . $start;
        }

        // The video frame (wrapper + iframe) — same markup for every layout.
        $frame = '<div class="byblos-youtube-frame"'
            . ' style="position:relative !important; width:100% !important;'
            . ' aspect-ratio:16/9 !important; background:#000 !important;'
            . ' border-radius:0.5rem !important; overflow:hidden !important;'
            . ' box-shadow:0 2px 12px rgba(0,0,0,0.12) !important;">'
            . '<iframe src="' . s($embedurl) . '"'
            . ' style="position:absolute !important; inset:0 !important;'
            . ' width:100% !important; height:100% !important; border:0 !important;"'
            . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
            . ' allowfullscreen loading="lazy"'
            . ' title="' . s($heading !== '' ? $heading : 'YouTube video') . '"></iframe>'
            . '</div>';

        $captionhtml = $desc !== ''
            ? '<p class="byblos-youtube-desc" style="margin:0.75rem 0 0 0 !important;'
                . ' font-size:1.05rem !important; color:#6c757d !important;'
                . ' font-style:italic !important; line-height:1.5 !important;">'
                . s($desc) . '</p>'
            : '';

        $bodyhtml = $body !== ''
            ? '<div class="byblos-youtube-body" style="min-width:0 !important;">'
                . self::clean_body($body) . '</div>'
            : '';

        // Per-alignment layout.
        if ($alignment === 'left' || $alignment === 'right') {
            // Two-column grid — video on one side, rich body on the other.
            // Stacks on narrow screens via the `grid-template-columns` min().
            $order = ($alignment === 'left') ? 1 : 2;
            $bodyorder = ($alignment === 'left') ? 2 : 1;
            $sidebody = $body !== '' ? $bodyhtml : '<div class="byblos-youtube-body text-muted"'
                . ' style="min-width:0 !important;"><em>'
                . get_string('youtube_body_placeholder', 'local_byblos') . '</em></div>';
            $html .= '<div class="byblos-youtube-split"'
                . ' style="display:grid !important; gap:1.25rem !important;'
                . ' grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)) !important;'
                . ' align-items:start !important;">';
            $html .= '<div class="byblos-youtube-media" style="order:' . $order . ' !important;'
                . ' min-width:0 !important;">' . $frame . $captionhtml . '</div>';
            // Inject order via wrapper since the helper above doesn't know about it.
            $sidebody = preg_replace(
                '/class="byblos-youtube-body/',
                'style="order:' . $bodyorder . ' !important;" class="byblos-youtube-body',
                $sidebody,
                1
            );
            $html .= $sidebody;
            $html .= '</div>';
        } else if ($alignment === 'center') {
            $html .= '<div class="byblos-youtube-center-wrap"'
                . ' style="max-width:720px !important; margin:0 auto !important;">';
            $html .= '<div class="byblos-youtube-media">' . $frame . $captionhtml . '</div>';
            if ($bodyhtml !== '') {
                $html .= '<div style="margin-top:1.75rem !important;">' . $bodyhtml . '</div>';
            }
            $html .= '</div>';
        } else {
            // Full width — video first, then body below.
            $html .= '<div class="byblos-youtube-media">' . $frame . $captionhtml . '</div>';
            if ($bodyhtml !== '') {
                $html .= '<div style="margin-top:1.75rem !important;">' . $bodyhtml . '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }
    // Pagenav section.

    /**
     * Render a page-navigation widget.
     *
     * Resolves a target list of portfolio pages from either a collection
     * (ordered) or a manual list of page IDs (preserving the caller-supplied
     * order), filters for viewer-accessibility, and renders them in one of
     * four display modes (tabs, pills, cards, next/prev).
     *
     * @param array $config        Decoded configdata:
     *                             `heading`, `source` (collection|manual),
     *                             `collectionid`, `pageids[]`, `display`
     *                             (tabs|pills|cards|nextprev), `show_descriptions`.
     * @param int   $currentpageid Host page id (for active-state detection and
     *                             prev/next navigation). 0 when unknown.
     * @return string HTML fragment.
     */
    public static function render_pagenav(array $config, int $currentpageid = 0): string {
        global $USER;

        $heading    = (string) ($config['heading'] ?? '');
        $source     = (string) ($config['source'] ?? 'collection');
        $display    = (string) ($config['display'] ?? 'pills');
        $showdescs  = !empty($config['show_descriptions']);

        if (!in_array($source, ['collection', 'manual'], true)) {
            $source = 'collection';
        }
        if (!in_array($display, ['tabs', 'pills', 'cards', 'nextprev'], true)) {
            $display = 'pills';
        }

        $wrapclass = 'byblos-section-pagenav byblos-pagenav-display-' . $display;
        $html = '<div class="' . s($wrapclass) . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-pagenav-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        // Resolve the page list; each candidate is filtered by share::can_view_page.
        $pages = [];
        if ($source === 'collection') {
            $collectionid = (int) ($config['collectionid'] ?? 0);
            if ($collectionid > 0 && \local_byblos\share::can_view_collection((int) $USER->id, $collectionid)) {
                $pages = collection::get_pages($collectionid);
            }
        } else {
            $pageids = is_array($config['pageids'] ?? null) ? $config['pageids'] : [];
            foreach ($pageids as $pid) {
                $pid = (int) $pid;
                if ($pid <= 0) {
                    continue;
                }
                $p = page::get($pid);
                if (!$p) {
                    continue;
                }
                $pages[] = $p;
            }
        }
        $pages = array_values(array_filter(
            $pages,
            fn($p) => \local_byblos\share::can_view_page((int) $USER->id, (int) $p->id)
        ));

        if (empty($pages)) {
            $html .= '<p class="text-muted"><em>'
                . get_string('pagenav_empty', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        switch ($display) {
            case 'tabs':
                $html .= self::render_pagenav_nav($pages, $currentpageid, 'nav-tabs');
                break;
            case 'cards':
                $html .= self::render_pagenav_cards($pages, $showdescs);
                break;
            case 'nextprev':
                $html .= self::render_pagenav_nextprev($pages, $currentpageid);
                break;
            case 'pills':
            default:
                $html .= self::render_pagenav_nav($pages, $currentpageid, 'nav-pills');
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a Bootstrap nav list (tabs or pills) of page links.
     *
     * @param array  $pages         Array of page records.
     * @param int    $currentpageid Host page id used to mark the active link.
     * @param string $navclass      Either `nav-tabs` or `nav-pills`.
     * @return string
     */
    private static function render_pagenav_nav(array $pages, int $currentpageid, string $navclass): string {
        $html = '<ul class="nav ' . s($navclass) . '" role="tablist">';
        foreach ($pages as $p) {
            $isactive = ((int) $p->id === (int) $currentpageid);
            $url = '/local/byblos/page.php?id=' . (int) $p->id;
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link' . ($isactive ? ' active' : '') . '" href="'
                . s($url) . '"' . ($isactive ? ' aria-current="page"' : '') . '>'
                . s((string) ($p->title ?? '')) . '</a>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Render a responsive card grid of page links.
     *
     * @param array $pages
     * @param bool  $showdescs If true, include the page description on each card.
     * @return string
     */
    private static function render_pagenav_cards(array $pages, bool $showdescs): string {
        $html = '<div class="row byblos-pagenav-cards">';
        foreach ($pages as $p) {
            $title = (string) ($p->title ?? '');
            $desc  = (string) ($p->description ?? '');
            $url   = '/local/byblos/page.php?id=' . (int) $p->id;

            $html .= '<div class="col-md-4 col-sm-6 mb-3">';
            $html .= '<div class="card h-100 byblos-pagenav-card"'
                . ' style="border:1px solid #e9ecef !important; border-radius:0.5rem !important;">';
            $html .= '<div class="card-body" style="display:flex !important;'
                . ' flex-direction:column !important;">';
            $html .= '<h5 class="card-title" style="font-size:1rem !important;'
                . ' font-weight:600 !important; margin-bottom:0.5rem !important;">'
                . s($title) . '</h5>';
            if ($showdescs && $desc !== '') {
                $html .= '<p class="card-text text-muted small"'
                    . ' style="flex:1 !important;">' . s($desc) . '</p>';
            }
            $html .= '<a href="' . s($url) . '" class="btn btn-sm btn-outline-primary mt-auto"'
                . ' style="align-self:flex-start !important;">'
                . get_string('pagenav_viewpage', 'local_byblos') . '</a>';
            $html .= '</div></div></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render previous / next buttons relative to $currentpageid.
     *
     * @param array $pages
     * @param int   $currentpageid
     * @return string
     */
    private static function render_pagenav_nextprev(array $pages, int $currentpageid): string {
        $prev = null;
        $next = null;
        $pages = array_values($pages);
        $count = count($pages);
        for ($i = 0; $i < $count; $i++) {
            if ((int) $pages[$i]->id === (int) $currentpageid) {
                if ($i > 0) {
                    $prev = $pages[$i - 1];
                }
                if ($i < $count - 1) {
                    $next = $pages[$i + 1];
                }
                break;
            }
        }

        // If the current page isn't in the list, show first as next (common
        // on preview/editor where currentpageid may not match a list member).
        if ($prev === null && $next === null && $count > 0 && $currentpageid === 0) {
            $next = $pages[0];
        }

        $html = '<div class="byblos-pagenav-nextprev d-flex justify-content-between align-items-stretch"'
            . ' style="gap:1rem !important;">';

        if ($prev !== null) {
            $url = '/local/byblos/page.php?id=' . (int) $prev->id;
            $html .= '<a href="' . s($url) . '" class="btn btn-outline-secondary flex-fill text-left"'
                . ' style="padding:1rem !important;">'
                . '<div class="small text-muted"><i class="fa fa-chevron-left"></i> '
                . get_string('pagenav_previous', 'local_byblos') . '</div>'
                . '<div style="font-weight:600 !important;">' . s((string) ($prev->title ?? '')) . '</div>'
                . '</a>';
        } else {
            $html .= '<span class="flex-fill"></span>';
        }

        if ($next !== null) {
            $url = '/local/byblos/page.php?id=' . (int) $next->id;
            $html .= '<a href="' . s($url) . '" class="btn btn-outline-secondary flex-fill text-right"'
                . ' style="padding:1rem !important;">'
                . '<div class="small text-muted">' . get_string('pagenav_next', 'local_byblos')
                . ' <i class="fa fa-chevron-right"></i></div>'
                . '<div style="font-weight:600 !important;">' . s((string) ($next->title ?? '')) . '</div>'
                . '</a>';
        } else {
            $html .= '<span class="flex-fill"></span>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a learning-goals block: the page owner's goals with progress bars.
     *
     * @param array $config  Decoded configdata: `heading`, `mode`, `goalids`.
     * @param int   $ownerid The page owner whose goals to surface.
     * @return string HTML fragment.
     */
    public static function render_goals(array $config, int $ownerid): string {
        $heading = (string) ($config['heading'] ?? '');
        $mode    = (string) ($config['mode'] ?? 'all_active');
        $goalids = is_array($config['goalids'] ?? null) ? array_map('intval', $config['goalids']) : [];

        $goals = goal::list_by_user($ownerid);
        if ($mode === 'selected') {
            $set = array_flip($goalids);
            $goals = array_values(array_filter($goals, fn($g) => isset($set[(int) $g->id])));
        } else {
            $goals = array_values(array_filter($goals, fn($g) => $g->status === 'active'));
        }

        $html = '<div class="byblos-section-goals" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-goals-heading" style="margin-bottom:1rem !important;">'
                . s($heading) . '</h2>';
        }

        if (empty($goals)) {
            $html .= '<p class="text-muted"><em>' . get_string('nogoals', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        foreach ($goals as $g) {
            $progress = max(0, min(100, (int) $g->progress));
            $statuslabel = get_string('goalstatus_' . $g->status, 'local_byblos');
            $html .= '<div class="byblos-goal-row" style="margin-bottom:1rem !important;">';
            $html .= '<div style="display:flex !important; justify-content:space-between !important;'
                . ' align-items:baseline !important;">';
            $html .= '<strong style="font-size:1rem !important;">' . s($g->title) . '</strong>';
            $html .= '<span style="font-size:0.8rem !important; color:#666 !important;">'
                . s($statuslabel) . ' · ' . $progress . '%</span>';
            $html .= '</div>';
            $html .= '<div style="background:#e9ecef !important; border-radius:0.4rem !important;'
                . ' height:0.6rem !important; overflow:hidden !important; margin-top:0.25rem !important;">';
            $html .= '<div style="width:' . $progress . '% !important; height:100% !important;'
                . ' background:#0d6efd !important;"></div>';
            $html .= '</div>';
            if (!empty($g->description)) {
                $html .= '<div style="font-size:0.85rem !important; color:#666 !important;'
                    . ' margin-top:0.25rem !important;">' . s($g->description) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render an outcome-alignment map: each outcome with the evidence mapped to it.
     *
     * @param array $config  Decoded configdata: `heading`, `intro`, `outcomes[]`.
     * @param int   $ownerid The page owner (reserved for future live resolution).
     * @return string HTML fragment.
     */
    public static function render_alignment(array $config, int $ownerid): string {
        $heading  = (string) ($config['heading'] ?? '');
        $intro    = (string) ($config['intro'] ?? '');
        $outcomes = is_array($config['outcomes'] ?? null) ? $config['outcomes'] : [];

        $html = '<div class="byblos-section-alignment" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-alignment-heading" style="margin-bottom:0.5rem !important;">'
                . s($heading) . '</h2>';
        }
        if ($intro !== '') {
            $html .= '<p style="color:#555 !important; margin-bottom:1rem !important;">' . s($intro) . '</p>';
        }

        if (empty($outcomes)) {
            $html .= '<p class="text-muted"><em>' . get_string('noalignment', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        foreach ($outcomes as $outcome) {
            $text     = (string) ($outcome['text'] ?? '');
            $note     = (string) ($outcome['note'] ?? '');
            $evidence = is_array($outcome['evidence'] ?? null) ? $outcome['evidence'] : [];
            if ($text === '') {
                continue;
            }

            $html .= '<div class="byblos-alignment-row" style="border:1px solid rgba(0,0,0,0.08) !important;'
                . ' border-radius:0.5rem !important; padding:1rem !important; margin-bottom:0.75rem !important;'
                . ' background:#fff !important;">';
            $html .= '<div class="byblos-alignment-outcome" style="font-weight:600 !important;'
                . ' margin-bottom:0.5rem !important;">' . s($text) . '</div>';

            if (!empty($evidence)) {
                $html .= '<div class="byblos-alignment-evidence" style="display:flex !important;'
                    . ' flex-wrap:wrap !important; gap:0.4rem !important; margin-bottom:0.4rem !important;">';
                foreach ($evidence as $ev) {
                    $type  = (string) ($ev['type'] ?? '');
                    $evid  = (int) ($ev['id'] ?? 0);
                    $title = (string) ($ev['title'] ?? '');
                    if ($title === '' || $evid <= 0 || !in_array($type, ['artefact', 'page'], true)) {
                        continue;
                    }
                    $url = (new \moodle_url('/local/byblos/' . $type . '.php', ['id' => $evid]))->out(false);
                    $icon = $type === 'artefact' ? 'fa-puzzle-piece' : 'fa-file-text-o';
                    $html .= '<a href="' . $url . '" class="byblos-alignment-chip" '
                        . 'style="display:inline-flex !important; align-items:center !important;'
                        . ' gap:0.3rem !important; padding:0.2rem 0.6rem !important;'
                        . ' background:#eef2ff !important; border-radius:1rem !important;'
                        . ' font-size:0.85rem !important; text-decoration:none !important;'
                        . ' color:#1c3d8f !important;"><i class="fa ' . $icon . '"></i>'
                        . s($title) . '</a>';
                }
                $html .= '</div>';
            }

            if ($note !== '') {
                $html .= '<div class="byblos-alignment-note" style="font-size:0.85rem !important;'
                    . ' color:#666 !important; font-style:italic !important;">' . s($note) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render a reflection section: scaffolded rich body with recorded media.
     *
     * The stored body holds @@PLUGINFILE@@ tokens that we rewrite to live URLs
     * before passing through format_text (the same sanitiser as the custom-HTML
     * section; HTMLPurifier permits audio/video/source).
     *
     * @param array $config        Decoded configdata: `heading`, `framework`, `intro`, `bodyhtml`.
     * @param int   $sectionid     The section id (file area itemid).
     * @param int   $usercontextid The page owner's user context id (file storage context).
     * @return string HTML fragment.
     */
    public static function render_reflection(array $config, int $sectionid, int $usercontextid): string {
        $heading   = (string) ($config['heading'] ?? '');
        $framework = (string) ($config['framework'] ?? 'freewrite');
        $intro     = (string) ($config['intro'] ?? '');
        $bodyhtml  = (string) ($config['bodyhtml'] ?? '');

        $valid = ['freewrite', 'wsnw', 'gibbs', 'deal', 'kolb'];
        if (!in_array($framework, $valid, true)) {
            $framework = 'freewrite';
        }

        $html = '<div class="byblos-section-reflection byblos-reflection-framework-' . s($framework)
            . '" style="padding:1.5rem 0 !important;">';
        if ($heading !== '') {
            $html .= '<h2 class="byblos-reflection-heading" style="margin-bottom:0.5rem !important;">'
                . s($heading) . '</h2>';
        }
        if ($intro !== '') {
            $html .= '<p class="byblos-reflection-intro" style="color:#555 !important;'
                . ' margin-bottom:1rem !important;">' . s($intro) . '</p>';
        }

        if (
            trim(strip_tags($bodyhtml)) === '' && stripos($bodyhtml, '<audio') === false
                && stripos($bodyhtml, '<video') === false
        ) {
            $html .= '<p class="text-muted"><em>' . get_string('noreflection', 'local_byblos') . '</em></p>';
            $html .= '</div>';
            return $html;
        }

        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $bodyhtml = file_rewrite_pluginfile_urls(
            $bodyhtml,
            'pluginfile.php',
            $usercontextid,
            'local_byblos',
            file_manager::FILEAREA_REFLECTION,
            $sectionid
        );
        $clean = format_text($bodyhtml, FORMAT_HTML, [
            'noclean'     => false,
            'context'     => \context_system::instance(),
            'allowid'     => false,
            'overflowdiv' => false,
        ]);

        $html .= '<div class="byblos-reflection-body">' . $clean . '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Sanitise a stored rich-text body before output.
     *
     * Section bodies are stored as raw HTML (PARAM_RAW configdata) and must be
     * passed through HTMLPurifier on the way out, exactly like the custom-HTML
     * and reflection sections, so that scripts, event handlers and javascript:
     * URLs cannot survive into a shared or public page (stored-XSS defence).
     *
     * @param string $body Raw stored HTML.
     * @return string Sanitised HTML (empty string for empty input).
     */
    public static function clean_body(string $body): string {
        if (trim($body) === '') {
            return '';
        }
        return format_text($body, FORMAT_HTML, [
            'noclean'     => false,
            'context'     => \context_system::instance(),
            'allowid'     => false,
            'overflowdiv' => false,
        ]);
    }
}
