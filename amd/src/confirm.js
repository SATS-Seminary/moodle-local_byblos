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
 * Confirm-before-submit for destructive/state-changing forms.
 *
 * Replaces inline `onsubmit="return confirm(...)"` handlers: any
 * `<form data-byblos-confirm="message">` is intercepted and a modal is shown,
 * submitting only on confirmation.
 *
 * @module     local_byblos/confirm
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/str',
    'core/notification',
    'core/modal_save_cancel',
    'core/modal_events',
], function(Str, Notification, ModalSaveCancel, ModalEvents) {
    'use strict';

    /**
     * Show a confirm modal, then run the callback if the user confirms.
     *
     * @param {string} message The confirmation question.
     * @param {Function} onConfirm Called when the user confirms.
     */
    function confirmThen(message, onConfirm) {
        Str.get_strings([
            {key: 'confirm', component: 'core'},
            {key: 'yes', component: 'core'},
        ]).then(function(s) {
            return ModalSaveCancel.create({
                title: s[0],
                body: message,
                buttons: {save: s[1]},
            });
        }).then(function(modal) {
            modal.getRoot().on(ModalEvents.save, function() {
                onConfirm();
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    return {
        /**
         * Wire the delegated submit handler.
         */
        init: function() {
            document.addEventListener('submit', function(e) {
                var form = e.target;
                if (!form || !form.matches || !form.matches('form[data-byblos-confirm]')) {
                    return;
                }
                if (form.dataset.byblosConfirmed) {
                    return;
                }
                e.preventDefault();
                confirmThen(form.getAttribute('data-byblos-confirm'), function() {
                    // Programmatic submit bypasses the submit event, so it posts directly.
                    form.dataset.byblosConfirmed = '1';
                    form.submit();
                });
            }, true);
        }
    };
});
