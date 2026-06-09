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
 * Database upgrade steps for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps.
 *
 * @param int $oldversion The previously installed version.
 * @return bool
 */
function xmldb_local_byblos_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026041700) {
        // Extend local_byblos_submission with assignsubmissionid, snapshotmode, snapshotid, timemodified.
        $table = new xmldb_table('local_byblos_submission');

        $field = new xmldb_field('assignsubmissionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'assignmentid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'snapshotmode',
            XMLDB_TYPE_CHAR,
            '30',
            null,
            XMLDB_NOTNULL,
            null,
            'snapshot_on_submit',
            'userid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('snapshotid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'snapshotmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('ix_assignsubmissionid', XMLDB_INDEX_NOTUNIQUE, ['assignsubmissionid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Create local_byblos_snapshot.
        $table = new xmldb_table('local_byblos_snapshot');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('capturedjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_byblos_page', ['id']);
            $dbman->create_table($table);
        }

        // Create local_byblos_comment.
        $table = new xmldb_table('local_byblos_comment');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('anchorkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
            $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'teacher');
            $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key(
                'fk_submissionid',
                XMLDB_KEY_FOREIGN,
                ['submissionid'],
                'local_byblos_submission',
                ['id']
            );
            $table->add_key('fk_authorid', XMLDB_KEY_FOREIGN, ['authorid'], 'user', ['id']);
            $table->add_index('ix_submissionid_anchor', XMLDB_INDEX_NOTUNIQUE, ['submissionid', 'anchorkey']);
            $dbman->create_table($table);
        }

        // Create local_byblos_peer_assignment.
        $table = new xmldb_table('local_byblos_peer_assignment');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('assignmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('revieweeuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('submissionid', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('advisoryscore', XMLDB_TYPE_NUMBER, '6, 2', null, null);
            $table->add_field('timeassigned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_reviewerid', XMLDB_KEY_FOREIGN, ['reviewerid'], 'user', ['id']);
            $table->add_key('fk_revieweeuserid', XMLDB_KEY_FOREIGN, ['revieweeuserid'], 'user', ['id']);
            $table->add_key(
                'fk_submissionid',
                XMLDB_KEY_FOREIGN,
                ['submissionid'],
                'local_byblos_submission',
                ['id']
            );
            $table->add_index('ix_assignmentid_reviewer', XMLDB_INDEX_NOTUNIQUE, ['assignmentid', 'reviewerid']);
            $table->add_index('ix_assignmentid_reviewee', XMLDB_INDEX_NOTUNIQUE, ['assignmentid', 'revieweeuserid']);
            $table->add_index(
                'uix_assign_reviewer_reviewee',
                XMLDB_INDEX_UNIQUE,
                ['assignmentid', 'reviewerid', 'revieweeuserid']
            );
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041700, 'local', 'byblos');
    }

    if ($oldversion < 2026041800) {
        // Drop the unused local_byblos_submission.status column — it was always
        // written as 'submitted' and never read by any business logic.
        $table = new xmldb_table('local_byblos_submission');
        $field = new xmldb_field('status');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026041800, 'local', 'byblos');
    }

    if ($oldversion < 2026041801) {
        // Add rubricdata column to local_byblos_peer_assignment for rubric-mode reviews.
        $table = new xmldb_table('local_byblos_peer_assignment');
        $field = new xmldb_field('rubricdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'advisoryscore');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026041801, 'local', 'byblos');
    }

    if ($oldversion < 2026042000) {
        // Add is_primary flag to local_byblos_collection_page so a page can designate
        // one of its collections as the "primary" one (used for Layer 1 nav strip).
        $table = new xmldb_table('local_byblos_collection_page');
        $field = new xmldb_field('is_primary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026042000, 'local', 'byblos');
    }

    if ($oldversion < 2026042100) {
        // Add groupid column to local_byblos_collection for Moodle-group-bound collections.
        $table = new xmldb_table('local_byblos_collection');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('ix_groupid', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026042100, 'local', 'byblos');
    }

    if ($oldversion < 2026060701) {
        // Create local_byblos_goal (first-class learning-goal store).
        $table = new xmldb_table('local_byblos_goal');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('targetdate', XMLDB_TYPE_INTEGER, '10', null, null);
            $table->add_field('progress', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('ix_userid_status', XMLDB_INDEX_NOTUNIQUE, ['userid', 'status']);
            $table->add_index('ix_userid_sort', XMLDB_INDEX_NOTUNIQUE, ['userid', 'sortorder']);
            $dbman->create_table($table);
        }

        // Create local_byblos_goal_link (evidence citations for a goal).
        $table = new xmldb_table('local_byblos_goal_link');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('goalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('linktype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table->add_field('linkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_goalid', XMLDB_KEY_FOREIGN, ['goalid'], 'local_byblos_goal', ['id']);
            $table->add_index('uix_goal_link', XMLDB_INDEX_UNIQUE, ['goalid', 'linktype', 'linkid']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060701, 'local', 'byblos');
    }

    if ($oldversion < 2026060702) {
        // Add feedback-mode column to local_byblos_page.
        $table = new xmldb_table('local_byblos_page');
        $field = new xmldb_field('feedback', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'off', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create local_byblos_pagefeedback (logged-in, owner-moderated page feedback).
        $table = new xmldb_table('local_byblos_pagefeedback');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('pageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('authorrole', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'peer');
            $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_pageid', XMLDB_KEY_FOREIGN, ['pageid'], 'local_byblos_page', ['id']);
            $table->add_key('fk_authorid', XMLDB_KEY_FOREIGN, ['authorid'], 'user', ['id']);
            $table->add_index('ix_pageid_visible', XMLDB_INDEX_NOTUNIQUE, ['pageid', 'visible']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026060702, 'local', 'byblos');
    }

    if ($oldversion < 2026060904) {
        // Per-section column width (full / half / third) for page layout.
        $table = new xmldb_table('local_byblos_section');
        $field = new xmldb_field('width', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'full', 'content');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026060904, 'local', 'byblos');
    }

    return true;
}
