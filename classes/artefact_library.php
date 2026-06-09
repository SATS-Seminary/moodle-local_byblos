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
 * Builds the template context for the artefact library (the dashboard's
 * "My Artefacts" tab): enriched artefact cards plus the type and tag filter
 * sets. Search, sort, group, the grid/list toggle and filtering all run
 * client-side over this set via the local_byblos/artefacts module.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class artefact_library {
    /** @var string[] FontAwesome glyph per artefact type for the recognition tile. */
    private const FA_ICONS = [
        'text'              => 'fa-file-text-o',
        'image'             => 'fa-file-image-o',
        'file'              => 'fa-file-o',
        'link'              => 'fa-link',
        'audio'             => 'fa-file-audio-o',
        'video'             => 'fa-file-video-o',
        'embed'             => 'fa-code',
        'badge'             => 'fa-certificate',
        'course_completion' => 'fa-graduation-cap',
        'blog_entry'        => 'fa-rss',
    ];

    /** @var string[] Auto-imported types, folded behind one "Imported" filter pill. */
    private const IMPORTED_TYPES = ['badge', 'course_completion', 'blog_entry'];

    /** @var string[] Manually-creatable types, in display order, for the filter pills. */
    private const TYPE_ORDER = ['text', 'image', 'file', 'link', 'audio', 'video', 'embed'];

    /**
     * Build the library template context for one user's artefacts.
     *
     * @param int $userid The owner user id.
     * @return array Context: artefacts, has_artefacts, typefilters, tagfilters, hastags.
     */
    public static function data(int $userid): array {
        $artefacts = artefact::list_by_user($userid);

        // One bulk tag lookup for the whole set (avoids an N+1 query per artefact).
        $artefactids = array_map(static fn($a) => (int) $a->id, $artefacts);
        $itemtags = $artefactids
            ? \core_tag_tag::get_items_tags('local_byblos', 'local_byblos_artefact', $artefactids)
            : [];

        $fs = get_file_storage();
        $artdata = [];
        $typespresent = [];
        $hasimported = false;
        $tagsused = [];

        foreach ($artefacts as $a) {
            $type = $a->artefacttype;
            $typelabel = get_string('artefacttype_' . $type, 'local_byblos');
            $isimported = in_array($type, self::IMPORTED_TYPES, true);
            $typespresent[$type] = true;
            if ($isimported) {
                $hasimported = true;
            }

            // Real thumbnail for images; everything else uses its type glyph.
            $thumburl = '';
            if ($type === 'image' && !empty($a->fileid)) {
                $file = $fs->get_file_by_id($a->fileid);
                if ($file) {
                    $thumburl = \moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename(),
                    )->out(false);
                }
            }

            // Tag chips (display + view URL) and a lowercased name list for filtering.
            $tagchips = [];
            $tagnames = [];
            foreach (($itemtags[(int) $a->id] ?? []) as $tag) {
                $raw = $tag->get_display_name(false);
                $tagchips[] = ['name' => $raw, 'url' => $tag->get_view_url()->out(false)];
                $tagnames[] = \core_text::strtolower($raw);
                $tagsused[\core_text::strtolower($raw)] = $raw;
            }

            $plaindesc = trim(html_to_text($a->description ?? '', 0, false));
            $searchblob = \core_text::strtolower(trim($a->title . ' ' . $plaindesc . ' '
                . $typelabel . ' ' . implode(' ', $tagnames)));

            $artdata[] = [
                'id'          => $a->id,
                'title'       => format_string($a->title, true, ['escape' => false]),
                'type'        => $type,
                'typelabel'   => $typelabel,
                'faicon'      => self::FA_ICONS[$type] ?? 'fa-file-o',
                'isimage'     => ($thumburl !== ''),
                'thumburl'    => $thumburl,
                'imported'    => $isimported,
                'description' => format_text($a->description ?? '', FORMAT_HTML),
                'tags'        => $tagchips,
                'hastags'     => !empty($tagchips),
                'tagnames'    => implode(' ', $tagnames),
                'searchblob'  => $searchblob,
                'titlesort'   => \core_text::strtolower($a->title),
                'timesort'    => (int) $a->timecreated,
                'viewurl'     => (new \moodle_url('/local/byblos/artefact.php', ['id' => $a->id]))->out(false),
                'editurl'     => (new \moodle_url(
                    '/local/byblos/artefact.php',
                    ['id' => $a->id, 'action' => 'edit']
                ))->out(false),
                'deleteurl'   => (new \moodle_url('/local/byblos/delete.php'))->out(false),
                'timecreated' => userdate($a->timecreated),
            ];
        }

        // Type pills: "All", then only the types present, with the auto-imported
        // types folded behind one "Imported" pill.
        $typefilters = [[
            'value'  => '',
            'label'  => get_string('type_all', 'local_byblos'),
            'active' => true,
        ]];
        foreach (self::TYPE_ORDER as $t) {
            if (!empty($typespresent[$t])) {
                $typefilters[] = [
                    'value'  => $t,
                    'label'  => get_string('artefacttype_' . $t, 'local_byblos'),
                    'active' => false,
                ];
            }
        }
        if ($hasimported) {
            $typefilters[] = [
                'value'  => 'imported',
                'label'  => get_string('type_imported', 'local_byblos'),
                'active' => false,
            ];
        }

        // Tag filter chips (alphabetical), shown only when the library uses any tags.
        \core_collator::asort($tagsused);
        $tagfilters = [];
        foreach ($tagsused as $lower => $display) {
            $tagfilters[] = ['value' => $lower, 'label' => $display];
        }

        return [
            'artefacts'     => $artdata,
            'has_artefacts' => !empty($artdata),
            'typefilters'   => $typefilters,
            'tagfilters'    => $tagfilters,
            'hastags'       => !empty($tagfilters),
        ];
    }
}
