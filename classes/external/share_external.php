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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/enrollib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web services backing the share dialog.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class share_external extends external_api {
    /**
     * Parameters for get_course_users.
     *
     * @return external_function_parameters
     */
    public static function get_course_users_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course to list participants from'),
        ]);
    }

    /**
     * List the participants of one course the sharer belongs to, so the share
     * dialog can offer a manageable, course-scoped list instead of every person
     * across every course at once. Names only (no email) to limit exposure.
     *
     * @param int $courseid The course id.
     * @return array[] List of ['id' => int, 'label' => string].
     */
    public static function get_course_users(int $courseid): array {
        global $USER;

        $params = self::validate_parameters(self::get_course_users_parameters(), ['courseid' => $courseid]);

        $systemcontext = \context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/byblos:share', $systemcontext);

        $coursecontext = \context_course::instance($params['courseid'], MUST_EXIST);

        // The sharer may only enumerate participants of a course they belong to
        // (managers excepted). This mirrors the course list the dialog offers.
        $ismanager = has_capability('local/byblos:manageall', $systemcontext);
        if (!$ismanager && !is_enrolled($coursecontext, $USER->id, '', true)) {
            throw new \moodle_exception('accessdenied', 'local_byblos');
        }

        $fields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';
        $users = [];
        foreach (get_enrolled_users($coursecontext, '', 0, $fields, null, 0, 0, true) as $u) {
            if ((int) $u->id === (int) $USER->id) {
                continue;
            }
            $users[] = [
                'id'    => (int) $u->id,
                'label' => fullname($u),
            ];
        }

        usort($users, static fn($a, $b) => strcmp(
            \core_text::strtolower($a['label']),
            \core_text::strtolower($b['label'])
        ));

        return $users;
    }

    /**
     * Return definition for get_course_users.
     *
     * @return external_multiple_structure
     */
    public static function get_course_users_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'    => new external_value(PARAM_INT, 'User id'),
                'label' => new external_value(PARAM_NOTAGS, 'Display name'),
            ])
        );
    }
}
