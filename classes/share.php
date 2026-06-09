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
 * Share model — sharing and access-control for pages and collections.
 *
 * Sharing types:
 *  - public  : anyone with the token URL
 *  - user    : specific user (sharevalue = userid)
 *  - course  : all enrolled users in a course (sharevalue = courseid)
 *  - group   : all members of a group (sharevalue = groupid)
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class share {
    /** @var string Database table name. */
    private const TABLE = 'local_byblos_share';

    /** @var int Token length in bytes (hex-encoded to 64 chars). */
    private const TOKEN_BYTES = 32;

    /**
     * Create a new share record.
     *
     * For public shares a random token is generated automatically.
     * Either $pageid or $collectionid should be non-zero
     * (not both zero, not both non-zero).
     *
     * @param int    $pageid       Page ID, or 0 if sharing a collection.
     * @param int    $collectionid Collection ID, or 0 if sharing a page.
     * @param string $sharetype         One of: public, user, course, group.
     * @param string $sharevalue        Type-specific value (userid, courseid, groupid, or empty).
     * @return int Newly created share ID.
     */
    public static function create(
        int $pageid,
        int $collectionid,
        string $sharetype,
        string $sharevalue = '',
    ): int {
        global $DB;

        $token = '';
        if ($sharetype === 'public') {
            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        }

        $now = time();
        $record = (object) [
            'pageid'       => $pageid,
            'collectionid' => $collectionid,
            'sharetype'    => $sharetype,
            'sharevalue'   => $sharevalue,
            'token'        => $token,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a share record.
     *
     * @param int $id Share ID.
     * @return bool True on success.
     */
    public static function delete(int $id): bool {
        global $DB;

        return $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Look up a share by its public token.
     *
     * @param string $token The share token.
     * @return \stdClass|null The share record, or null if not found.
     */
    public static function get_by_token(string $token): ?\stdClass {
        global $DB;

        return $DB->get_record(self::TABLE, ['token' => $token]) ?: null;
    }

    /**
     * List all shares for a specific page.
     *
     * @param int $pageid Page ID.
     * @return array Array of stdClass share records.
     */
    public static function list_for_page(int $pageid): array {
        global $DB;

        return array_values($DB->get_records(self::TABLE, ['pageid' => $pageid]));
    }

    /**
     * List all shares for a specific collection.
     *
     * @param int $collectionid Collection ID.
     * @return array Array of stdClass share records.
     */
    public static function list_for_collection(int $collectionid): array {
        global $DB;

        return array_values($DB->get_records(self::TABLE, ['collectionid' => $collectionid]));
    }

    /**
     * Check whether a user can view a page.
     *
     * Access is granted if the user owns the page, if any page-level share
     * record matches, or if the page belongs to a collection the user can view.
     *
     * @param int $userid User ID.
     * @param int $pageid Page ID.
     * @return bool True if the user may view the page.
     */
    public static function can_view_page(int $userid, int $pageid): bool {
        global $DB;

        // Owner always has access.
        $page = $DB->get_record('local_byblos_page', ['id' => $pageid]);
        if (!$page) {
            return false;
        }
        if ((int) $page->userid === $userid) {
            return true;
        }

        // Direct user share.
        if (
            $DB->record_exists(self::TABLE, [
            'pageid' => $pageid, 'sharetype' => 'user', 'sharevalue' => (string) $userid,
            ])
        ) {
            return true;
        }

        // Course share — user must be enrolled.
        $courseshares = $DB->get_records(self::TABLE, ['pageid' => $pageid, 'sharetype' => 'course']);
        foreach ($courseshares as $cs) {
            $context = \context_course::instance((int) $cs->sharevalue, IGNORE_MISSING);
            if ($context && is_enrolled($context, $userid)) {
                return true;
            }
        }

        // Group share — user must be a member.
        $groupshares = $DB->get_records(self::TABLE, ['pageid' => $pageid, 'sharetype' => 'group']);
        foreach ($groupshares as $gs) {
            if (groups_is_member((int) $gs->sharevalue, $userid)) {
                return true;
            }
        }

        // Collection-level share: page is a member of a collection the user can view.
        $collectionids = $DB->get_fieldset_select(
            'local_byblos_collection_page',
            'collectionid',
            'pageid = :pid',
            ['pid' => $pageid]
        );
        foreach ($collectionids as $cid) {
            if (self::can_view_collection($userid, (int) $cid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a user can view a collection.
     *
     * Access is granted if the user owns the collection or if any
     * share record (user, course, group) matches.
     *
     * @param int $userid       User ID.
     * @param int $collectionid Collection ID.
     * @return bool True if the user may view the collection.
     */
    public static function can_view_collection(int $userid, int $collectionid): bool {
        global $DB;

        // Owner always has access.
        $coll = $DB->get_record('local_byblos_collection', ['id' => $collectionid]);
        if (!$coll) {
            return false;
        }
        if ((int) $coll->userid === $userid) {
            return true;
        }

        // Group-bound collection — every member of the bound group sees it.
        if (!empty($coll->groupid) && groups_is_member((int) $coll->groupid, $userid)) {
            return true;
        }

        // Direct user share.
        if (
            $DB->record_exists(self::TABLE, [
            'collectionid' => $collectionid, 'sharetype' => 'user', 'sharevalue' => (string) $userid,
            ])
        ) {
            return true;
        }

        // Course share.
        $courseshares = $DB->get_records(self::TABLE, ['collectionid' => $collectionid, 'sharetype' => 'course']);
        foreach ($courseshares as $cs) {
            $context = \context_course::instance((int) $cs->sharevalue, IGNORE_MISSING);
            if ($context && is_enrolled($context, $userid)) {
                return true;
            }
        }

        // Group share.
        $groupshares = $DB->get_records(self::TABLE, ['collectionid' => $collectionid, 'sharetype' => 'group']);
        foreach ($groupshares as $gs) {
            if (groups_is_member((int) $gs->sharevalue, $userid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * List pages and collections shared with a user.
     *
     * Checks user-level, course-level, and group-level shares.
     *
     * @param int $userid User ID.
     * @return array Associative array with keys 'pages' and 'collections',
     *               each containing arrays of stdClass records.
     */
    public static function list_shared_with_user(int $userid): array {
        global $DB;

        $pageids = [];
        $collectionids = [];

        // 1. Direct user shares.
        $usershares = $DB->get_records(self::TABLE, ['sharetype' => 'user', 'sharevalue' => (string) $userid]);
        foreach ($usershares as $s) {
            if ($s->pageid) {
                $pageids[(int) $s->pageid] = true;
            }
            if ($s->collectionid) {
                $collectionids[(int) $s->collectionid] = true;
            }
        }

        // 2. Course shares — find courses the user is enrolled in.
        $enrolledcourses = enrol_get_users_courses($userid, true, 'id');
        if ($enrolledcourses) {
            $courseids = array_keys($enrolledcourses);
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $params['sharetype'] = 'course';
            $courseshares = $DB->get_records_select(
                self::TABLE,
                "sharetype = :sharetype AND sharevalue $insql",
                $params,
            );
            foreach ($courseshares as $s) {
                if ($s->pageid) {
                    $pageids[(int) $s->pageid] = true;
                }
                if ($s->collectionid) {
                    $collectionids[(int) $s->collectionid] = true;
                }
            }
        }

        // 3. Group shares — find groups the user belongs to.
        $usergroups = groups_get_user_groups(0, $userid);
        $allgroupids = [];
        foreach ($usergroups as $coursegroupids) {
            $allgroupids = array_merge($allgroupids, $coursegroupids);
        }
        if ($allgroupids) {
            $allgroupids = array_unique($allgroupids);
            [$insql, $params] = $DB->get_in_or_equal($allgroupids, SQL_PARAMS_NAMED, 'gid');
            $params['sharetype'] = 'group';
            $groupshares = $DB->get_records_select(
                self::TABLE,
                "sharetype = :sharetype AND sharevalue $insql",
                $params,
            );
            foreach ($groupshares as $s) {
                if ($s->pageid) {
                    $pageids[(int) $s->pageid] = true;
                }
                if ($s->collectionid) {
                    $collectionids[(int) $s->collectionid] = true;
                }
            }
        }

        // Load full records, excluding anything the user owns: "shared with me"
        // means other people's items, not your own pages that you happen to have
        // shared with a course or group you are also a member of.
        $pages = [];
        if ($pageids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($pageids), SQL_PARAMS_NAMED);
            $params['owner'] = $userid;
            $pages = array_values(
                $DB->get_records_select('local_byblos_page', "id $insql AND userid <> :owner", $params)
            );
        }

        $collections = [];
        if ($collectionids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($collectionids), SQL_PARAMS_NAMED);
            $params['owner'] = $userid;
            $collections = array_values(
                $DB->get_records_select('local_byblos_collection', "id $insql AND userid <> :owner", $params)
            );
        }

        return [
            'pages'       => $pages,
            'collections' => $collections,
        ];
    }

    /**
     * List the current user's own pages and collections that carry at least one
     * share, for the "Shared by me" management view.
     *
     * @param int $userid Owner user id.
     * @return array{pages: \stdClass[], collections: \stdClass[]} Items (id, title, timecreated).
     */
    public static function list_owned_shared_items(int $userid): array {
        global $DB;

        $pages = $DB->get_records_sql(
            "SELECT DISTINCT p.id, p.title, p.timecreated
               FROM {local_byblos_page} p
               JOIN {local_byblos_share} s ON s.pageid = p.id
              WHERE p.userid = :uid AND s.pageid <> 0
           ORDER BY p.title ASC",
            ['uid' => $userid]
        );
        $collections = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.title, c.timecreated
               FROM {local_byblos_collection} c
               JOIN {local_byblos_share} s ON s.collectionid = c.id
              WHERE c.userid = :uid AND s.collectionid <> 0
           ORDER BY c.title ASC",
            ['uid' => $userid]
        );

        return ['pages' => array_values($pages), 'collections' => array_values($collections)];
    }

    /**
     * Whether a logged-in user may leave feedback on a page.
     *
     * Requires: feedback enabled on the page, the user can already view the page,
     * the user is not the owner, and the page's feedback scope admits them
     * (teachers-only, or cohort = shares the page's course/group).
     *
     * @param int $userid User ID (must be a real, logged-in user).
     * @param int $pageid Page ID.
     * @return bool
     */
    public static function can_leave_feedback(int $userid, int $pageid): bool {
        global $DB;

        if ($userid <= 0) {
            return false;
        }

        $page = $DB->get_record('local_byblos_page', ['id' => $pageid]);
        if (!$page || ($page->feedback ?? 'off') === 'off') {
            return false;
        }
        if ((int) $page->userid === $userid) {
            return false;
        }
        if (!self::can_view_page($userid, $pageid)) {
            return false;
        }

        if ($page->feedback === 'teachers') {
            return self::is_teacher_for_page($userid, $pageid);
        }

        // Cohort: the user must share the course/group the page is shared through.
        return self::shares_cohort_with_page($userid, $pageid);
    }

    /**
     * Resolve the role a feedback author gets on a page: teacher or peer.
     *
     * @param int $userid User ID.
     * @param int $pageid Page ID.
     * @return string 'teacher' or 'peer'.
     */
    public static function feedback_author_role(int $userid, int $pageid): string {
        return self::is_teacher_for_page($userid, $pageid) ? 'teacher' : 'peer';
    }

    /**
     * Whether a user counts as a "teacher" for a page's feedback: holds the
     * system viewshared capability, or mod/assign:grade on a course the page is
     * tagged to.
     *
     * @param int $userid User ID.
     * @param int $pageid Page ID.
     * @return bool
     */
    private static function is_teacher_for_page(int $userid, int $pageid): bool {
        global $DB;

        if (has_capability('local/byblos:viewshared', \context_system::instance(), $userid)) {
            return true;
        }
        $courseids = $DB->get_fieldset_select('local_byblos_page_course', 'courseid', 'pageid = ?', [$pageid]);
        foreach ($courseids as $cid) {
            $context = \context_course::instance((int) $cid, IGNORE_MISSING);
            if ($context && has_capability('mod/assign:grade', $context, $userid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a user is in the cohort a page is shared with — enrolled in a
     * course-share's course, or a member of a group-share's group.
     *
     * @param int $userid User ID.
     * @param int $pageid Page ID.
     * @return bool
     */
    private static function shares_cohort_with_page(int $userid, int $pageid): bool {
        global $DB;

        $courseshares = $DB->get_records(self::TABLE, ['pageid' => $pageid, 'sharetype' => 'course']);
        foreach ($courseshares as $cs) {
            $context = \context_course::instance((int) $cs->sharevalue, IGNORE_MISSING);
            if ($context && is_enrolled($context, $userid)) {
                return true;
            }
        }
        $groupshares = $DB->get_records(self::TABLE, ['pageid' => $pageid, 'sharetype' => 'group']);
        foreach ($groupshares as $gs) {
            if (groups_is_member((int) $gs->sharevalue, $userid)) {
                return true;
            }
        }
        return false;
    }
}
