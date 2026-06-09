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
 * Documentation hub registry.
 *
 * Single source of truth for the in-plugin help pages: the ordered topic list,
 * their lang-string titles, and the grouping used to build the sidebar. Used by
 * docs.php to validate the requested topic, render the sidebar, compute
 * prev/next, and supply cross-link URLs to the content templates.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docs {
    /**
     * Ordered registry of documentation topics.
     *
     * Order here drives both the sidebar order and prev/next navigation. The
     * 'group' key is one of: home (the landing page), guide, concept, usecase.
     *
     * @return array<string, array{title: string, group: string}>
     */
    public static function topics(): array {
        return [
            'index'                => ['title' => 'docs_topic_index', 'group' => 'home'],
            'getting_started'      => ['title' => 'docs_topic_getting_started', 'group' => 'guide'],
            'building_pages'       => ['title' => 'docs_topic_building_pages', 'group' => 'guide'],
            'publishing'           => ['title' => 'docs_topic_publishing', 'group' => 'guide'],
            'collections'          => ['title' => 'docs_topic_collections', 'group' => 'guide'],
            'goals'                => ['title' => 'docs_topic_goals', 'group' => 'guide'],
            'sharing'              => ['title' => 'docs_topic_sharing', 'group' => 'guide'],
            'assessment'           => ['title' => 'docs_topic_assessment', 'group' => 'guide'],
            'pedagogy'             => ['title' => 'docs_topic_pedagogy', 'group' => 'concept'],
            'best_practices'       => ['title' => 'docs_topic_best_practices', 'group' => 'concept'],
            'usecase_programme'    => ['title' => 'docs_topic_usecase_programme', 'group' => 'usecase'],
            'usecase_course'       => ['title' => 'docs_topic_usecase_course', 'group' => 'usecase'],
            'usecase_professional' => ['title' => 'docs_topic_usecase_professional', 'group' => 'usecase'],
        ];
    }

    /**
     * Whether the given topic key is a known documentation topic.
     *
     * @param string $key Topic key.
     * @return bool
     */
    public static function exists(string $key): bool {
        return array_key_exists($key, self::topics());
    }

    /**
     * Build the moodle_url for a topic page.
     *
     * @param string $key Topic key.
     * @return \moodle_url
     */
    public static function topic_url(string $key): \moodle_url {
        $params = ($key === 'index') ? [] : ['topic' => $key];
        return new \moodle_url('/local/byblos/docs.php', $params);
    }

    /**
     * Map of topic key => URL string, for cross-linking inside content templates.
     *
     * @return array<string, string>
     */
    public static function topiclinks(): array {
        $links = [];
        foreach (array_keys(self::topics()) as $key) {
            $links[$key] = self::topic_url($key)->out(false);
        }
        return $links;
    }

    /**
     * Build the grouped sidebar context (Guides + Use cases) with active flags.
     *
     * @param string $active The currently displayed topic key.
     * @return array Sidebar groups, each with a label and a list of items.
     */
    public static function nav(string $active): array {
        $guides = [];
        $concepts = [];
        $usecases = [];
        foreach (self::topics() as $key => $meta) {
            if ($meta['group'] === 'home') {
                continue;
            }
            $item = [
                'title'  => get_string($meta['title'], 'local_byblos'),
                'url'    => self::topic_url($key)->out(false),
                'active' => ($key === $active),
            ];
            if ($meta['group'] === 'usecase') {
                $usecases[] = $item;
            } else if ($meta['group'] === 'concept') {
                $concepts[] = $item;
            } else {
                $guides[] = $item;
            }
        }
        return [
            ['label' => get_string('docs_group_guides', 'local_byblos'), 'items' => $guides],
            ['label' => get_string('docs_group_concepts', 'local_byblos'), 'items' => $concepts],
            ['label' => get_string('docs_group_usecases', 'local_byblos'), 'items' => $usecases],
        ];
    }

    /**
     * Compute the previous and next topics for footer navigation.
     *
     * @param string $key The current topic key.
     * @return array{prev: ?array, next: ?array} Each entry is {title, url} or null.
     */
    public static function adjacent(string $key): array {
        $keys = array_keys(self::topics());
        $pos = array_search($key, $keys, true);
        $result = ['prev' => null, 'next' => null];
        if ($pos === false) {
            return $result;
        }
        if ($pos > 0) {
            $prevkey = $keys[$pos - 1];
            $result['prev'] = [
                'title' => get_string(self::topics()[$prevkey]['title'], 'local_byblos'),
                'url'   => self::topic_url($prevkey)->out(false),
            ];
        }
        if ($pos < count($keys) - 1) {
            $nextkey = $keys[$pos + 1];
            $result['next'] = [
                'title' => get_string(self::topics()[$nextkey]['title'], 'local_byblos'),
                'url'   => self::topic_url($nextkey)->out(false),
            ];
        }
        return $result;
    }
}
