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
 * Dashboard Goals tab: create, edit, delete and link evidence to learning goals.
 *
 * Mutations re-load the goals tab so the server re-renders the cards; this keeps
 * the rendering logic in one place (the mustache template).
 *
 * @module     local_byblos/goals
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/modal_save_cancel',
    'core/modal_events',
], function($, Ajax, Notification, Str, ModalSaveCancel, ModalEvents) {
    'use strict';

    /** @type {number} The current user id (whose goals are shown). */
    var userId = 0;

    /** @type {Array} Quick-start goal templates ({key, title, description, practice}). */
    var goalTemplates = [];

    /**
     * Reload onto the goals tab so the server re-renders the cards.
     */
    function reloadGoalsTab() {
        window.location.search = '?tab=goals';
    }

    /**
     * Escape a value for safe insertion into an HTML attribute/text node.
     *
     * @param {string} value
     * @return {string}
     */
    function esc(value) {
        return $('<div/>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    /**
     * Call a single external function and return its promise.
     *
     * @param {string} methodname
     * @param {Object} args
     * @return {Promise}
     */
    function call(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Build the goal create/edit form HTML.
     *
     * @param {Object} goal Existing goal values (empty object for create).
     * @param {Object} strings Localised labels.
     * @param {Array} templates Quick-start templates to offer (create mode only).
     * @return {string}
     */
    function goalFormHtml(goal, strings, templates) {
        var target = '';
        if (goal.targetdate) {
            // Convert unix seconds to yyyy-mm-dd for the date input.
            var d = new Date(goal.targetdate * 1000);
            target = d.toISOString().slice(0, 10);
        }
        var statusOpts = ['active', 'achieved', 'archived'].map(function(s) {
            var sel = (goal.status || 'active') === s ? ' selected' : '';
            return '<option value="' + s + '"' + sel + '>' + esc(strings['status_' + s]) + '</option>';
        }).join('');
        // "Start from a template" picker, shown only when creating a goal.
        var picker = '';
        if (templates && templates.length) {
            var opts = '<option value="">' + esc(strings.startblank) + '</option>';
            templates.forEach(function(t, i) {
                opts += '<option value="' + i + '">' + esc(t.title) + '</option>';
            });
            picker =
                '  <div class="form-group mb-3">' +
                '    <label class="font-weight-bold">' + esc(strings.startfrom) + '</label>' +
                '    <select class="form-control byblos-goal-tplpick">' + opts + '</select>' +
                '  </div>';
        }
        return '' +
            '<form class="byblos-goal-form">' +
            picker +
            '  <div class="form-group mb-2">' +
            '    <label class="font-weight-bold">' + esc(strings.title) + '</label>' +
            '    <input type="text" class="form-control" data-field="title" maxlength="255" value="' +
                   esc(goal.title || '') + '" required>' +
            '  </div>' +
            '  <div class="form-group mb-2">' +
            '    <label class="font-weight-bold">' + esc(strings.description) + '</label>' +
            '    <textarea class="form-control" data-field="description" rows="3">' +
                   esc(goal.description || '') + '</textarea>' +
            '  </div>' +
            '  <div class="form-row">' +
            '    <div class="form-group col-md-4 mb-2">' +
            '      <label class="font-weight-bold">' + esc(strings.statuslabel) + '</label>' +
            '      <select class="form-control" data-field="status">' + statusOpts + '</select>' +
            '    </div>' +
            '    <div class="form-group col-md-4 mb-2">' +
            '      <label class="font-weight-bold">' + esc(strings.progress) + '</label>' +
            '      <input type="number" class="form-control" data-field="progress" min="0" max="100" value="' +
                   esc(goal.progress || 0) + '">' +
            '    </div>' +
            '    <div class="form-group col-md-4 mb-2">' +
            '      <label class="font-weight-bold">' + esc(strings.target) + '</label>' +
            '      <input type="date" class="form-control" data-field="targetdate" value="' + esc(target) + '">' +
            '    </div>' +
            '  </div>' +
            '</form>';
    }

    /**
     * Read the goal form values from the modal body.
     *
     * @param {jQuery} body
     * @return {Object}
     */
    function readForm(body) {
        var target = body.find('[data-field="targetdate"]').val();
        var ts = 0;
        if (target) {
            ts = Math.floor(new Date(target + 'T00:00:00').getTime() / 1000);
        }
        return {
            title: $.trim(body.find('[data-field="title"]').val()),
            description: body.find('[data-field="description"]').val(),
            status: body.find('[data-field="status"]').val(),
            progress: parseInt(body.find('[data-field="progress"]').val(), 10) || 0,
            targetdate: ts,
        };
    }

    /**
     * Open the create/edit modal.
     *
     * @param {Object|null} goal Existing goal (null = create).
     */
    function openGoalModal(goal) {
        var isEdit = !!goal;
        var keys = [
            {key: isEdit ? 'goal_edit' : 'goal_new', component: 'local_byblos'},
            {key: 'goal_title', component: 'local_byblos'},
            {key: 'goal_description', component: 'local_byblos'},
            {key: 'goalstatus_label', component: 'local_byblos'},
            {key: 'goal_progress', component: 'local_byblos'},
            {key: 'goal_target', component: 'local_byblos'},
            {key: 'goalstatus_active', component: 'local_byblos'},
            {key: 'goalstatus_achieved', component: 'local_byblos'},
            {key: 'goalstatus_archived', component: 'local_byblos'},
            {key: 'goal_startfrom', component: 'local_byblos'},
            {key: 'goal_startblank', component: 'local_byblos'},
            {key: 'savechanges', component: 'core'},
        ];
        Str.get_strings(keys).then(function(s) {
            var strings = {
                modaltitle: s[0],
                title: s[1],
                description: s[2],
                statuslabel: s[3],
                progress: s[4],
                target: s[5],
                status_active: s[6],
                status_achieved: s[7],
                status_archived: s[8],
                startfrom: s[9],
                startblank: s[10],
                save: s[11],
            };
            return ModalSaveCancel.create({
                title: strings.modaltitle,
                body: goalFormHtml(goal || {}, strings, isEdit ? [] : goalTemplates),
                buttons: {save: strings.save},
                large: true,
            }).then(function(modal) {
                // "Start from a template" prefills the title and description.
                modal.getRoot().on('change', '.byblos-goal-tplpick', function() {
                    var body = modal.getRoot().find('.modal-body');
                    var idx = $(this).val();
                    if (idx === '') {
                        body.find('[data-field="title"]').val('');
                        body.find('[data-field="description"]').val('');
                        return;
                    }
                    var tpl = goalTemplates[parseInt(idx, 10)];
                    if (tpl) {
                        body.find('[data-field="title"]').val(tpl.title);
                        body.find('[data-field="description"]').val(tpl.description);
                    }
                });
                modal.getRoot().on(ModalEvents.save, function(e) {
                    e.preventDefault();
                    var values = readForm(modal.getRoot().find('.modal-body'));
                    if (!values.title) {
                        return;
                    }
                    var promise;
                    if (isEdit) {
                        promise = call('local_byblos_update_goal', {
                            goalid: goal.id,
                            title: values.title,
                            description: values.description,
                            status: values.status,
                            progress: values.progress,
                            targetdate: values.targetdate,
                        });
                    } else {
                        promise = call('local_byblos_create_goal', {
                            title: values.title,
                            description: values.description,
                            targetdate: values.targetdate,
                        });
                    }
                    promise.then(reloadGoalsTab).catch(Notification.exception);
                });
                modal.show();
                return modal;
            });
        }).catch(Notification.exception);
    }

    /**
     * Fetch a single goal's data (via the user's goal list) then open the editor.
     *
     * @param {number} goalId
     */
    function editGoal(goalId) {
        call('local_byblos_list_goals', {userid: userId}).then(function(goals) {
            var match = goals.find(function(g) {
                return g.id === goalId;
            });
            if (match) {
                openGoalModal(match);
            }
            return match;
        }).catch(Notification.exception);
    }

    /**
     * Delete a goal after confirmation.
     *
     * @param {number} goalId
     */
    function deleteGoal(goalId) {
        Str.get_strings([
            {key: 'goal_delete_confirm', component: 'local_byblos'},
            {key: 'delete', component: 'core'},
            {key: 'cancel', component: 'core'},
        ]).then(function(s) {
            return Notification.confirm(s[1], s[0], s[1], s[2], function() {
                call('local_byblos_delete_goal', {goalid: goalId})
                    .then(reloadGoalsTab).catch(Notification.exception);
            });
        }).catch(Notification.exception);
    }

    /**
     * Open the "add evidence" modal: pick an artefact or page to link.
     *
     * @param {number} goalId
     */
    function addEvidence(goalId) {
        var requests = Ajax.call([
            {methodname: 'local_byblos_list_artefacts', args: {}},
            {methodname: 'local_byblos_list_user_pages', args: {excludepageid: 0}},
        ]);
        var strkeys = [
            {key: 'goal_addevidence', component: 'local_byblos'},
            {key: 'goal_evidence_artefacts', component: 'local_byblos'},
            {key: 'goal_evidence_pages', component: 'local_byblos'},
            {key: 'add', component: 'core'},
        ];
        $.when(requests[0], requests[1], Str.get_strings(strkeys)).then(
            function(artefacts, pages, s) {
                var html = '<div class="byblos-goal-evidence-picker">';
                html += '<h6>' + esc(s[1]) + '</h6><ul class="list-unstyled">';
                (artefacts || []).forEach(function(a) {
                    html += '<li><button type="button" class="btn btn-sm btn-outline-secondary mb-1" ' +
                        'data-linktype="artefact" data-linkid="' + a.id + '">' +
                        '<i class="fa fa-plus"></i> ' + esc(a.title) + '</button></li>';
                });
                html += '</ul><h6>' + esc(s[2]) + '</h6><ul class="list-unstyled">';
                (pages || []).forEach(function(p) {
                    html += '<li><button type="button" class="btn btn-sm btn-outline-secondary mb-1" ' +
                        'data-linktype="page" data-linkid="' + p.id + '">' +
                        '<i class="fa fa-plus"></i> ' + esc(p.title) + '</button></li>';
                });
                html += '</ul></div>';
                return ModalSaveCancel.create({title: s[0], body: html}).then(function(modal) {
                    modal.getRoot().on('click', '[data-linktype]', function() {
                        var btn = $(this);
                        call('local_byblos_add_goal_link', {
                            goalid: goalId,
                            linktype: btn.data('linktype'),
                            linkid: btn.data('linkid'),
                        }).then(reloadGoalsTab).catch(Notification.exception);
                    });
                    modal.show();
                    return modal;
                });
            }
        ).catch(Notification.exception);
    }

    return {
        /**
         * Initialise the goals tab handlers.
         *
         * @param {Object} args Init args ({userid, sesskey}).
         */
        init: function(args) {
            userId = parseInt((args && args.userid) || 0, 10);
            goalTemplates = (args && args.templates) || [];
            var root = document.querySelector('[data-byblos-goals]');
            if (!root) {
                return;
            }
            var $root = $(root);
            $root.on('click', '.byblos-goal-new', function() {
                openGoalModal(null);
            });
            $root.on('click', '.byblos-goal-edit', function() {
                editGoal(parseInt($(this).closest('.byblos-goal-card').data('goalid'), 10));
            });
            $root.on('click', '.byblos-goal-delete', function() {
                deleteGoal(parseInt($(this).closest('.byblos-goal-card').data('goalid'), 10));
            });
            $root.on('click', '.byblos-goal-evidence-add', function() {
                addEvidence(parseInt($(this).closest('.byblos-goal-card').data('goalid'), 10));
            });
            // Quick-start: show/hide the starter-goal templates.
            $root.on('click', '.byblos-goal-qs-toggle', function() {
                $root.find('.byblos-goal-qs-body').toggleClass('d-none');
            });
            // Quick-start: add a scaffolded starter goal, then reload to show it.
            $root.on('click', '.byblos-goal-tpl-add', function() {
                var btn = $(this);
                call('local_byblos_create_goal', {
                    title: btn.attr('data-title'),
                    description: btn.attr('data-description'),
                    targetdate: 0,
                }).then(reloadGoalsTab).catch(Notification.exception);
            });
        }
    };
});
