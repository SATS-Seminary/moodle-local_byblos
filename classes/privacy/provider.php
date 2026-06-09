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
 * GDPR Privacy API implementation for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_byblos\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_byblos.
 *
 * Declares all database tables storing personal data and provides
 * export/delete functionality for GDPR compliance.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The privacy metadata collection.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_byblos_artefact',
            [
                'userid'       => 'privacy:metadata:local_byblos_artefact:userid',
                'title'        => 'privacy:metadata:local_byblos_artefact:title',
                'description'  => 'privacy:metadata:local_byblos_artefact:description',
                'content'      => 'privacy:metadata:local_byblos_artefact:content',
                'timecreated'  => 'privacy:metadata:local_byblos_artefact:timecreated',
                'timemodified' => 'privacy:metadata:local_byblos_artefact:timemodified',
            ],
            'privacy:metadata:local_byblos_artefact'
        );

        $collection->add_database_table(
            'local_byblos_page',
            [
                'userid'       => 'privacy:metadata:local_byblos_page:userid',
                'title'        => 'privacy:metadata:local_byblos_page:title',
                'description'  => 'privacy:metadata:local_byblos_page:description',
                'timecreated'  => 'privacy:metadata:local_byblos_page:timecreated',
                'timemodified' => 'privacy:metadata:local_byblos_page:timemodified',
            ],
            'privacy:metadata:local_byblos_page'
        );

        $collection->add_database_table(
            'local_byblos_collection',
            [
                'userid'       => 'privacy:metadata:local_byblos_collection:userid',
                'title'        => 'privacy:metadata:local_byblos_collection:title',
                'description'  => 'privacy:metadata:local_byblos_collection:description',
                'timecreated'  => 'privacy:metadata:local_byblos_collection:timecreated',
                'timemodified' => 'privacy:metadata:local_byblos_collection:timemodified',
            ],
            'privacy:metadata:local_byblos_collection'
        );

        $collection->add_database_table(
            'local_byblos_section',
            [
                'pageid'       => 'privacy:metadata:local_byblos_section:pageid',
                'sectiontype'  => 'privacy:metadata:local_byblos_section:sectiontype',
                'content'      => 'privacy:metadata:local_byblos_section:content',
                'configdata'   => 'privacy:metadata:local_byblos_section:configdata',
                'timecreated'  => 'privacy:metadata:local_byblos_section:timecreated',
                'timemodified' => 'privacy:metadata:local_byblos_section:timemodified',
            ],
            'privacy:metadata:local_byblos_section'
        );

        $collection->add_database_table(
            'local_byblos_collection_page',
            [
                'collectionid' => 'privacy:metadata:local_byblos_collection_page:collectionid',
                'pageid'       => 'privacy:metadata:local_byblos_collection_page:pageid',
                'sortorder'    => 'privacy:metadata:local_byblos_collection_page:sortorder',
            ],
            'privacy:metadata:local_byblos_collection_page'
        );

        $collection->add_database_table(
            'local_byblos_share',
            [
                'pageid'       => 'privacy:metadata:local_byblos_share:pageid',
                'collectionid' => 'privacy:metadata:local_byblos_share:collectionid',
                'sharetype'    => 'privacy:metadata:local_byblos_share:sharetype',
                'sharevalue'   => 'privacy:metadata:local_byblos_share:sharevalue',
                'token'        => 'privacy:metadata:local_byblos_share:token',
                'timecreated'  => 'privacy:metadata:local_byblos_share:timecreated',
            ],
            'privacy:metadata:local_byblos_share'
        );

        $collection->add_database_table(
            'local_byblos_page_course',
            [
                'pageid'      => 'privacy:metadata:local_byblos_page_course:pageid',
                'courseid'    => 'privacy:metadata:local_byblos_page_course:courseid',
                'timecreated' => 'privacy:metadata:local_byblos_page_course:timecreated',
            ],
            'privacy:metadata:local_byblos_page_course'
        );

        $collection->add_database_table(
            'local_byblos_submission',
            [
                'userid'       => 'privacy:metadata:local_byblos_submission:userid',
                'pageid'       => 'privacy:metadata:local_byblos_submission:pageid',
                'collectionid' => 'privacy:metadata:local_byblos_submission:collectionid',
                'assignmentid' => 'privacy:metadata:local_byblos_submission:assignmentid',
                'timecreated'  => 'privacy:metadata:local_byblos_submission:timecreated',
            ],
            'privacy:metadata:local_byblos_submission'
        );

        $collection->add_database_table(
            'local_byblos_goal',
            [
                'userid'       => 'privacy:metadata:local_byblos_goal:userid',
                'title'        => 'privacy:metadata:local_byblos_goal:title',
                'description'  => 'privacy:metadata:local_byblos_goal:description',
                'status'       => 'privacy:metadata:local_byblos_goal:status',
                'targetdate'   => 'privacy:metadata:local_byblos_goal:targetdate',
                'progress'     => 'privacy:metadata:local_byblos_goal:progress',
                'timecreated'  => 'privacy:metadata:local_byblos_goal:timecreated',
                'timemodified' => 'privacy:metadata:local_byblos_goal:timemodified',
            ],
            'privacy:metadata:local_byblos_goal'
        );

        $collection->add_database_table(
            'local_byblos_goal_link',
            [
                'goalid'      => 'privacy:metadata:local_byblos_goal_link:goalid',
                'linktype'    => 'privacy:metadata:local_byblos_goal_link:linktype',
                'linkid'      => 'privacy:metadata:local_byblos_goal_link:linkid',
                'timecreated' => 'privacy:metadata:local_byblos_goal_link:timecreated',
            ],
            'privacy:metadata:local_byblos_goal_link'
        );

        $collection->add_database_table(
            'local_byblos_pagefeedback',
            [
                'pageid'       => 'privacy:metadata:local_byblos_pagefeedback:pageid',
                'authorid'     => 'privacy:metadata:local_byblos_pagefeedback:authorid',
                'authorrole'   => 'privacy:metadata:local_byblos_pagefeedback:authorrole',
                'body'         => 'privacy:metadata:local_byblos_pagefeedback:body',
                'timecreated'  => 'privacy:metadata:local_byblos_pagefeedback:timecreated',
                'timemodified' => 'privacy:metadata:local_byblos_pagefeedback:timemodified',
            ],
            'privacy:metadata:local_byblos_pagefeedback'
        );

        return $collection;
    }

    /**
     * Get the list of contexts containing user data.
     *
     * All portfolio data is stored in the system context.
     *
     * @param int $userid The user ID.
     * @return contextlist The context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // All portfolio data lives in the system context.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {local_byblos_page} p ON p.userid = :userid1
                 WHERE c.contextlevel = :contextlevel1
                   AND c.instanceid = 0
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {local_byblos_artefact} a ON a.userid = :userid2
                 WHERE c.contextlevel = :contextlevel2
                   AND c.instanceid = 0
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {local_byblos_collection} col ON col.userid = :userid3
                 WHERE c.contextlevel = :contextlevel3
                   AND c.instanceid = 0
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {local_byblos_submission} s ON s.userid = :userid4
                 WHERE c.contextlevel = :contextlevel4
                   AND c.instanceid = 0
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {local_byblos_goal} g ON g.userid = :userid5
                 WHERE c.contextlevel = :contextlevel5
                   AND c.instanceid = 0
                 UNION
                SELECT c.id
                  FROM {context} c
                  JOIN {local_byblos_pagefeedback} fb ON fb.authorid = :userid6
                 WHERE c.contextlevel = :contextlevel6
                   AND c.instanceid = 0";

        $params = [
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
            'contextlevel1' => CONTEXT_SYSTEM,
            'contextlevel2' => CONTEXT_SYSTEM,
            'contextlevel3' => CONTEXT_SYSTEM,
            'contextlevel4' => CONTEXT_SYSTEM,
            'contextlevel5' => CONTEXT_SYSTEM,
            'contextlevel6' => CONTEXT_SYSTEM,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        $sql = "SELECT userid FROM {local_byblos_page}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT userid FROM {local_byblos_artefact}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT userid FROM {local_byblos_collection}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT userid FROM {local_byblos_submission}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT userid FROM {local_byblos_goal}";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT authorid FROM {local_byblos_pagefeedback}";
        $userlist->add_from_sql('authorid', $sql, []);
    }

    /**
     * Export all user data for the given approved contextlist.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = \context_system::instance();
        $subcontext = [get_string('pluginname', 'local_byblos')];

        // Export artefacts.
        $artefacts = $DB->get_records('local_byblos_artefact', ['userid' => $userid]);
        if ($artefacts) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['artefacts']),
                (object) ['artefacts' => array_values($artefacts)]
            );
        }

        // Export pages with their sections and course tags.
        $pages = $DB->get_records('local_byblos_page', ['userid' => $userid]);
        if ($pages) {
            foreach ($pages as $page) {
                $page->sections = array_values(
                    $DB->get_records('local_byblos_section', ['pageid' => $page->id], 'sortorder ASC')
                );
                $page->course_tags = array_values(
                    $DB->get_records('local_byblos_page_course', ['pageid' => $page->id])
                );
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['pages']),
                (object) ['pages' => array_values($pages)]
            );
        }

        // Export collections with page membership.
        $collections = $DB->get_records('local_byblos_collection', ['userid' => $userid]);
        if ($collections) {
            foreach ($collections as $collection) {
                $collection->pages = array_values(
                    $DB->get_records('local_byblos_collection_page', ['collectionid' => $collection->id], 'sortorder ASC')
                );
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['collections']),
                (object) ['collections' => array_values($collections)]
            );
        }

        // Export shares on the user's pages and collections.
        $shares = [];
        $pageids = $pages ? array_keys($pages) : [];
        $collids = $collections ? array_keys($collections) : [];

        if ($pageids) {
            [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
            $shares = array_merge($shares, array_values(
                $DB->get_records_select('local_byblos_share', "pageid $insql", $params)
            ));
        }
        if ($collids) {
            [$insql, $params] = $DB->get_in_or_equal($collids, SQL_PARAMS_NAMED);
            $shares = array_merge($shares, array_values(
                $DB->get_records_select('local_byblos_share', "collectionid $insql", $params)
            ));
        }
        if ($shares) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['shares']),
                (object) ['shares' => $shares]
            );
        }

        // Export submissions.
        $submissions = $DB->get_records('local_byblos_submission', ['userid' => $userid]);
        if ($submissions) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['submissions']),
                (object) ['submissions' => array_values($submissions)]
            );
        }

        // Export learning goals with their evidence links.
        $goals = $DB->get_records('local_byblos_goal', ['userid' => $userid], 'sortorder ASC');
        if ($goals) {
            foreach ($goals as $goal) {
                $goal->links = array_values(
                    $DB->get_records('local_byblos_goal_link', ['goalid' => $goal->id], 'timecreated ASC')
                );
            }
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['goals']),
                (object) ['goals' => array_values($goals)]
            );
        }

        // Export page feedback the user authored.
        $authored = $DB->get_records('local_byblos_pagefeedback', ['authorid' => $userid]);
        if ($authored) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['feedback_authored']),
                (object) ['feedback' => array_values($authored)]
            );
        }

        // Export feedback others left on the user's pages (it is data about the user's portfolio).
        if (!empty($pageids)) {
            [$insql, $params] = $DB->get_in_or_equal($pageids, SQL_PARAMS_NAMED);
            $received = array_values(
                $DB->get_records_select('local_byblos_pagefeedback', "pageid $insql", $params)
            );
            if ($received) {
                writer::with_context($context)->export_data(
                    array_merge($subcontext, ['feedback_received']),
                    (object) ['feedback' => $received]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        // Delete in dependency order.
        // Sections depend on pages.
        $pageids = $DB->get_fieldset_select('local_byblos_page', 'id', '1=1');
        if ($pageids) {
            [$insql, $params] = $DB->get_in_or_equal($pageids);
            $DB->delete_records_select('local_byblos_section', "pageid {$insql}", $params);
            $DB->delete_records_select('local_byblos_share', "pageid {$insql}", $params);
            $DB->delete_records_select('local_byblos_page_course', "pageid {$insql}", $params);
            $DB->delete_records_select('local_byblos_submission', "pageid {$insql}", $params);
        }

        $collectionids = $DB->get_fieldset_select('local_byblos_collection', 'id', '1=1');
        if ($collectionids) {
            [$insql, $params] = $DB->get_in_or_equal($collectionids);
            $DB->delete_records_select('local_byblos_collection_page', "collectionid {$insql}", $params);
            $DB->delete_records_select('local_byblos_share', "collectionid {$insql}", $params);
            $DB->delete_records_select('local_byblos_submission', "collectionid {$insql}", $params);
        }

        // Goals and their evidence links.
        $goalids = $DB->get_fieldset_select('local_byblos_goal', 'id', '1=1');
        if ($goalids) {
            [$insql, $params] = $DB->get_in_or_equal($goalids);
            $DB->delete_records_select('local_byblos_goal_link', "goalid {$insql}", $params);
        }

        $DB->delete_records('local_byblos_artefact');
        $DB->delete_records('local_byblos_page');
        $DB->delete_records('local_byblos_collection');
        $DB->delete_records('local_byblos_submission');
        $DB->delete_records('local_byblos_goal');
        $DB->delete_records('local_byblos_pagefeedback');
    }

    /**
     * Delete all user data for the specified user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            self::delete_user_portfolio_data($userid);
        }
    }

    /**
     * Delete all data for the specified users in the given context.
     *
     * @param approved_userlist $userlist The approved user list to delete for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_portfolio_data($userid);
        }
    }

    /**
     * Delete all portfolio data for a specific user.
     *
     * @param int $userid The user ID.
     * @return void
     */
    private static function delete_user_portfolio_data(int $userid): void {
        global $DB;

        // Get user's page IDs to clean up dependent records.
        $pageids = $DB->get_fieldset_select('local_byblos_page', 'id', 'userid = ?', [$userid]);
        if ($pageids) {
            [$insql, $params] = $DB->get_in_or_equal($pageids);
            $DB->delete_records_select('local_byblos_section', "pageid {$insql}", $params);
            $DB->delete_records_select('local_byblos_share', "pageid {$insql}", $params);
            $DB->delete_records_select('local_byblos_page_course', "pageid {$insql}", $params);
            // Feedback left by anyone on this user's pages.
            $DB->delete_records_select('local_byblos_pagefeedback', "pageid {$insql}", $params);
        }

        // Get user's collection IDs to clean up dependent records.
        $collectionids = $DB->get_fieldset_select('local_byblos_collection', 'id', 'userid = ?', [$userid]);
        if ($collectionids) {
            [$insql, $params] = $DB->get_in_or_equal($collectionids);
            $DB->delete_records_select('local_byblos_collection_page', "collectionid {$insql}", $params);
            $DB->delete_records_select('local_byblos_share', "collectionid {$insql}", $params);
        }

        // Goals and their evidence links.
        $goalids = $DB->get_fieldset_select('local_byblos_goal', 'id', 'userid = ?', [$userid]);
        if ($goalids) {
            [$insql, $params] = $DB->get_in_or_equal($goalids);
            $DB->delete_records_select('local_byblos_goal_link', "goalid {$insql}", $params);
        }

        // Delete primary records.
        $DB->delete_records('local_byblos_artefact', ['userid' => $userid]);
        $DB->delete_records('local_byblos_page', ['userid' => $userid]);
        $DB->delete_records('local_byblos_collection', ['userid' => $userid]);
        $DB->delete_records('local_byblos_submission', ['userid' => $userid]);
        $DB->delete_records('local_byblos_goal', ['userid' => $userid]);
        // Feedback this user authored on others' pages.
        $DB->delete_records('local_byblos_pagefeedback', ['authorid' => $userid]);
    }
}
