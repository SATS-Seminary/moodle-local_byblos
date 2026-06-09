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
 * Page-feedback model — logged-in, owner-moderated feedback left on a shared page.
 *
 * Distinct from {@see comment}, which is bound to assignment submissions. Page
 * feedback attaches to a published/shared page and never to anonymous viewers.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback {
    /** @var string Database table. */
    private const TABLE = 'local_byblos_pagefeedback';

    /** @var string[] Valid author roles. */
    public const ROLES = ['teacher', 'peer'];

    /**
     * Create a feedback row.
     *
     * @param int    $pageid
     * @param int    $authorid
     * @param string $authorrole 'teacher' or 'peer'.
     * @param string $body
     * @return int New feedback ID.
     */
    public static function create(int $pageid, int $authorid, string $authorrole, string $body): int {
        global $DB;

        if (!in_array($authorrole, self::ROLES, true)) {
            throw new \coding_exception("Invalid feedback role: {$authorrole}");
        }

        $now = time();
        return (int) $DB->insert_record(self::TABLE, (object) [
            'pageid'       => $pageid,
            'authorid'     => $authorid,
            'authorrole'   => $authorrole,
            'body'         => $body,
            'visible'      => 1,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Retrieve a feedback row by ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Update the body of a feedback row.
     *
     * @param int    $id
     * @param string $body
     * @return bool
     */
    public static function update_body(int $id, string $body): bool {
        global $DB;

        return $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'body'         => $body,
            'timemodified' => time(),
        ]);
    }

    /**
     * Set the visibility of a feedback row (owner moderation).
     *
     * @param int  $id
     * @param bool $visible
     * @return bool
     */
    public static function set_visible(int $id, bool $visible): bool {
        global $DB;

        return $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'visible'      => $visible ? 1 : 0,
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete a feedback row.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * List feedback for a page, oldest first.
     *
     * @param int  $pageid
     * @param bool $includehidden When true, also return rows with visible = 0 (owner view).
     * @return \stdClass[]
     */
    public static function list_for_page(int $pageid, bool $includehidden = false): array {
        global $DB;

        $conditions = ['pageid' => $pageid];
        if (!$includehidden) {
            $conditions['visible'] = 1;
        }
        return array_values($DB->get_records(self::TABLE, $conditions, 'timecreated ASC'));
    }
}
