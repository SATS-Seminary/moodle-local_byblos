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
 * External service definitions for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_byblos_upload_image' => [
        'classname'   => \local_byblos\external\upload_external::class,
        'methodname'  => 'upload_image',
        'description' => 'Upload an image to a portfolio page from the draft area.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_add_section' => [
        'classname'    => \local_byblos\external\section_external::class,
        'methodname'   => 'add_section',
        'description'  => 'Add a new section to a portfolio page.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_update_section' => [
        'classname'    => \local_byblos\external\section_external::class,
        'methodname'   => 'update_section',
        'description'  => 'Update section configdata and content.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_delete_section' => [
        'classname'    => \local_byblos\external\section_external::class,
        'methodname'   => 'delete_section',
        'description'  => 'Delete a section from a portfolio page.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_reorder_sections' => [
        'classname'    => \local_byblos\external\section_external::class,
        'methodname'   => 'reorder_sections',
        'description'  => 'Reorder sections within a portfolio page.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_save_page_settings' => [
        'classname'    => \local_byblos\external\section_external::class,
        'methodname'   => 'save_page_settings',
        'description'  => 'Update page layout and theme settings.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    // Assessment: inline comments.

    'local_byblos_add_comment' => [
        'classname'    => \local_byblos\external\comment_external::class,
        'methodname'   => 'add_comment',
        'description'  => 'Add an inline comment to a submission (teacher or peer reviewer).',
        'type'         => 'write',
        'ajax'         => true,
    ],

    'local_byblos_update_comment' => [
        'classname'    => \local_byblos\external\comment_external::class,
        'methodname'   => 'update_comment',
        'description'  => 'Update the body of an existing comment.',
        'type'         => 'write',
        'ajax'         => true,
    ],

    'local_byblos_delete_comment' => [
        'classname'    => \local_byblos\external\comment_external::class,
        'methodname'   => 'delete_comment',
        'description'  => 'Delete a comment (author or grading teacher).',
        'type'         => 'write',
        'ajax'         => true,
    ],

    'local_byblos_list_comments' => [
        'classname'    => \local_byblos\external\comment_external::class,
        'methodname'   => 'list_comments',
        'description'  => 'List all inline comments on a submission.',
        'type'         => 'read',
        'ajax'         => true,
    ],

    // Assessment: peer review submission.

    'local_byblos_submit_peer_review' => [
        'classname'    => \local_byblos\external\peer_review_external::class,
        'methodname'   => 'submit_peer_review',
        'description'  => 'Submit a completed peer review with optional score and rubric data.',
        'type'         => 'write',
        'ajax'         => true,
    ],

    // Phase 5: advisory assessment checklist.

    'local_byblos_get_assignment_checklists' => [
        'classname'    => \local_byblos\external\checklist_external::class,
        'methodname'   => 'get_assignment_checklists',
        'description'  => 'Return advisory checklists from active byblos-enabled '
                        . 'assignments the calling user is enrolled in.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    // Artefact picker.

    'local_byblos_list_artefacts' => [
        'classname'    => \local_byblos\external\artefact_external::class,
        'methodname'   => 'list_artefacts',
        'description'  => 'List the current user\'s artefacts, optionally filtered by type or search.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    // Collection / multi-page portfolio navigation.

    'local_byblos_list_user_collections' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'list_user_collections',
        'description'  => 'List the current user\'s collections, optionally tagged with membership '
                        . 'and primary flags relative to a given page.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    'local_byblos_list_user_pages' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'list_user_pages',
        'description'  => 'List the current user\'s pages (for page-pickers), optionally excluding one page.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    'local_byblos_add_page_to_collection' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'add_page_to_collection',
        'description'  => 'Add a page to one of the current user\'s collections.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    'local_byblos_remove_page_from_collection' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'remove_page_from_collection',
        'description'  => 'Remove a page from one of the current user\'s collections.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    'local_byblos_set_primary_collection' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'set_primary_collection',
        'description'  => 'Set the primary collection for a page (used for the auto nav strip).',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    'local_byblos_create_collection' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'create_collection',
        'description'  => 'Create a collection for the current user; optionally adds a page and/or binds to a Moodle group.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    'local_byblos_list_user_groups' => [
        'classname'    => \local_byblos\external\collection_external::class,
        'methodname'   => 'list_user_groups',
        'description'  => 'List Moodle groups the current user is a member of (for group-collection creation).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    // Chart editor live preview.
    'local_byblos_render_chart_preview' => [
        'classname'    => \local_byblos\external\chart_preview_external::class,
        'methodname'   => 'render_chart_preview',
        'description'  => 'Render a chart section from raw configdata JSON (used by the live-preview pane in the editor).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    // Announcement-turnstile link generator.
    'local_byblos_list_postable_courses' => [
        'classname'    => \local_byblos\external\announce_external::class,
        'methodname'   => 'list_postable_courses',
        'description'  => 'List courses where the current user can post announcements (for the Get announcement link picker).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    // Goals: first-class learning-goal store (self-regulated-learning loop).

    'local_byblos_create_goal' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'create_goal',
        'description'  => 'Create a learning goal for the current user.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:managegoals',
    ],

    'local_byblos_update_goal' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'update_goal',
        'description'  => 'Update a learning goal (title, description, status, progress, target date).',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:managegoals',
    ],

    'local_byblos_delete_goal' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'delete_goal',
        'description'  => 'Delete a learning goal and its evidence links.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:managegoals',
    ],

    'local_byblos_reorder_goals' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'reorder_goals',
        'description'  => 'Reorder the current user\'s goals.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:managegoals',
    ],

    'local_byblos_add_goal_link' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'add_goal_link',
        'description'  => 'Link an artefact or page as evidence for a goal.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:managegoals',
    ],

    'local_byblos_remove_goal_link' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'remove_goal_link',
        'description'  => 'Remove an evidence link from a goal.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:managegoals',
    ],

    'local_byblos_list_goals' => [
        'classname'    => \local_byblos\external\goal_external::class,
        'methodname'   => 'list_goals',
        'description'  => 'List a user\'s goals (self, or a student\'s if you can view shared work).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    // Page feedback: logged-in, owner-moderated feedback on shared pages.

    'local_byblos_set_page_feedback_mode' => [
        'classname'    => \local_byblos\external\feedback_external::class,
        'methodname'   => 'set_page_feedback_mode',
        'description'  => 'Set the feedback mode (off|teachers|cohort) for one of your pages.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_add_page_feedback' => [
        'classname'    => \local_byblos\external\feedback_external::class,
        'methodname'   => 'add_page_feedback',
        'description'  => 'Leave feedback on a shared page (logged-in, scope-gated).',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:leavefeedback',
    ],

    'local_byblos_edit_page_feedback' => [
        'classname'    => \local_byblos\external\feedback_external::class,
        'methodname'   => 'edit_page_feedback',
        'description'  => 'Edit your own page-feedback comment.',
        'type'         => 'write',
        'ajax'         => true,
    ],

    'local_byblos_delete_page_feedback' => [
        'classname'    => \local_byblos\external\feedback_external::class,
        'methodname'   => 'delete_page_feedback',
        'description'  => 'Delete a page-feedback comment (author or page owner).',
        'type'         => 'write',
        'ajax'         => true,
    ],

    'local_byblos_set_feedback_visibility' => [
        'classname'    => \local_byblos\external\feedback_external::class,
        'methodname'   => 'set_feedback_visibility',
        'description'  => 'Hide or show a page-feedback comment (page owner moderation).',
        'type'         => 'write',
        'ajax'         => true,
    ],

    'local_byblos_list_page_feedback' => [
        'classname'    => \local_byblos\external\feedback_external::class,
        'methodname'   => 'list_page_feedback',
        'description'  => 'List feedback on a page (owner sees hidden rows too).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:use',
    ],

    // Outcome map: optional competency-framework import (when competencies enabled).

    'local_byblos_list_competency_frameworks' => [
        'classname'    => \local_byblos\external\competency_external::class,
        'methodname'   => 'list_competency_frameworks',
        'description'  => 'List competency frameworks (only when site competencies are enabled).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    'local_byblos_list_framework_competencies' => [
        'classname'    => \local_byblos\external\competency_external::class,
        'methodname'   => 'list_framework_competencies',
        'description'  => 'List competencies within a framework (only when site competencies are enabled).',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],

    // Reflection section: save body + recorded media (draft file area handling).

    'local_byblos_save_reflection' => [
        'classname'    => \local_byblos\external\section_external::class,
        'methodname'   => 'save_reflection',
        'description'  => 'Save a reflection section\'s heading, framework and rich body (with recorded media).',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/byblos:createpage',
    ],
];
