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
 * Learning-goal model — a self-regulated-learning goal a user sets and tracks
 * over time, with optional links to evidence (artefacts or pages).
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class goal {
    /** @var string Goal table. */
    private const TABLE = 'local_byblos_goal';

    /** @var string Evidence-link table. */
    private const LINKTABLE = 'local_byblos_goal_link';

    /** @var string[] Valid goal statuses. */
    public const STATUSES = ['active', 'achieved', 'archived'];

    /** @var string[] Valid evidence link types. */
    public const LINKTYPES = ['artefact', 'page'];

    /**
     * Create a new goal for a user. Appended at the end of their goal order.
     *
     * @param int      $userid
     * @param string   $title
     * @param string|null $description
     * @param int|null $targetdate Optional unix timestamp.
     * @return int Newly created goal ID.
     */
    public static function create(
        int $userid,
        string $title,
        ?string $description = null,
        ?int $targetdate = null
    ): int {
        global $DB;

        $now = time();
        $maxsort = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {' . self::TABLE . '} WHERE userid = ?',
            [$userid]
        );

        return (int) $DB->insert_record(self::TABLE, (object) [
            'userid'       => $userid,
            'title'        => $title,
            'description'  => $description,
            'status'       => 'active',
            'targetdate'   => $targetdate ?: null,
            'progress'     => 0,
            'sortorder'    => $maxsort + 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Retrieve a goal by ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update a goal. Only whitelisted fields are written.
     *
     * @param int   $id
     * @param array $data Associative array of fields to set.
     * @return bool
     */
    public static function update(int $id, array $data): bool {
        global $DB;

        $allowed = ['title', 'description', 'status', 'progress', 'targetdate'];
        $record = (object) ['id' => $id, 'timemodified' => time()];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $record->{$field} = $data[$field];
            }
        }
        if (isset($record->progress)) {
            $record->progress = max(0, min(100, (int) $record->progress));
        }
        if (isset($record->status) && !in_array($record->status, self::STATUSES, true)) {
            throw new \coding_exception("Invalid goal status: {$record->status}");
        }

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Delete a goal and all of its evidence links.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        $DB->delete_records(self::LINKTABLE, ['goalid' => $id]);
        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List a user's goals, optionally filtered by status, in sort order.
     *
     * @param int         $userid
     * @param string|null $status Optional status filter.
     * @return \stdClass[]
     */
    public static function list_by_user(int $userid, ?string $status = null): array {
        global $DB;

        $conditions = ['userid' => $userid];
        if ($status !== null) {
            $conditions['status'] = $status;
        }

        return array_values($DB->get_records(self::TABLE, $conditions, 'sortorder ASC, id ASC'));
    }

    /**
     * Persist a new sort order. Caller is responsible for verifying ownership of
     * every goal in the ordering.
     *
     * @param array $ordering Map of goalid => sortorder.
     * @return void
     */
    public static function reorder(array $ordering): void {
        global $DB;

        $now = time();
        foreach ($ordering as $goalid => $sortorder) {
            $DB->update_record(self::TABLE, (object) [
                'id'           => (int) $goalid,
                'sortorder'    => (int) $sortorder,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Link a piece of evidence (artefact or page) to a goal. Idempotent on the
     * unique (goalid, linktype, linkid) index.
     *
     * @param int    $goalid
     * @param string $linktype 'artefact' or 'page'.
     * @param int    $linkid
     * @return int Link ID (existing or new).
     */
    public static function add_link(int $goalid, string $linktype, int $linkid): int {
        global $DB;

        if (!in_array($linktype, self::LINKTYPES, true)) {
            throw new \coding_exception("Invalid link type: {$linktype}");
        }

        $existing = $DB->get_record(self::LINKTABLE, [
            'goalid' => $goalid, 'linktype' => $linktype, 'linkid' => $linkid,
        ]);
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) $DB->insert_record(self::LINKTABLE, (object) [
            'goalid'      => $goalid,
            'linktype'    => $linktype,
            'linkid'      => $linkid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Remove an evidence link from a goal.
     *
     * @param int    $goalid
     * @param string $linktype
     * @param int    $linkid
     * @return bool
     */
    public static function remove_link(int $goalid, string $linktype, int $linkid): bool {
        global $DB;

        return $DB->delete_records(self::LINKTABLE, [
            'goalid' => $goalid, 'linktype' => $linktype, 'linkid' => $linkid,
        ]);
    }

    /**
     * Get a goal's evidence links resolved to display rows {type, id, title, url}.
     *
     * Orphaned links (deleted artefact/page) are skipped.
     *
     * @param int $goalid
     * @return array[] List of resolved evidence rows.
     */
    public static function get_links(int $goalid): array {
        global $DB;

        $links = $DB->get_records(self::LINKTABLE, ['goalid' => $goalid], 'timecreated ASC');
        $rows = [];
        foreach ($links as $link) {
            if ($link->linktype === 'artefact') {
                $artefact = $DB->get_record('local_byblos_artefact', ['id' => $link->linkid], 'id, title');
                if (!$artefact) {
                    continue;
                }
                $rows[] = [
                    'type'  => 'artefact',
                    'id'    => (int) $link->linkid,
                    'title' => $artefact->title,
                    'url'   => (new \moodle_url('/local/byblos/artefact.php', ['id' => $link->linkid]))->out(false),
                ];
            } else if ($link->linktype === 'page') {
                $page = $DB->get_record('local_byblos_page', ['id' => $link->linkid], 'id, title');
                if (!$page) {
                    continue;
                }
                $rows[] = [
                    'type'  => 'page',
                    'id'    => (int) $link->linkid,
                    'title' => $page->title,
                    'url'   => (new \moodle_url('/local/byblos/page.php', ['id' => $link->linkid]))->out(false),
                ];
            }
        }
        return $rows;
    }

    /**
     * Delete all goals (and links) belonging to a user. Used by the privacy API.
     *
     * @param int $userid
     * @return void
     */
    public static function delete_for_user(int $userid): void {
        global $DB;

        $goalids = $DB->get_fieldset_select(self::TABLE, 'id', 'userid = ?', [$userid]);
        if ($goalids) {
            [$insql, $params] = $DB->get_in_or_equal($goalids);
            $DB->delete_records_select(self::LINKTABLE, "goalid {$insql}", $params);
        }
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /** @var string[] Quick-start template keys, in display order. */
    private const TEMPLATE_KEYS = ['reflection', 'integrate', 'align', 'feedback', 'growth', 'demonstrate'];

    /**
     * Quick-start goal templates: scaffolded starter goals a learner can add in
     * one click, each grounded in a documented best practice / high-impact
     * practice. Titles, descriptions and the practice label are translatable.
     *
     * @return array[] List of {key, title, description, practice}.
     */
    public static function quickstart_templates(): array {
        $templates = [];
        foreach (self::TEMPLATE_KEYS as $key) {
            $templates[] = [
                'key'         => $key,
                'title'       => get_string("goaltpl_{$key}_title", 'local_byblos'),
                'description' => get_string("goaltpl_{$key}_desc", 'local_byblos'),
                'practice'    => get_string("goaltpl_{$key}_practice", 'local_byblos'),
            ];
        }
        return $templates;
    }
}
