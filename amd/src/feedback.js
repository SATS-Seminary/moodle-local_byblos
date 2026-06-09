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
 * Page feedback: owner sets the scope; viewers post; owner moderates.
 *
 * Mutations re-load the page so the server re-renders the (access-checked)
 * feedback list; no feedback markup is ever rendered on the public-token path.
 *
 * @module     local_byblos/feedback
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
], function($, Ajax, Notification, Str) {
    'use strict';

    /** @type {number} The page id this panel belongs to. */
    var pageId = 0;

    /**
     * Call a single external function.
     *
     * @param {string} methodname
     * @param {Object} args
     * @return {Promise}
     */
    function call(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Reload the current page.
     */
    function reload() {
        window.location.reload();
    }

    return {
        /**
         * Initialise the feedback panel.
         *
         * @param {Object} args {pageid, isowner, canleave}.
         */
        init: function(args) {
            pageId = parseInt((args && args.pageid) || 0, 10);
            var root = document.querySelector('[data-byblos-feedback]');
            if (!root || !pageId) {
                return;
            }
            var $root = $(root);

            // Owner: change the feedback scope.
            $root.on('change', '.byblos-feedback-mode', function() {
                call('local_byblos_set_page_feedback_mode', {
                    pageid: pageId,
                    mode: $(this).val(),
                }).then(reload).catch(Notification.exception);
            });

            // Viewer: post feedback.
            $root.on('click', '.byblos-feedback-submit', function() {
                var input = $root.find('.byblos-feedback-input');
                var body = $.trim(input.val());
                if (!body) {
                    return;
                }
                call('local_byblos_add_page_feedback', {pageid: pageId, body: body})
                    .then(reload).catch(Notification.exception);
            });

            // Owner: hide a comment.
            $root.on('click', '.byblos-feedback-hide', function() {
                var id = parseInt($(this).closest('.byblos-feedback-item').data('feedbackid'), 10);
                call('local_byblos_set_feedback_visibility', {id: id, visible: false})
                    .then(reload).catch(Notification.exception);
            });

            // Owner: show a hidden comment.
            $root.on('click', '.byblos-feedback-show', function() {
                var id = parseInt($(this).closest('.byblos-feedback-item').data('feedbackid'), 10);
                call('local_byblos_set_feedback_visibility', {id: id, visible: true})
                    .then(reload).catch(Notification.exception);
            });

            // Author or owner: delete a comment.
            $root.on('click', '.byblos-feedback-delete', function() {
                var id = parseInt($(this).closest('.byblos-feedback-item').data('feedbackid'), 10);
                Str.get_strings([
                    {key: 'feedback_delete_confirm', component: 'local_byblos'},
                    {key: 'delete', component: 'core'},
                    {key: 'cancel', component: 'core'},
                ]).then(function(s) {
                    return Notification.confirm(s[1], s[0], s[1], s[2], function() {
                        call('local_byblos_delete_page_feedback', {id: id})
                            .then(reload).catch(Notification.exception);
                    });
                }).catch(Notification.exception);
            });
        }
    };
});
