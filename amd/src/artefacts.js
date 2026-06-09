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
 * Artefact library controller: instant search, sort, group, density toggle and
 * type/tag filtering, all client-side over the already-rendered set (no reloads,
 * no web services). Scales comfortably to a few hundred artefacts.
 *
 * @module     local_byblos/artefacts
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    /** @type {Object} CSS selectors used throughout the module. */
    var SEL = {
        root: '[data-region="byblos-artefacts"]',
        items: '[data-region="items"]',
        item: '[data-region="artefact"]',
        heading: '[data-region="group-heading"]',
        search: '[data-action="search"]',
        sort: '[data-action="sort"]',
        group: '[data-action="group"]',
        view: '[data-action="view"]',
        typeFilter: '[data-filtertype]',
        tagFilter: '[data-filtertag]',
        clearTags: '[data-action="cleartags"]',
        count: '[data-region="count"]',
        noresults: '[data-region="noresults"]'
    };

    /** @type {string} localStorage key remembering the grid/list choice. */
    var VIEW_KEY = 'local_byblos_artefacts_view';

    /** @type {Object} Current filter/sort/group state. */
    var state = {search: '', type: '', tags: [], sort: 'newest', group: 'none'};

    /**
     * Debounce a function so it runs at most once per `wait` ms of quiet.
     *
     * @param {Function} fn The function to debounce.
     * @param {number} wait Quiet period in milliseconds.
     * @return {Function} The debounced wrapper.
     */
    function debounce(fn, wait) {
        var timer;
        return function() {
            var ctx = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(ctx, args);
            }, wait);
        };
    }

    /**
     * Escape a string for safe insertion as text-in-HTML.
     *
     * @param {string} text Raw text.
     * @return {string} HTML-escaped text.
     */
    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function(ch) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
        });
    }

    /**
     * Whether an item passes the active type filter.
     *
     * @param {Element} el The artefact item element.
     * @return {boolean} True if it should be shown.
     */
    function matchesType(el) {
        if (state.type === '') {
            return true;
        }
        if (state.type === 'imported') {
            return el.getAttribute('data-imported') === '1';
        }
        return el.getAttribute('data-type') === state.type;
    }

    /**
     * Whether an item passes the active tag filter (OR semantics).
     *
     * @param {Element} el The artefact item element.
     * @return {boolean} True if it should be shown.
     */
    function matchesTags(el) {
        if (!state.tags.length) {
            return true;
        }
        var tags = (el.getAttribute('data-tags') || '').split(' ');
        return state.tags.some(function(tag) {
            return tags.indexOf(tag) !== -1;
        });
    }

    /**
     * Whether an item matches every search term (AND semantics).
     *
     * @param {Element} el The artefact item element.
     * @param {Array} terms Lowercased search terms.
     * @return {boolean} True if it should be shown.
     */
    function matchesSearch(el, terms) {
        if (!terms.length) {
            return true;
        }
        var blob = el.getAttribute('data-search') || '';
        return terms.every(function(term) {
            return blob.indexOf(term) !== -1;
        });
    }

    /**
     * Compare two items for the active sort order.
     *
     * @param {Element} a First item.
     * @param {Element} b Second item.
     * @return {number} Standard comparator result.
     */
    function comparator(a, b) {
        var ta;
        var tb;
        switch (state.sort) {
            case 'oldest':
                return (+a.getAttribute('data-time')) - (+b.getAttribute('data-time'));
            case 'title_az':
                return a.getAttribute('data-title').localeCompare(b.getAttribute('data-title'));
            case 'title_za':
                return b.getAttribute('data-title').localeCompare(a.getAttribute('data-title'));
            case 'type':
                ta = a.getAttribute('data-typelabel');
                tb = b.getAttribute('data-typelabel');
                return ta.localeCompare(tb) || a.getAttribute('data-title').localeCompare(b.getAttribute('data-title'));
            default:
                return (+b.getAttribute('data-time')) - (+a.getAttribute('data-time'));
        }
    }

    /**
     * Re-run filtering, sorting and grouping, then update the DOM.
     *
     * @return {void}
     */
    function apply() {
        var root = document.querySelector(SEL.root);
        if (!root) {
            return;
        }
        var container = root.querySelector(SEL.items);
        var items = $(container).find(SEL.item).toArray();
        var terms = state.search.trim().toLowerCase().split(/\s+/).filter(function(term) {
            return term.length > 0;
        });

        var visible = [];
        items.forEach(function(el) {
            var show = matchesType(el) && matchesTags(el) && matchesSearch(el, terms);
            el.classList.toggle('d-none', !show);
            if (show) {
                visible.push(el);
            }
        });

        visible.sort(comparator);

        // Clear any previous group headings before re-laying-out.
        $(container).find(SEL.heading).remove();

        if (state.group === 'type') {
            var lastLabel = null;
            visible.forEach(function(el) {
                var label = el.getAttribute('data-typelabel');
                if (label !== lastLabel) {
                    var heading = document.createElement('div');
                    heading.className = 'byblos-group-heading';
                    heading.setAttribute('data-region', 'group-heading');
                    heading.innerHTML = '<h4 class="h6 text-uppercase text-muted mt-3 mb-2">' +
                        escapeHtml(label) + '</h4>';
                    container.appendChild(heading);
                    lastLabel = label;
                }
                container.appendChild(el);
            });
        } else {
            visible.forEach(function(el) {
                container.appendChild(el);
            });
        }

        // Keep hidden items in the DOM but parked at the end.
        items.forEach(function(el) {
            if (el.classList.contains('d-none')) {
                container.appendChild(el);
            }
        });

        var countEl = root.querySelector(SEL.count);
        if (countEl) {
            var tpl = countEl.getAttribute('data-tpl') || 'Showing {shown} of {total}';
            countEl.textContent = tpl.replace('{shown}', visible.length).replace('{total}', items.length);
        }
        var noresults = root.querySelector(SEL.noresults);
        if (noresults) {
            noresults.classList.toggle('d-none', visible.length !== 0);
        }
    }

    /**
     * Switch the library between grid and list density and remember the choice.
     *
     * @param {string} view Either "grid" or "list".
     * @return {void}
     */
    function setView(view) {
        var resolved = (view === 'list') ? 'list' : 'grid';
        var root = document.querySelector(SEL.root);
        var container = root.querySelector(SEL.items);
        container.classList.toggle('byblos-view-grid', resolved === 'grid');
        container.classList.toggle('byblos-view-list', resolved === 'list');
        $(root).find(SEL.view).each(function() {
            this.classList.toggle('active', this.getAttribute('data-view') === resolved);
        });
        try {
            window.localStorage.setItem(VIEW_KEY, resolved);
        } catch (e) {
            // Private mode or storage disabled: density just will not persist.
        }
    }

    /**
     * Wire up the library controls and render the initial view.
     *
     * @return {void}
     */
    var init = function() {
        var root = document.querySelector(SEL.root);
        if (!root) {
            return;
        }

        var savedView = 'grid';
        try {
            savedView = window.localStorage.getItem(VIEW_KEY) || 'grid';
        } catch (e) {
            savedView = 'grid';
        }
        setView(savedView);

        // Seed the type filter from any server-marked active pill (?type= deep link).
        var activePill = root.querySelector(SEL.typeFilter + '.active');
        if (activePill) {
            state.type = activePill.getAttribute('data-filtertype');
        }

        var $root = $(root);

        $root.on('input', SEL.search, debounce(function() {
            state.search = this.value || '';
            apply();
        }, 150));

        $root.on('change', SEL.sort, function() {
            state.sort = this.value;
            apply();
        });

        $root.on('change', SEL.group, function() {
            state.group = this.value;
            apply();
        });

        $root.on('click', SEL.view, function() {
            setView(this.getAttribute('data-view'));
        });

        $root.on('click', SEL.typeFilter, function() {
            var clicked = this;
            state.type = clicked.getAttribute('data-filtertype');
            $root.find(SEL.typeFilter).each(function() {
                this.classList.toggle('active', this === clicked);
            });
            apply();
        });

        $root.on('click', SEL.tagFilter, function() {
            var value = this.getAttribute('data-filtertag');
            var idx = state.tags.indexOf(value);
            if (idx === -1) {
                state.tags.push(value);
                this.classList.add('active');
            } else {
                state.tags.splice(idx, 1);
                this.classList.remove('active');
            }
            var clearBtn = root.querySelector(SEL.clearTags);
            if (clearBtn) {
                clearBtn.classList.toggle('d-none', state.tags.length === 0);
            }
            apply();
        });

        $root.on('click', SEL.clearTags, function() {
            state.tags = [];
            $root.find(SEL.tagFilter).each(function() {
                this.classList.remove('active');
            });
            this.classList.add('d-none');
            apply();
        });

        apply();
    };

    return {init: init};
});
