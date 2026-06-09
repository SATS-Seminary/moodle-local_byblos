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
 * Artefact model — CRUD and auto-import for portfolio artefacts.
 *
 * Each artefact belongs to a user and has a type (text, file, image,
 * badge, course_completion, blog_entry). Badges and course completions
 * can be auto-imported from core Moodle tables.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class artefact {
    /** @var string Database table name. */
    private const TABLE = 'local_byblos_artefact';

    /**
     * Create a new artefact.
     *
     * @param int         $userid     Owner user ID.
     * @param string      $type       Artefact type key (text, file, image, badge, etc.).
     * @param string      $title      Human-readable title.
     * @param string      $description Short description.
     * @param string      $content    Body content (HTML or plain text).
     * @param int|null    $fileid     Moodle file ID if file-backed.
     * @param string|null $sourceref  External reference (e.g. badge:42, completion:7).
     * @return int Newly created artefact ID.
     */
    public static function create(
        int $userid,
        string $type,
        string $title,
        string $description,
        string $content,
        ?int $fileid = null,
        ?string $sourceref = null,
    ): int {
        global $DB;

        $now = time();
        $record = (object) [
            'userid'       => $userid,
            'artefacttype' => $type,
            'title'        => $title,
            'description'  => $description,
            'content'      => $content,
            'fileid'       => $fileid,
            'sourceref'    => $sourceref,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Retrieve a single artefact by ID.
     *
     * @param int $id Artefact ID.
     * @return \stdClass|null The artefact record, or null if not found.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update an existing artefact.
     *
     * Only the fields present in $data are updated; timemodified is
     * always refreshed.
     *
     * @param int   $id   Artefact ID.
     * @param array $data Associative array of column => value pairs.
     * @return bool True on success.
     */
    public static function update(int $id, array $data): bool {
        global $DB;

        $data['id'] = $id;
        $data['timemodified'] = time();

        return $DB->update_record(self::TABLE, (object) $data);
    }

    /**
     * Delete an artefact.
     *
     * @param int $id Artefact ID.
     * @return bool True on success.
     */
    public static function delete(int $id): bool {
        global $DB;

        // Remove any tag instances attached to this artefact (core_tag area).
        \core_tag_tag::remove_all_item_tags('local_byblos', 'local_byblos_artefact', $id);

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List artefacts belonging to a user, optionally filtered by type.
     *
     * @param int    $userid Owner user ID.
     * @param string $type   Optional type filter (empty string = all).
     * @return array Array of stdClass records ordered by timecreated DESC.
     */
    public static function list_by_user(int $userid, string $type = ''): array {
        global $DB;

        $params = ['userid' => $userid];
        $typesql = '';
        if ($type !== '') {
            $typesql = ' AND artefacttype = :type';
            $params['type'] = $type;
        }

        return array_values(
            $DB->get_records_select(
                self::TABLE,
                'userid = :userid' . $typesql,
                $params,
                'timecreated DESC',
            )
        );
    }

    /**
     * Count artefacts belonging to a user.
     *
     * @param int $userid Owner user ID.
     * @return int Number of artefacts.
     */
    public static function count_by_user(int $userid): int {
        global $DB;

        return $DB->count_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Auto-import badges awarded to a user.
     *
     * Scans the core badge_issued table and creates artefacts of type
     * "badge" for each issued badge not already imported (identified by
     * sourceref = "badge:{issuedid}").
     *
     * @param int $userid User ID.
     * @return int Number of newly imported artefacts.
     */
    public static function auto_import_badges(int $userid): int {
        global $DB;

        $sql = "SELECT bi.id AS issuedid, bi.badgeid, b.name, b.description
                  FROM {badge_issued} bi
                  JOIN {badge} b ON b.id = bi.badgeid
                 WHERE bi.userid = :userid";

        $issued = $DB->get_records_sql($sql, ['userid' => $userid]);
        $imported = 0;

        foreach ($issued as $badge) {
            $ref = 'badge:' . $badge->issuedid;

            // Skip if already imported.
            if ($DB->record_exists(self::TABLE, ['userid' => $userid, 'sourceref' => $ref])) {
                continue;
            }

            self::create(
                $userid,
                'badge',
                $badge->name,
                $badge->description ?? '',
                '',
                null,
                $ref,
            );
            $imported++;
        }

        return $imported;
    }

    /**
     * Auto-import course completions for a user.
     *
     * Scans the core course_completions table and creates artefacts of
     * type "course_completion" for each completion not already imported
     * (identified by sourceref = "completion:{completionid}").
     *
     * @param int $userid User ID.
     * @return int Number of newly imported artefacts.
     */
    public static function auto_import_completions(int $userid): int {
        global $DB;

        $sql = "SELECT cc.id AS completionid, cc.course, c.fullname, c.summary
                  FROM {course_completions} cc
                  JOIN {course} c ON c.id = cc.course
                 WHERE cc.userid = :userid
                   AND cc.timecompleted IS NOT NULL";

        $completions = $DB->get_records_sql($sql, ['userid' => $userid]);
        $imported = 0;

        foreach ($completions as $comp) {
            $ref = 'completion:' . $comp->completionid;

            // Skip if already imported.
            if ($DB->record_exists(self::TABLE, ['userid' => $userid, 'sourceref' => $ref])) {
                continue;
            }

            self::create(
                $userid,
                'course_completion',
                $comp->fullname,
                $comp->summary ?? '',
                '',
                null,
                $ref,
            );
            $imported++;
        }

        return $imported;
    }
}
