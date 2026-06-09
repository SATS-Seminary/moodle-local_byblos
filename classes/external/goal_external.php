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

namespace local_byblos\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use context_system;
use local_byblos\goal;

/**
 * External functions for learning-goal CRUD.
 *
 * Authorisation model:
 *  - A user creates and manages their OWN goals (goal.userid === $USER->id), or
 *    a manager with local/byblos:manageall may act on any goal.
 *  - list_goals additionally lets a teacher with local/byblos:viewshared READ a
 *    student's goals.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class goal_external extends external_api {
    /**
     * Validate the system context and that the caller owns the given goal.
     *
     * @param int $goalid
     * @return \stdClass The goal record.
     * @throws \moodle_exception
     */
    private static function require_goal_owner(int $goalid): \stdClass {
        global $USER;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:managegoals', $context);

        $goal = goal::get($goalid);
        if (!$goal) {
            throw new \moodle_exception('error:goalnotfound', 'local_byblos');
        }
        if (
            (int) $goal->userid !== (int) $USER->id
                && !has_capability('local/byblos:manageall', $context)
        ) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }
        return $goal;
    }

    /**
     * Trigger a goal lifecycle event.
     *
     * @param string $eventclass Short event class name (goal_created|goal_updated|goal_deleted).
     * @param int    $goalid
     * @return void
     */
    private static function trigger_goal_event(string $eventclass, int $goalid): void {
        $class = "\\local_byblos\\event\\{$eventclass}";
        $event = $class::create([
            'objectid' => $goalid,
            'context'  => context_system::instance(),
        ]);
        $event->trigger();
    }

    /**
     * Parameters for create_goal.
     *
     * @return external_function_parameters
     */
    public static function create_goal_parameters(): external_function_parameters {
        return new external_function_parameters([
            'title'       => new external_value(PARAM_TEXT, 'Goal title'),
            'description' => new external_value(PARAM_RAW, 'Goal description', VALUE_DEFAULT, ''),
            'targetdate'  => new external_value(PARAM_INT, 'Target date (unix ts, 0 = none)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Create a goal for the current user.
     *
     * @param string $title
     * @param string $description
     * @param int    $targetdate
     * @return array{id:int}
     */
    public static function create_goal(string $title, string $description = '', int $targetdate = 0): array {
        global $USER;

        self::validate_parameters(self::create_goal_parameters(), [
            'title' => $title, 'description' => $description, 'targetdate' => $targetdate,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:managegoals', $context);

        $title = trim($title);
        if ($title === '') {
            throw new \moodle_exception('error:goaltitlerequired', 'local_byblos');
        }

        $id = goal::create(
            (int) $USER->id,
            $title,
            $description !== '' ? $description : null,
            $targetdate ?: null
        );
        self::trigger_goal_event('goal_created', $id);
        return ['id' => $id];
    }

    /**
     * Return structure for create_goal.
     *
     * @return external_single_structure
     */
    public static function create_goal_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New goal ID'),
        ]);
    }

    /**
     * Parameters for update_goal.
     *
     * @return external_function_parameters
     */
    public static function update_goal_parameters(): external_function_parameters {
        return new external_function_parameters([
            'goalid'      => new external_value(PARAM_INT, 'Goal ID'),
            'title'       => new external_value(PARAM_TEXT, 'Goal title', VALUE_DEFAULT, null),
            'description' => new external_value(PARAM_RAW, 'Goal description', VALUE_DEFAULT, null),
            'status'      => new external_value(PARAM_ALPHA, 'active|achieved|archived', VALUE_DEFAULT, null),
            'progress'    => new external_value(PARAM_INT, 'Progress 0-100', VALUE_DEFAULT, null),
            'targetdate'  => new external_value(PARAM_INT, 'Target date (unix ts, 0 = clear)', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Update a goal owned by the current user.
     *
     * @param int         $goalid
     * @param string|null $title
     * @param string|null $description
     * @param string|null $status
     * @param int|null    $progress
     * @param int|null    $targetdate
     * @return array{success:bool}
     */
    public static function update_goal(
        int $goalid,
        ?string $title = null,
        ?string $description = null,
        ?string $status = null,
        ?int $progress = null,
        ?int $targetdate = null
    ): array {
        self::validate_parameters(self::update_goal_parameters(), [
            'goalid' => $goalid, 'title' => $title, 'description' => $description,
            'status' => $status, 'progress' => $progress, 'targetdate' => $targetdate,
        ]);
        self::require_goal_owner($goalid);

        $data = [];
        if ($title !== null) {
            $title = trim($title);
            if ($title === '') {
                throw new \moodle_exception('error:goaltitlerequired', 'local_byblos');
            }
            $data['title'] = $title;
        }
        if ($description !== null) {
            $data['description'] = $description !== '' ? $description : null;
        }
        if ($status !== null) {
            if (!in_array($status, goal::STATUSES, true)) {
                throw new \moodle_exception('error:invalidgoalstatus', 'local_byblos');
            }
            $data['status'] = $status;
        }
        if ($progress !== null) {
            $data['progress'] = max(0, min(100, $progress));
        }
        if ($targetdate !== null) {
            $data['targetdate'] = $targetdate ?: null;
        }

        goal::update($goalid, $data);
        self::trigger_goal_event('goal_updated', $goalid);
        return ['success' => true];
    }

    /**
     * Return structure for update_goal.
     *
     * @return external_single_structure
     */
    public static function update_goal_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for delete_goal.
     *
     * @return external_function_parameters
     */
    public static function delete_goal_parameters(): external_function_parameters {
        return new external_function_parameters([
            'goalid' => new external_value(PARAM_INT, 'Goal ID'),
        ]);
    }

    /**
     * Delete a goal owned by the current user.
     *
     * @param int $goalid
     * @return array{success:bool}
     */
    public static function delete_goal(int $goalid): array {
        self::validate_parameters(self::delete_goal_parameters(), ['goalid' => $goalid]);
        self::require_goal_owner($goalid);

        goal::delete($goalid);
        self::trigger_goal_event('goal_deleted', $goalid);
        return ['success' => true];
    }

    /**
     * Return structure for delete_goal.
     *
     * @return external_single_structure
     */
    public static function delete_goal_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for reorder_goals.
     *
     * @return external_function_parameters
     */
    public static function reorder_goals_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ordering' => new external_value(PARAM_RAW, 'JSON array of {goalid, sortorder}'),
        ]);
    }

    /**
     * Reorder the current user's goals.
     *
     * @param string $ordering JSON array of {goalid, sortorder}.
     * @return array{success:bool}
     */
    public static function reorder_goals(string $ordering): array {
        self::validate_parameters(self::reorder_goals_parameters(), ['ordering' => $ordering]);
        self::validate_context(context_system::instance());
        require_capability('local/byblos:managegoals', context_system::instance());

        $decoded = json_decode($ordering, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('error:invalidjson', 'local_byblos');
        }

        $map = [];
        foreach ($decoded as $row) {
            $goalid = (int) ($row['goalid'] ?? 0);
            // Ownership of each goal is enforced here; mixed-owner payloads are rejected.
            self::require_goal_owner($goalid);
            $map[$goalid] = (int) ($row['sortorder'] ?? 0);
        }
        goal::reorder($map);
        return ['success' => true];
    }

    /**
     * Return structure for reorder_goals.
     *
     * @return external_single_structure
     */
    public static function reorder_goals_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for add_goal_link.
     *
     * @return external_function_parameters
     */
    public static function add_goal_link_parameters(): external_function_parameters {
        return new external_function_parameters([
            'goalid'   => new external_value(PARAM_INT, 'Goal ID'),
            'linktype' => new external_value(PARAM_ALPHA, 'artefact|page'),
            'linkid'   => new external_value(PARAM_INT, 'Artefact or page ID'),
        ]);
    }

    /**
     * Link an artefact or page (owned by the goal owner) as evidence.
     *
     * @param int    $goalid
     * @param string $linktype
     * @param int    $linkid
     * @return array{id:int}
     */
    public static function add_goal_link(int $goalid, string $linktype, int $linkid): array {
        global $DB;

        self::validate_parameters(self::add_goal_link_parameters(), [
            'goalid' => $goalid, 'linktype' => $linktype, 'linkid' => $linkid,
        ]);
        $goal = self::require_goal_owner($goalid);

        if (!in_array($linktype, goal::LINKTYPES, true)) {
            throw new \moodle_exception('error:invalidlinktype', 'local_byblos');
        }

        // The linked evidence must belong to the goal owner.
        $table = $linktype === 'artefact' ? 'local_byblos_artefact' : 'local_byblos_page';
        $evidence = $DB->get_record($table, ['id' => $linkid], 'id, userid');
        if (!$evidence || (int) $evidence->userid !== (int) $goal->userid) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        $id = goal::add_link($goalid, $linktype, $linkid);
        return ['id' => $id];
    }

    /**
     * Return structure for add_goal_link.
     *
     * @return external_single_structure
     */
    public static function add_goal_link_returns(): external_single_structure {
        return new external_single_structure(['id' => new external_value(PARAM_INT, 'Link ID')]);
    }

    /**
     * Parameters for remove_goal_link.
     *
     * @return external_function_parameters
     */
    public static function remove_goal_link_parameters(): external_function_parameters {
        return new external_function_parameters([
            'goalid'   => new external_value(PARAM_INT, 'Goal ID'),
            'linktype' => new external_value(PARAM_ALPHA, 'artefact|page'),
            'linkid'   => new external_value(PARAM_INT, 'Artefact or page ID'),
        ]);
    }

    /**
     * Remove an evidence link from a goal.
     *
     * @param int    $goalid
     * @param string $linktype
     * @param int    $linkid
     * @return array{success:bool}
     */
    public static function remove_goal_link(int $goalid, string $linktype, int $linkid): array {
        self::validate_parameters(self::remove_goal_link_parameters(), [
            'goalid' => $goalid, 'linktype' => $linktype, 'linkid' => $linkid,
        ]);
        self::require_goal_owner($goalid);

        goal::remove_link($goalid, $linktype, $linkid);
        return ['success' => true];
    }

    /**
     * Return structure for remove_goal_link.
     *
     * @return external_single_structure
     */
    public static function remove_goal_link_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for list_goals.
     *
     * @return external_function_parameters
     */
    public static function list_goals_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID whose goals to list'),
        ]);
    }

    /**
     * List a user's goals with resolved evidence links.
     *
     * @param int $userid
     * @return array[] Goal rows.
     */
    public static function list_goals(int $userid): array {
        global $USER;

        self::validate_parameters(self::list_goals_parameters(), ['userid' => $userid]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:use', $context);

        // A non-positive userid means "the current user" (self-service pickers).
        if ($userid <= 0) {
            $userid = (int) $USER->id;
        }

        if (
            (int) $userid !== (int) $USER->id
                && !has_capability('local/byblos:viewshared', $context)
        ) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        $goals = goal::list_by_user($userid);
        return array_map(static function (\stdClass $g): array {
            return [
                'id'          => (int) $g->id,
                'title'       => $g->title,
                'description' => $g->description ?? '',
                'status'      => $g->status,
                'progress'    => (int) $g->progress,
                'targetdate'  => (int) ($g->targetdate ?? 0),
                'links'       => goal::get_links((int) $g->id),
            ];
        }, $goals);
    }

    /**
     * Return structure for list_goals.
     *
     * @return external_multiple_structure
     */
    public static function list_goals_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'          => new external_value(PARAM_INT, 'Goal ID'),
                'title'       => new external_value(PARAM_TEXT, 'Title'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'status'      => new external_value(PARAM_ALPHA, 'Status'),
                'progress'    => new external_value(PARAM_INT, 'Progress 0-100'),
                'targetdate'  => new external_value(PARAM_INT, 'Target date (unix ts, 0 = none)'),
                'links'       => new external_multiple_structure(
                    new external_single_structure([
                        'type'  => new external_value(PARAM_ALPHA, 'artefact|page'),
                        'id'    => new external_value(PARAM_INT, 'Evidence ID'),
                        'title' => new external_value(PARAM_TEXT, 'Evidence title'),
                        'url'   => new external_value(PARAM_URL, 'Evidence URL'),
                    ])
                ),
            ])
        );
    }
}
