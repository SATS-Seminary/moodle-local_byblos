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
 * Share page behaviour: swap the value picker to match the selected share type,
 * and load the "share with a person" list one course at a time (a teacher can
 * otherwise be enrolled with thousands of people).
 *
 * @module     local_byblos/share
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    /**
     * Toggle visibility + `name` attribute so only the picker matching the
     * selected sharetype posts a sharevalue.
     *
     * @param {string} selected Current sharetype value.
     */
    function apply(selected) {
        document.querySelectorAll('.byblos-share-picker').forEach(function(group) {
            var matches = group.dataset.sharetype === selected;
            group.classList.toggle('d-none', !matches);
            var select = group.querySelector('select[data-name="sharevalue"]');
            if (!select) {
                return;
            }
            if (matches) {
                select.name = 'sharevalue';
                select.disabled = false;
            } else {
                select.removeAttribute('name');
                select.disabled = true;
            }
        });
    }

    /**
     * Build an option element.
     *
     * @param {string|number} value Option value.
     * @param {string} text Visible label.
     * @param {boolean} disabled Whether the option is selectable.
     * @return {HTMLOptionElement} The option.
     */
    function makeOption(value, text, disabled) {
        var opt = document.createElement('option');
        opt.value = value;
        opt.textContent = text;
        if (disabled) {
            opt.disabled = true;
        }
        return opt;
    }

    /**
     * Load the chosen course's participants into the person picker on demand.
     *
     * @param {string} courseid Selected course id (empty clears the list).
     * @param {HTMLSelectElement} personSelect The person select to fill.
     * @param {Array} sharedUsers User ids already shared with (shown disabled).
     * @param {Object} strings Localised labels.
     */
    function loadCourseUsers(courseid, personSelect, sharedUsers, strings) {
        personSelect.innerHTML = '';
        if (!courseid) {
            personSelect.appendChild(makeOption('', strings.pickperson || '', false));
            return;
        }
        personSelect.appendChild(makeOption('', strings.loading || '', true));

        Ajax.call([{
            methodname: 'local_byblos_get_course_users',
            args: {courseid: parseInt(courseid, 10)}
        }])[0].then(function(users) {
            personSelect.innerHTML = '';
            if (!users || !users.length) {
                personSelect.appendChild(makeOption('', strings.noneincourse || '', true));
                return users;
            }
            personSelect.appendChild(makeOption('', strings.pickperson || '', false));
            users.forEach(function(u) {
                var already = sharedUsers.indexOf(u.id) !== -1;
                var label = u.label + (already ? ' — ' + (strings.alreadyshared || '') : '');
                personSelect.appendChild(makeOption(u.id, label, already));
            });
            return users;
        }).catch(function() {
            personSelect.innerHTML = '';
            personSelect.appendChild(makeOption('', strings.noneincourse || '', true));
        });
    }

    /**
     * Copy a field's value to the clipboard, with brief icon feedback.
     *
     * @param {HTMLElement} btn The copy button (data-byblos-copy = target input id).
     */
    function copyFrom(btn) {
        var input = document.getElementById(btn.getAttribute('data-byblos-copy'));
        if (!input) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value);
        } else {
            input.select();
            document.execCommand('copy');
        }
        var icon = btn.querySelector('i');
        if (icon) {
            var prev = icon.className;
            icon.className = 'fa fa-check';
            setTimeout(function() {
                icon.className = prev;
            }, 1200);
        }
    }

    return {
        /**
         * Initialise the share page.
         *
         * @param {Object} args Object with sharedusers (number[]) and strings (Object).
         */
        init: function(args) {
            args = args || {};
            var sharedUsers = args.sharedusers || [];
            var strings = args.strings || {};

            // Copy-to-clipboard for public share links.
            document.querySelectorAll('.byblos-share-copy').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    copyFrom(btn);
                });
            });

            // User cascade: pick a course, then load only that course's people.
            var courseSelect = document.getElementById('byblos-share-user-course');
            var personSelect = document.getElementById('byblos-sharevalue-user');
            if (courseSelect && personSelect) {
                courseSelect.addEventListener('change', function() {
                    loadCourseUsers(courseSelect.value, personSelect, sharedUsers, strings);
                });
            }

            // Share-type picker: swap the value control to match the selected type.
            var typeSelect = document.getElementById('byblos-sharetype');
            if (!typeSelect) {
                return;
            }
            typeSelect.addEventListener('change', function() {
                apply(typeSelect.value);
            });
            apply(typeSelect.value);
        }
    };
});
