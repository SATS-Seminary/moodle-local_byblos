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
 * Page model — CRUD and course-tagging for portfolio pages.
 *
 * A page is the primary display unit of the portfolio. It can contain
 * sections (structured layout) or be a single-body page. Pages may be
 * tagged to courses and grouped into collections.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {
    /** @var string Database table name. */
    private const TABLE = 'local_byblos_page';

    /** @var string Course-tagging join table. */
    private const TABLE_COURSE = 'local_byblos_page_course';

    /**
     * Create a new portfolio page.
     *
     * @param int    $userid    Owner user ID.
     * @param string $title     Page title.
     * @param string $description Page description.
     * @param string $layoutkey  Layout identifier (e.g. single, two-col).
     * @param string $themekey   Theme identifier (e.g. clean, dark).
     * @return int Newly created page ID.
     */
    public static function create(
        int $userid,
        string $title,
        string $description = '',
        string $layoutkey = 'single',
        string $themekey = 'clean',
    ): int {
        global $DB;

        $now = time();
        $record = (object) [
            'userid'       => $userid,
            'title'        => $title,
            'description'  => $description,
            'layoutkey'    => $layoutkey,
            'themekey'     => $themekey,
            'status'       => 'draft',
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Retrieve a single page by ID.
     *
     * @param int $id Page ID.
     * @return \stdClass|null The page record, or null if not found.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update an existing page.
     *
     * @param int   $id   Page ID.
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
     * Delete a page and all dependent data.
     *
     * Cascade-deletes sections, collection_page references, shares,
     * and page_course tags.
     *
     * @param int $id Page ID.
     * @return bool True on success.
     */
    public static function delete(int $id): bool {
        global $DB;

        // Cascade deletes.
        $DB->delete_records('local_byblos_section', ['pageid' => $id]);
        $DB->delete_records('local_byblos_collection_page', ['pageid' => $id]);
        $DB->delete_records('local_byblos_share', ['pageid' => $id]);
        $DB->delete_records(self::TABLE_COURSE, ['pageid' => $id]);

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List all pages belonging to a user.
     *
     * @param int $userid Owner user ID.
     * @return array Array of stdClass records ordered by timecreated DESC.
     */
    public static function list_by_user(int $userid): array {
        global $DB;

        return array_values(
            $DB->get_records(self::TABLE, ['userid' => $userid], 'timecreated DESC')
        );
    }

    /**
     * Set the publication status of a page.
     *
     * @param int    $id     Page ID.
     * @param string $status New status (draft, published, archived).
     * @return bool True on success.
     */
    public static function set_status(int $id, string $status): bool {
        return self::update($id, ['status' => $status]);
    }

    /**
     * Tag a page with a course.
     *
     * Creates a row in local_byblos_page_course. Does nothing if the
     * tag already exists.
     *
     * @param int $pageid   Page ID.
     * @param int $courseid Course ID.
     * @return void
     */
    public static function tag_course(int $pageid, int $courseid): void {
        global $DB;

        if ($DB->record_exists(self::TABLE_COURSE, ['pageid' => $pageid, 'courseid' => $courseid])) {
            return;
        }

        $DB->insert_record(self::TABLE_COURSE, (object) [
            'pageid'      => $pageid,
            'courseid'    => $courseid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Remove a course tag from a page.
     *
     * @param int $pageid   Page ID.
     * @param int $courseid Course ID.
     * @return void
     */
    public static function untag_course(int $pageid, int $courseid): void {
        global $DB;

        $DB->delete_records(self::TABLE_COURSE, ['pageid' => $pageid, 'courseid' => $courseid]);
    }

    /**
     * Get all course IDs tagged to a page.
     *
     * @param int $pageid Page ID.
     * @return array Array of integer course IDs.
     */
    public static function get_courses(int $pageid): array {
        global $DB;

        $records = $DB->get_records(self::TABLE_COURSE, ['pageid' => $pageid], '', 'courseid');

        return array_map(fn($r) => (int) $r->courseid, array_values($records));
    }

    /**
     * Get all pages tagged with a specific course.
     *
     * @param int $courseid Course ID.
     * @return array Array of page stdClass records.
     */
    public static function get_pages_for_course(int $courseid): array {
        global $DB;

        // A page belongs on a course's portfolio list if it is explicitly
        // associated with the course (page_course) OR shared with the whole
        // course (a 'course' share). Gather the distinct page ids from both
        // sources (a UNION over integer ids is portable across databases),
        // then load the records.
        $idsql = "SELECT pc.pageid AS pid
                    FROM {" . self::TABLE_COURSE . "} pc
                   WHERE pc.courseid = :cid1
                   UNION
                  SELECT s.pageid AS pid
                    FROM {local_byblos_share} s
                   WHERE s.sharetype = 'course' AND s.sharevalue = :cid2 AND s.pageid <> 0";
        $ids = $DB->get_fieldset_sql($idsql, ['cid1' => $courseid, 'cid2' => (string) $courseid]);
        if (empty($ids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        return array_values($DB->get_records_select(self::TABLE, "id $insql", $inparams, 'title ASC'));
    }

    /**
     * Check whether a page uses structured sections.
     *
     * @param int $pageid Page ID.
     * @return bool True if at least one section row exists for this page.
     */
    public static function uses_sections(int $pageid): bool {
        global $DB;

        return $DB->record_exists('local_byblos_section', ['pageid' => $pageid]);
    }
}
