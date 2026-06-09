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
use local_byblos\feedback;
use local_byblos\page;
use local_byblos\share;

/**
 * External functions for logged-in, owner-moderated page feedback.
 *
 * Every method runs in the system context. The unauthenticated public-token
 * path ({@see publicview.php}) never reaches these functions, which guarantees
 * that anonymous viewers cannot leave or read feedback through this API.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_external extends external_api {
    /** @var string[] Valid feedback modes. */
    private const MODES = ['off', 'teachers', 'cohort'];

    /**
     * Load a page and assert the current user owns it (or holds manageall).
     *
     * @param int $pageid
     * @return \stdClass The page record.
     * @throws \moodle_exception
     */
    private static function require_page_owner(int $pageid): \stdClass {
        global $USER;

        $page = page::get($pageid);
        if (!$page) {
            throw new \moodle_exception('error:pagenotfound', 'local_byblos');
        }
        if (
            (int) $page->userid !== (int) $USER->id
                && !has_capability('local/byblos:manageall', context_system::instance())
        ) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }
        return $page;
    }

    /**
     * Parameters for set_page_feedback_mode.
     *
     * @return external_function_parameters
     */
    public static function set_page_feedback_mode_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
            'mode'   => new external_value(PARAM_ALPHA, 'off|teachers|cohort'),
        ]);
    }

    /**
     * Set the feedback mode for one of the caller's pages.
     *
     * @param int    $pageid
     * @param string $mode
     * @return array{success:bool}
     */
    public static function set_page_feedback_mode(int $pageid, string $mode): array {
        self::validate_parameters(self::set_page_feedback_mode_parameters(), [
            'pageid' => $pageid, 'mode' => $mode,
        ]);
        self::validate_context(context_system::instance());

        if (!in_array($mode, self::MODES, true)) {
            throw new \moodle_exception('error:invalidfeedbackmode', 'local_byblos');
        }
        self::require_page_owner($pageid);

        page::update($pageid, ['feedback' => $mode]);
        return ['success' => true];
    }

    /**
     * Return structure for set_page_feedback_mode.
     *
     * @return external_single_structure
     */
    public static function set_page_feedback_mode_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for add_page_feedback.
     *
     * @return external_function_parameters
     */
    public static function add_page_feedback_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
            'body'   => new external_value(PARAM_RAW, 'Feedback body (plain text)'),
        ]);
    }

    /**
     * Leave feedback on a shared page (scope-gated, logged-in only).
     *
     * @param int    $pageid
     * @param string $body
     * @return array{id:int, authorrole:string}
     */
    public static function add_page_feedback(int $pageid, string $body): array {
        global $USER;

        self::validate_parameters(self::add_page_feedback_parameters(), [
            'pageid' => $pageid, 'body' => $body,
        ]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:leavefeedback', $context);

        if (!share::can_leave_feedback((int) $USER->id, $pageid)) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        $body = trim($body);
        if ($body === '') {
            throw new \moodle_exception('error:feedbackempty', 'local_byblos');
        }

        $role = share::feedback_author_role((int) $USER->id, $pageid);
        $id = feedback::create($pageid, (int) $USER->id, $role, $body);

        $event = \local_byblos\event\page_feedback_left::create([
            'objectid' => $id,
            'context'  => $context,
            'other'    => ['pageid' => $pageid],
        ]);
        $event->trigger();

        return ['id' => $id, 'authorrole' => $role];
    }

    /**
     * Return structure for add_page_feedback.
     *
     * @return external_single_structure
     */
    public static function add_page_feedback_returns(): external_single_structure {
        return new external_single_structure([
            'id'         => new external_value(PARAM_INT, 'New feedback ID'),
            'authorrole' => new external_value(PARAM_ALPHA, 'teacher|peer'),
        ]);
    }

    /**
     * Parameters for edit_page_feedback.
     *
     * @return external_function_parameters
     */
    public static function edit_page_feedback_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id'   => new external_value(PARAM_INT, 'Feedback ID'),
            'body' => new external_value(PARAM_RAW, 'New body'),
        ]);
    }

    /**
     * Edit one's own feedback.
     *
     * @param int    $id
     * @param string $body
     * @return array{success:bool}
     */
    public static function edit_page_feedback(int $id, string $body): array {
        global $USER;

        self::validate_parameters(self::edit_page_feedback_parameters(), ['id' => $id, 'body' => $body]);
        self::validate_context(context_system::instance());

        $row = feedback::get($id);
        if (!$row) {
            throw new \moodle_exception('error:feedbacknotfound', 'local_byblos');
        }
        if ((int) $row->authorid !== (int) $USER->id) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        $body = trim($body);
        if ($body === '') {
            throw new \moodle_exception('error:feedbackempty', 'local_byblos');
        }
        feedback::update_body($id, $body);
        return ['success' => true];
    }

    /**
     * Return structure for edit_page_feedback.
     *
     * @return external_single_structure
     */
    public static function edit_page_feedback_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for delete_page_feedback.
     *
     * @return external_function_parameters
     */
    public static function delete_page_feedback_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Feedback ID'),
        ]);
    }

    /**
     * Delete feedback (the author, or the page owner moderating, or manageall).
     *
     * @param int $id
     * @return array{success:bool}
     */
    public static function delete_page_feedback(int $id): array {
        global $USER;

        self::validate_parameters(self::delete_page_feedback_parameters(), ['id' => $id]);
        $context = context_system::instance();
        self::validate_context($context);

        $row = feedback::get($id);
        if (!$row) {
            throw new \moodle_exception('error:feedbacknotfound', 'local_byblos');
        }

        $isauthor = ((int) $row->authorid === (int) $USER->id);
        $isowner = false;
        if (!$isauthor) {
            $page = page::get((int) $row->pageid);
            $isowner = $page && (int) $page->userid === (int) $USER->id;
        }
        if (!$isauthor && !$isowner && !has_capability('local/byblos:manageall', $context)) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        feedback::delete($id);
        return ['success' => true];
    }

    /**
     * Return structure for delete_page_feedback.
     *
     * @return external_single_structure
     */
    public static function delete_page_feedback_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for set_feedback_visibility.
     *
     * @return external_function_parameters
     */
    public static function set_feedback_visibility_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id'      => new external_value(PARAM_INT, 'Feedback ID'),
            'visible' => new external_value(PARAM_BOOL, 'Visible flag'),
        ]);
    }

    /**
     * Show/hide a feedback row — page-owner moderation.
     *
     * @param int  $id
     * @param bool $visible
     * @return array{success:bool}
     */
    public static function set_feedback_visibility(int $id, bool $visible): array {
        self::validate_parameters(self::set_feedback_visibility_parameters(), [
            'id' => $id, 'visible' => $visible,
        ]);
        self::validate_context(context_system::instance());

        $row = feedback::get($id);
        if (!$row) {
            throw new \moodle_exception('error:feedbacknotfound', 'local_byblos');
        }
        self::require_page_owner((int) $row->pageid);

        feedback::set_visible($id, (bool) $visible);
        return ['success' => true];
    }

    /**
     * Return structure for set_feedback_visibility.
     *
     * @return external_single_structure
     */
    public static function set_feedback_visibility_returns(): external_single_structure {
        return new external_single_structure(['success' => new external_value(PARAM_BOOL, 'Success')]);
    }

    /**
     * Parameters for list_page_feedback.
     *
     * @return external_function_parameters
     */
    public static function list_page_feedback_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pageid' => new external_value(PARAM_INT, 'Page ID'),
        ]);
    }

    /**
     * List feedback on a page. The owner sees hidden rows; others see visible
     * rows only, and only if they can view the page.
     *
     * @param int $pageid
     * @return array[]
     */
    public static function list_page_feedback(int $pageid): array {
        global $USER, $DB;

        self::validate_parameters(self::list_page_feedback_parameters(), ['pageid' => $pageid]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:use', $context);

        $page = page::get($pageid);
        if (!$page) {
            throw new \moodle_exception('error:pagenotfound', 'local_byblos');
        }

        $isowner = ((int) $page->userid === (int) $USER->id);
        if (!$isowner && !share::can_view_page((int) $USER->id, $pageid)) {
            throw new \moodle_exception('error:nopermission', 'local_byblos');
        }

        $rows = feedback::list_for_page($pageid, $isowner);
        $out = [];
        foreach ($rows as $row) {
            $author = $DB->get_record('user', ['id' => $row->authorid]);
            $out[] = [
                'id'          => (int) $row->id,
                'authorid'    => (int) $row->authorid,
                'authorname'  => $author ? fullname($author) : '',
                'authorrole'  => $row->authorrole,
                'body'        => $row->body,
                'visible'     => (int) $row->visible,
                'iscurrentuser' => ((int) $row->authorid === (int) $USER->id),
                'timecreated' => (int) $row->timecreated,
                'timecreatedstr' => userdate((int) $row->timecreated),
            ];
        }
        return $out;
    }

    /**
     * Return structure for list_page_feedback.
     *
     * @return external_multiple_structure
     */
    public static function list_page_feedback_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'             => new external_value(PARAM_INT, 'Feedback ID'),
                'authorid'       => new external_value(PARAM_INT, 'Author user ID'),
                'authorname'     => new external_value(PARAM_TEXT, 'Author full name'),
                'authorrole'     => new external_value(PARAM_ALPHA, 'teacher|peer'),
                'body'           => new external_value(PARAM_RAW, 'Feedback body'),
                'visible'        => new external_value(PARAM_INT, 'Visible flag'),
                'iscurrentuser'  => new external_value(PARAM_BOOL, 'Authored by the caller'),
                'timecreated'    => new external_value(PARAM_INT, 'Created timestamp'),
                'timecreatedstr' => new external_value(PARAM_TEXT, 'Created, formatted'),
            ])
        );
    }
}
