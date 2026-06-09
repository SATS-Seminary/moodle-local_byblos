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

/**
 * Optional competency-framework lookups for the Outcome-map section editor.
 *
 * These only return data when site competencies are enabled
 * ({@see \core_competency\api::is_enabled()}); otherwise they return an empty
 * list so the editor's "import from framework" control simply stays hidden.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competency_external extends external_api {
    /**
     * Parameters for list_competency_frameworks.
     *
     * @return external_function_parameters
     */
    public static function list_competency_frameworks_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * List competency frameworks (empty when competencies are disabled).
     *
     * @return array[]
     */
    public static function list_competency_frameworks(): array {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:createpage', $context);

        if (!\core_competency\api::is_enabled()) {
            return [];
        }
        require_capability('moodle/competency:competencyview', $context);

        $frameworks = \core_competency\api::list_frameworks('shortname', 'ASC', 0, 0, $context);
        $out = [];
        foreach ($frameworks as $fw) {
            $out[] = [
                'id'        => (int) $fw->get('id'),
                'shortname' => (string) $fw->get('shortname'),
            ];
        }
        return $out;
    }

    /**
     * Return structure for list_competency_frameworks.
     *
     * @return external_multiple_structure
     */
    public static function list_competency_frameworks_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'Framework ID'),
                'shortname' => new external_value(PARAM_TEXT, 'Framework short name'),
            ])
        );
    }

    /**
     * Parameters for list_framework_competencies.
     *
     * @return external_function_parameters
     */
    public static function list_framework_competencies_parameters(): external_function_parameters {
        return new external_function_parameters([
            'frameworkid' => new external_value(PARAM_INT, 'Competency framework ID'),
        ]);
    }

    /**
     * List the competencies within a framework (empty when disabled).
     *
     * @param int $frameworkid
     * @return array[]
     */
    public static function list_framework_competencies(int $frameworkid): array {
        self::validate_parameters(
            self::list_framework_competencies_parameters(),
            ['frameworkid' => $frameworkid]
        );
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/byblos:createpage', $context);

        if (!\core_competency\api::is_enabled()) {
            return [];
        }
        require_capability('moodle/competency:competencyview', $context);

        $competencies = \core_competency\api::list_competencies(['competencyframeworkid' => $frameworkid]);
        $out = [];
        foreach ($competencies as $comp) {
            $shortname = (string) $comp->get('shortname');
            $idnumber = (string) $comp->get('idnumber');
            $out[] = [
                'id'        => (int) $comp->get('id'),
                'shortname' => $shortname,
                'idnumber'  => $idnumber,
                'label'     => $idnumber !== '' ? ($idnumber . ' — ' . $shortname) : $shortname,
            ];
        }
        return $out;
    }

    /**
     * Return structure for list_framework_competencies.
     *
     * @return external_multiple_structure
     */
    public static function list_framework_competencies_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'        => new external_value(PARAM_INT, 'Competency ID'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency short name'),
                'idnumber'  => new external_value(PARAM_TEXT, 'Competency ID number'),
                'label'     => new external_value(PARAM_TEXT, 'Display label'),
            ])
        );
    }
}
