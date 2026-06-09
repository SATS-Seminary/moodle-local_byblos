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
 * Main AMD module for the Byblos section-based page editor.
 *
 * Bootstraps AJAX-driven section CRUD: add, update, delete, reorder.
 * Provides inline editing panels, preview toggle, and theme switching.
 *
 * @module     local_byblos/editor
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str',
        'local_byblos/editor_inline', 'local_byblos/upload', 'editor_tiny/loader',
        'core/fragment', 'core/modal_save_cancel', 'core/modal_events'],
function($, Ajax, Notification, Str, InlineEditor, Upload, TinyLoader,
        Fragment, ModalSaveCancel, ModalEvents) {
    'use strict';

    /** @type {number} Page ID from PHP. */
    var pageId;
    /** @type {jQuery} Root editor element. */
    var $root;
    /** @type {number} Position for the next section insert. */
    var insertPosition = 0;

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Escape HTML entities.
     * @param {string} str
     * @returns {string}
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    /**
     * Call a Moodle external function via core/ajax.
     * @param {string} methodname
     * @param {Object} args
     * @returns {Promise}
     */
    function callExternal(methodname, args) {
        return Ajax.call([{methodname: methodname, args: args}])[0];
    }

    /**
     * Smoothly scroll a jQuery element into the viewport. Uses the native
     * scrollIntoView where supported; falls back to no-op otherwise.
     * @param {jQuery} $el
     */
    function scrollIntoViewSafely($el) {
        if (!$el || !$el.length || !$el[0].scrollIntoView) {
            return;
        }
        try {
            $el[0].scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (e) {
            $el[0].scrollIntoView();
        }
    }

    /**
     * Give focus to the first meaningful input/select/textarea inside a panel.
     * Skips hidden inputs and elements marked readonly/disabled.
     * @param {jQuery} $panel
     */
    function focusFirstField($panel) {
        if (!$panel || !$panel.length) {
            return;
        }
        var $field = $panel.find(
            'input:not([type=hidden]):not([readonly]):not([disabled]),' +
            ' select:not([disabled]),' +
            ' textarea:not([readonly]):not([disabled])'
        ).first();
        if ($field.length) {
            // Defer slightly so TinyMCE / collapse animations don't steal focus back.
            setTimeout(function() {
                $field.trigger('focus');
            }, 50);
        }
    }

    // ================================================================
    // Section Type Modal
    // ================================================================

    /**
     * Open the section type picker at a given insert position.
     * @param {number} position
     */
    function openTypePicker(position) {
        insertPosition = position + 1; // Insert AFTER the clicked position.
        $('#byblos-section-type-modal').modal('show');
    }

    /**
     * Handle section type selection from the picker modal.
     */
    function initTypePicker() {
        $(document).on('click', '.byblos-type-pick-card', function() {
            var stype = $(this).data('type');
            $('#byblos-section-type-modal').modal('hide');

            callExternal('local_byblos_add_section', {
                pageid: pageId,
                sectiontype: stype,
                sortorder: insertPosition
            }).then(function(result) {
                if (result && result.id) {
                    // Reload to get the fresh section in the stack.
                    window.location.reload();
                }
                return;
            }).catch(Notification.exception);
        });
    }

    // ================================================================
    // Section Actions (Edit, Delete, Move)
    // ================================================================

    /**
     * Bind action handlers for a section card.
     * @param {jQuery} $card
     */
    function bindSectionActions($card) {
        var sectionId = parseInt($card.data('section-id'), 10);

        // Edit button.
        $card.find('.byblos-tb-edit').on('click', function(e) {
            e.stopPropagation();
            toggleEditPanel($card);
        });

        // Delete button.
        $card.find('.byblos-tb-delete').on('click', function(e) {
            e.stopPropagation();
            Str.get_string('deletesectionconfirm', 'local_byblos').then(function(confirmstr) {
                if (!window.confirm(confirmstr)) {
                    return;
                }
                callExternal('local_byblos_delete_section', {
                    sectionid: sectionId
                }).then(function() {
                    // Remove the card and its preceding add-row.
                    var $prev = $card.prev('.byblos-add-row');
                    $card.fadeOut(200, function() {
                        $card.remove();
                        if ($prev.length) {
                            $prev.remove();
                        }
                        // If no sections left, show empty state.
                        if ($root.find('.byblos-section-card').length === 0) {
                            $('#byblos-section-stack').append(
                                '<div class="text-center text-muted py-5" id="byblos-empty-state">' +
                                '<i class="fa fa-puzzle-piece fa-3x mb-3" style="display:block !important;"></i>' +
                                '<p>No sections yet.</p></div>'
                            );
                        }
                    });
                    return;
                }).catch(Notification.exception);
                return;
            }).catch(Notification.exception);
        });

        // Move up.
        $card.find('.byblos-tb-up').on('click', function(e) {
            e.stopPropagation();
            var $prevCard = $card.prevAll('.byblos-section-card').first();
            if ($prevCard.length) {
                var $myAddRow = $card.prev('.byblos-add-row');
                var $prevAddRow = $prevCard.prev('.byblos-add-row');
                if ($prevAddRow.length) {
                    $myAddRow.insertBefore($prevAddRow);
                    $card.insertAfter($myAddRow);
                } else {
                    $card.insertBefore($prevCard);
                }
                saveOrder();
            }
        });

        // Move down.
        $card.find('.byblos-tb-down').on('click', function(e) {
            e.stopPropagation();
            var $nextCard = $card.nextAll('.byblos-section-card').first();
            if ($nextCard.length) {
                var $nextAddRow = $nextCard.next('.byblos-add-row');
                if ($nextAddRow.length) {
                    $card.insertAfter($nextAddRow);
                    var $myAddRow = $card.prev('.byblos-add-row');
                    if (!$myAddRow.length || !$myAddRow.hasClass('byblos-add-row')) {
                        // Ensure an add-row exists before us.
                    }
                } else {
                    $card.insertAfter($nextCard);
                }
                saveOrder();
            }
        });
    }

    // ================================================================
    // Save Ordering
    // ================================================================

    /**
     * Persist the current visual order of section cards to the server.
     */
    function saveOrder() {
        var items = [];
        $root.find('.byblos-section-card').each(function(idx) {
            items.push({
                sectionid: parseInt($(this).data('section-id'), 10),
                sortorder: idx
            });
        });

        callExternal('local_byblos_reorder_sections', {
            pageid: pageId,
            ordering: JSON.stringify(items)
        }).catch(Notification.exception);
    }

    // ================================================================
    // Inline Edit Panel
    // ================================================================

    /**
     * Build a single form field HTML.
     * @param {string} label
     * @param {string} id
     * @param {*} val
     * @param {string} type - text|textarea|color|checkbox
     * @returns {string}
     */
    function buildField(label, id, val, type) {
        type = type || 'text';
        if (type === 'textarea') {
            return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
                '<textarea id="' + id + '" class="form-control form-control-sm" rows="4">' +
                escHtml(val || '') + '</textarea></div>';
        }
        if (type === 'rich') {
            return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
                '<textarea id="' + id + '" class="form-control byblos-rich" rows="6">' +
                escHtml(val || '') + '</textarea></div>';
        }
        if (type === 'color') {
            return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
                '<input type="color" id="' + id + '" class="form-control form-control-sm" value="' +
                escHtml(val || '#000000') + '" style="height:34px !important; padding:2px !important;"></div>';
        }
        if (type === 'checkbox') {
            return '<div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="' +
                id + '"' + (val ? ' checked' : '') + '><label class="form-check-label small" for="' +
                id + '">' + label + '</label></div>';
        }
        return '<div class="form-group"><label for="' + id + '">' + label + '</label>' +
            '<input type="' + type + '" id="' + id + '" class="form-control form-control-sm" value="' +
            escHtml(val || '') + '"></div>';
    }

    /**
     * Build markup for an image field backed by the upload widget.
     * Renders a placeholder div (mounted later) plus a hidden input that
     * collectEditForm reads by id — keeping the collection logic unchanged.
     *
     * @param {string} label
     * @param {string} id  Hidden input id (e.g. 'bse_image_url').
     * @param {string} val Current URL.
     * @returns {string}
     */
    function buildImageField(label, id, val) {
        return '<div class="form-group"><label>' + label + '</label>' +
            '<div class="byblos-image-field" data-field-id="' + id + '"></div>' +
            '<input type="hidden" id="' + id + '" value="' + escHtml(val || '') + '">' +
            '</div>';
    }

    /**
     * Render an HTML string into syntax-highlighted markup for the code-editor
     * shade pane. Walks the input character-by-character producing token spans
     * for comments, tags, attribute names, attribute values, and text.
     * Output is safe to drop straight into innerHTML because we HTML-escape
     * every literal character on the way out.
     *
     * @param {string} src Raw HTML the user is editing.
     * @returns {string} Coloured HTML for the shade overlay.
     */
    function highlightHtml(src) {
        src = src || '';
        var out = '';
        var i = 0;
        var n = src.length;
        var esc = function(s) {
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        };
        while (i < n) {
            // Comment: <!-- ... -->
            if (src.substr(i, 4) === '<!--') {
                var end = src.indexOf('-->', i + 4);
                if (end === -1) { end = n; } else { end += 3; }
                out += '<span class="ce-c">' + esc(src.substring(i, end)) + '</span>';
                i = end;
                continue;
            }
            // Doctype: <!DOCTYPE …>
            if (src.substr(i, 2) === '<!') {
                var endd = src.indexOf('>', i);
                if (endd === -1) { endd = n; } else { endd += 1; }
                out += '<span class="ce-d">' + esc(src.substring(i, endd)) + '</span>';
                i = endd;
                continue;
            }
            // Open or close tag.
            if (src.charAt(i) === '<') {
                var tagend = src.indexOf('>', i);
                if (tagend === -1) {
                    out += esc(src.substring(i));
                    break;
                }
                var tag = src.substring(i, tagend + 1);
                // Tokenise inside the tag: '<', tagname, attrs, '>'.
                var inner = tag.slice(1, -1); // Without < and >.
                var isClose = inner.charAt(0) === '/';
                var nameMatch = inner.match(/^\/?\s*([a-zA-Z][\w:-]*)/);
                var rendered = '&lt;';
                if (isClose) { rendered += '/'; }
                if (nameMatch) {
                    rendered += '<span class="ce-t">' + esc(nameMatch[1]) + '</span>';
                    var rest = inner.substr(nameMatch[0].length);
                    // Walk attributes inside the rest: name (= "value")?
                    rendered += highlightAttrs(rest, esc);
                } else {
                    rendered += esc(inner);
                }
                rendered += '&gt;';
                out += '<span class="ce-tagwrap">' + rendered + '</span>';
                i = tagend + 1;
                continue;
            }
            // Plain text until next '<'.
            var lt = src.indexOf('<', i);
            if (lt === -1) {
                out += esc(src.substring(i));
                break;
            }
            out += esc(src.substring(i, lt));
            i = lt;
        }
        return out + '\n'; // Trailing newline so the shade pane matches the textarea's last empty line.
    }

    /**
     * Tokenise the attribute portion of an HTML tag for highlightHtml.
     *
     * @param {string} src The text between the tag name and the closing '>'.
     * @param {Function} esc HTML-escape helper.
     * @returns {string} Highlighted markup.
     */
    function highlightAttrs(src, esc) {
        var out = '';
        var re = /\s+([a-zA-Z_:][\w:.-]*)(\s*=\s*("[^"]*"|'[^']*'|[^\s'">]+))?/g;
        var m;
        var lastEnd = 0;
        while ((m = re.exec(src)) !== null) {
            out += esc(src.substring(lastEnd, m.index));
            out += ' <span class="ce-a">' + esc(m[1]) + '</span>';
            if (m[2]) {
                var eqAndVal = m[2];
                var valIdx = eqAndVal.indexOf('=');
                out += esc(eqAndVal.substring(0, valIdx + 1));
                var val = eqAndVal.substring(valIdx + 1).replace(/^\s+/, '');
                var prefix = eqAndVal.substring(valIdx + 1, valIdx + 1 + (eqAndVal.length - valIdx - 1 - val.length));
                out += esc(prefix);
                out += '<span class="ce-s">' + esc(val) + '</span>';
            }
            lastEnd = m.index + m[0].length;
        }
        out += esc(src.substring(lastEnd));
        return out;
    }

    /**
     * Wire every .bse-codeedit inside $panel: hook input/scroll events on the
     * textarea so the shaded preview underneath stays in sync.
     *
     * @param {jQuery} $panel
     */
    function initCodeEditors($panel) {
        $panel.find('.bse-codeedit').each(function() {
            var $wrap = $(this);
            var $ta = $wrap.find('.bse-codeedit-input');
            var $shade = $wrap.find('.bse-codeedit-shade code');
            var sync = function() {
                $shade.html(highlightHtml($ta.val()));
            };
            var syncScroll = function() {
                $wrap.find('.bse-codeedit-shade').scrollTop($ta.scrollTop());
                $wrap.find('.bse-codeedit-shade').scrollLeft($ta.scrollLeft());
            };
            $ta.on('input', sync);
            $ta.on('scroll', syncScroll);
            // Tab in textarea inserts two spaces instead of moving focus.
            $ta.on('keydown', function(ev) {
                if (ev.key === 'Tab') {
                    ev.preventDefault();
                    var el = $ta[0];
                    var start = el.selectionStart;
                    var end = el.selectionEnd;
                    el.value = el.value.substring(0, start) + '  ' + el.value.substring(end);
                    el.selectionStart = el.selectionEnd = start + 2;
                    sync();
                }
            });
            sync();
        });
    }

    /**
     * Attach TinyMCE to every textarea.byblos-rich inside $panel.
     * Uses the Moodle Tiny loader with a lightweight toolbar — enough for body copy.
     *
     * @param {jQuery} $panel
     */
    function initRichFields($panel) {
        var $targets = $panel.find('textarea.byblos-rich');
        if (!$targets.length) {
            return;
        }
        TinyLoader.getTinyMCE().then(function(tinyMCE) {
            $targets.each(function() {
                tinyMCE.init({
                    target: this,
                    menubar: false,
                    statusbar: false,
                    branding: false,
                    promotion: false,
                    height: 220,
                    plugins: 'lists link autolink',
                    toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat'
                });
            });
            return tinyMCE;
        }).catch(Notification.exception);
    }

    /**
     * Persist current TinyMCE content back to the underlying textareas
     * and remove the editor instances — call before reading values or
     * tearing down the panel.
     */
    function teardownRichFields() {
        if (window.tinyMCE) {
            window.tinyMCE.triggerSave();
            window.tinyMCE.remove('textarea.byblos-rich');
        }
    }

    /**
     * Mount the upload widget on every .byblos-image-field placeholder in $panel,
     * wiring the onUpload callback to the sibling hidden input.
     *
     * @param {jQuery} $panel
     */
    function initImageFields($panel) {
        var sesskey = (window.M && window.M.cfg && window.M.cfg.sesskey) || '';
        $panel.find('.byblos-image-field').each(function() {
            var $placeholder = $(this);
            var fieldId = $placeholder.data('field-id');
            var $hidden = $panel.find('#' + fieldId);
            var currentUrl = $hidden.val() || '';
            Upload.createWidget(
                $placeholder[0],
                pageId,
                sesskey,
                currentUrl || null,
                function(url) {
                    $hidden.val(url || '');
                }
            );
        });
    }

    /**
     * Build the edit form HTML for a given section type.
     * @param {string} stype
     * @param {Object} cfg
     * @param {string} content
     * @returns {string}
     */
    function buildEditForm(stype, cfg, content) {
        var h = '';
        switch (stype) {
            case 'hero':
                h = buildField('Name', 'bse_name', cfg.name) +
                    buildField('Title', 'bse_title', cfg.title) +
                    buildField('Subtitle', 'bse_subtitle', cfg.subtitle) +
                    buildField('Background Colour', 'bse_bg_color', cfg.bg_color, 'color') +
                    buildImageField('Background Image', 'bse_bg_image', cfg.bg_image) +
                    buildImageField('Profile Photo', 'bse_photo_url', cfg.photo_url);
                break;
            case 'text':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Body', 'bse_body', cfg.body, 'rich');
                break;
            case 'text_image':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Body', 'bse_body', cfg.body, 'rich') +
                    buildImageField('Image', 'bse_image_url', cfg.image_url) +
                    buildField('Image Alt Text', 'bse_image_alt', cfg.image_alt) +
                    buildField('Reverse layout (image left)', 'bse_reversed', cfg.reversed, 'checkbox');
                break;
            case 'gallery':
                h = buildGalleryEditor(cfg);
                break;
            case 'skills':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                var skillsText = (cfg.skills || []).map(function(s) {
                    return s.name + ':' + s.level;
                }).join('\n');
                h += buildField(
                    'Skills (one per line: Name:Level 1-5 — '
                        + '1=Novice 2=Beginner 3=Intermediate 4=Proficient 5=Expert)',
                    'bse_skills',
                    skillsText,
                    'textarea'
                );
                break;
            case 'timeline':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                var tlText = (cfg.items || []).map(function(i) {
                    return i.date + '|' + i.title + '|' + (i.description || '');
                }).join('\n');
                h += buildField('Items (one per line: Date|Title|Description)', 'bse_items', tlText, 'textarea');
                break;
            case 'badges':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Show badges', 'bse_show', cfg.show !== false, 'checkbox');
                break;
            case 'completions':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Show completions', 'bse_show', cfg.show !== false, 'checkbox');
                break;
            case 'social':
                var platforms = ['linkedin', 'github', 'twitter', 'facebook', 'instagram', 'youtube', 'globe'];
                var linkMap = {};
                (cfg.links || []).forEach(function(l) {
                    linkMap[l.platform] = l.url;
                });
                h = '<label class="font-weight-bold" style="font-size:0.8rem !important;">Social Links</label>';
                platforms.forEach(function(p) {
                    var iconName = (p === 'globe') ? 'globe' : p;
                    h += '<div class="input-group input-group-sm mb-1">' +
                        '<div class="input-group-prepend"><span class="input-group-text" style="width:100px !important;">' +
                        '<i class="fa fa-' + iconName + '"></i> ' + p + '</span></div>' +
                        '<input type="url" class="form-control bse-social-url" data-platform="' + p +
                        '" value="' + escHtml(linkMap[p] || '') + '" placeholder="https://..."></div>';
                });
                break;
            case 'cta':
                h = buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('Body text', 'bse_body', cfg.body) +
                    buildField('Button text', 'bse_button_text', cfg.button_text) +
                    buildField('Button URL', 'bse_button_url', cfg.button_url) +
                    buildField('Background Colour', 'bse_bg_color', cfg.bg_color, 'color');
                break;
            case 'divider':
                h = '<div class="form-group"><label for="bse_style">Style</label>' +
                    '<select id="bse_style" class="custom-select custom-select-sm">' +
                    '<option value="line"' + (cfg.style === 'line' ? ' selected' : '') + '>Line</option>' +
                    '<option value="space"' + (cfg.style === 'space' ? ' selected' : '') + '>Space only</option>' +
                    '</select></div>' +
                    buildField('Spacing', 'bse_spacing', cfg.spacing || '2rem');
                break;
            case 'custom':
                h = '<div class="alert alert-info small mb-2">' +
                    '<i class="fa fa-shield"></i> HTML is sanitised on save: ' +
                    '<code>&lt;script&gt;</code>, event handlers (<code>onclick=…</code>), ' +
                    '<code>javascript:</code> URLs and <code>&lt;iframe&gt;</code>/' +
                    '<code>&lt;object&gt;</code>/<code>&lt;embed&gt;</code>' +
                    ' are stripped before the section is rendered.' +
                    '</div>' +
                    '<label class="font-weight-bold" style="font-size:0.8rem !important;" for="bse_html">' +
                    'HTML</label>' +
                    '<div class="bse-codeedit">' +
                        '<pre class="bse-codeedit-shade" aria-hidden="true"><code></code></pre>' +
                        '<textarea id="bse_html" class="bse-codeedit-input" spellcheck="false">' +
                        escHtml(content || '') + '</textarea>' +
                    '</div>';
                break;
            case 'chart':
                h = buildChartEditor(cfg);
                break;
            case 'cloud':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                h += buildField('Base Colour', 'bse_cloud_color', cfg.color || '#0d6efd', 'color');
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Words</label>';
                h += '<div id="bse_cloud_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-cloud-add">' +
                    '<i class="fa fa-plus"></i> Add word</button>';
                break;
            case 'quote':
                h = buildField('Quote body', 'bse_body', cfg.body, 'rich') +
                    buildField('Attribution', 'bse_attribution', cfg.attribution) +
                    buildField('Source URL (optional)', 'bse_source', cfg.source, 'url');
                break;
            case 'stats':
                h = buildField('Heading', 'bse_heading', cfg.heading);
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Stat cards (2–4)</label>';
                h += '<div id="bse_stats_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-stats-add">' +
                    '<i class="fa fa-plus"></i> Add stat card</button>';
                break;
            case 'citations':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'References');
                h += '<div class="form-group"><label for="bse_citations_style">Citation style</label>' +
                    '<select id="bse_citations_style" class="custom-select custom-select-sm">' +
                    '<option value="plain"' + ((cfg.style || 'plain') === 'plain' ? ' selected' : '') + '>Plain</option>' +
                    '<option value="apa"' + (cfg.style === 'apa' ? ' selected' : '') + '>APA</option>' +
                    '<option value="mla"' + (cfg.style === 'mla' ? ' selected' : '') + '>MLA</option>' +
                    '<option value="chicago"' + (cfg.style === 'chicago' ? ' selected' : '') + '>Chicago</option>' +
                    '</select></div>';
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">References</label>';
                h += '<div id="bse_citations_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-citations-add">' +
                    '<i class="fa fa-plus"></i> Add reference</button>';
                break;
            case 'files':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'Files');
                h += '<div class="form-group"><label for="bse_files_display">Display</label>' +
                    '<select id="bse_files_display" class="custom-select custom-select-sm">' +
                    '<option value="list"' + ((cfg.display || 'list') === 'list' ? ' selected' : '') + '>List</option>' +
                    '<option value="tile"' + (cfg.display === 'tile' ? ' selected' : '') + '>Tile</option>' +
                    '<option value="thumbs"' + (cfg.display === 'thumbs' ? ' selected' : '') + '>Thumbnails</option>' +
                    '</select></div>';
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Files</label>';
                h += '<div id="bse_files_items"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-files-add">' +
                    '<i class="fa fa-plus"></i> Add file</button>';
                break;
            case 'pagenav':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'Related pages');
                h += '<div class="form-group"><label for="bse_pn_source">Source</label>' +
                    '<select id="bse_pn_source" class="custom-select custom-select-sm">' +
                    '<option value="collection"' + ((cfg.source || 'collection') === 'collection' ? ' selected' : '') +
                    '>Collection</option>' +
                    '<option value="manual"' + (cfg.source === 'manual' ? ' selected' : '') +
                    '>Manual list of pages</option>' +
                    '</select></div>';
                // Collection source block.
                h += '<div class="form-group bse-pn-collection-block">' +
                    '<label for="bse_pn_collection">Collection</label>' +
                    '<select id="bse_pn_collection" class="custom-select custom-select-sm">' +
                    '<option value="0">Loading collections...</option>' +
                    '</select></div>';
                // Manual source block.
                h += '<div class="bse-pn-manual-block">' +
                    '<label class="font-weight-bold" style="font-size:0.8rem !important;">Pages</label>' +
                    '<div id="bse_pn_items"></div>' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-pn-add">' +
                    '<i class="fa fa-plus"></i> Add page</button></div>';
                h += '<div class="form-group"><label for="bse_pn_display">Display</label>' +
                    '<select id="bse_pn_display" class="custom-select custom-select-sm">' +
                    '<option value="tabs"' + (cfg.display === 'tabs' ? ' selected' : '') + '>Tabs</option>' +
                    '<option value="pills"' + ((cfg.display || 'pills') === 'pills' ? ' selected' : '') +
                    '>Pills</option>' +
                    '<option value="cards"' + (cfg.display === 'cards' ? ' selected' : '') + '>Cards</option>' +
                    '<option value="nextprev"' + (cfg.display === 'nextprev' ? ' selected' : '') +
                    '>Previous / next</option>' +
                    '</select></div>';
                h += '<div class="form-check mb-2 bse-pn-showdescs-wrap">' +
                    '<input type="checkbox" class="form-check-input" id="bse_pn_showdescs"' +
                    (cfg.show_descriptions ? ' checked' : '') + '>' +
                    '<label class="form-check-label small" for="bse_pn_showdescs">' +
                    'Show page descriptions on cards</label></div>';
                break;
            case 'youtube':
                h = buildField('Heading (optional)', 'bse_heading', cfg.heading);
                h += buildField('YouTube URL', 'bse_yt_url', cfg.url, 'url');
                h += '<small class="form-text text-muted mb-2" style="margin-top:-0.5rem !important;">' +
                    'Accepts any youtube.com/watch, youtu.be, /embed, /shorts, or /live link.</small>';
                h += '<div class="form-group"><label for="bse_yt_align">Layout</label>' +
                    '<select id="bse_yt_align" class="custom-select custom-select-sm">' +
                    '<option value="full"' + ((cfg.alignment || 'full') === 'full' ? ' selected' : '') +
                        '>Full width</option>' +
                    '<option value="center"' + (cfg.alignment === 'center' ? ' selected' : '') +
                        '>Center (max 720px)</option>' +
                    '<option value="left"' + (cfg.alignment === 'left' ? ' selected' : '') +
                        '>Align left — text on right</option>' +
                    '<option value="right"' + (cfg.alignment === 'right' ? ' selected' : '') +
                        '>Align right — text on left</option>' +
                    '</select></div>';
                h += buildField('Caption (optional, shown below video)', 'bse_yt_desc', cfg.description);
                h += buildField('Start at (seconds, optional)', 'bse_yt_start', cfg.start || '', 'number');
                h += buildField(
                    'Body text (shown beside the video in left/right layouts, below it in full/center)',
                    'bse_yt_body',
                    cfg.body || '',
                    'rich'
                );
                break;
            case 'alignment':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'Outcome map');
                h += buildField('Intro (optional)', 'bse_align_intro', cfg.intro || '', 'textarea');
                h += '<div class="bse-align-import d-none mb-2">' +
                    '<label class="font-weight-bold" style="font-size:0.8rem !important;">' +
                    'Import outcomes from a competency framework</label>' +
                    '<div class="input-group input-group-sm">' +
                    '<select id="bse_align_framework" class="custom-select custom-select-sm">' +
                    '<option value="0">Choose a framework…</option></select>' +
                    '<div class="input-group-append">' +
                    '<button type="button" class="btn btn-outline-secondary bse-align-import-btn">Import</button>' +
                    '</div></div></div>';
                h += '<label class="font-weight-bold" style="font-size:0.8rem !important;">Outcomes</label>';
                h += '<div id="bse_align_outcomes"></div>';
                h += '<button type="button" class="btn btn-outline-secondary btn-sm mt-1 bse-align-add">' +
                    '<i class="fa fa-plus"></i> Add outcome</button>';
                break;
            case 'goals':
                h = buildField('Heading', 'bse_heading', cfg.heading || 'My goals');
                h += '<div class="form-group"><label for="bse_goals_mode">Which goals</label>' +
                    '<select id="bse_goals_mode" class="custom-select custom-select-sm">' +
                    '<option value="all_active"' + ((cfg.mode || 'all_active') === 'all_active' ? ' selected' : '') +
                    '>All my active goals</option>' +
                    '<option value="selected"' + (cfg.mode === 'selected' ? ' selected' : '') +
                    '>Only the goals I choose</option>' +
                    '</select></div>';
                h += '<div class="bse-goals-select-block' +
                    (cfg.mode === 'selected' ? '' : ' d-none') + '">' +
                    '<label class="font-weight-bold" style="font-size:0.8rem !important;">Choose goals</label>' +
                    '<div id="bse_goals_list"><p class="text-muted small">Loading goals…</p></div></div>';
                break;
            default:
                h = '<p class="text-muted">No edit form for this section type.</p>';
        }
        return h;
    }

    /**
     * Collect form values from the edit panel.
     * @param {string} stype
     * @param {jQuery} $panel
     * @returns {{cfg: Object, content: string}}
     */
    function collectEditForm(stype, $panel) {
        var v = function(id) {
            return $panel.find('#' + id).val() || '';
        };
        var chk = function(id) {
            return $panel.find('#' + id).is(':checked');
        };

        var cfg = {};
        var content = '';

        switch (stype) {
            case 'hero':
                cfg = {
                    name: v('bse_name'),
                    title: v('bse_title'),
                    subtitle: v('bse_subtitle'),
                    bg_color: v('bse_bg_color'),
                    bg_image: v('bse_bg_image'),
                    photo_url: v('bse_photo_url')
                };
                break;
            case 'text':
                cfg = {heading: v('bse_heading'), body: v('bse_body')};
                break;
            case 'text_image':
                cfg = {
                    heading: v('bse_heading'),
                    body: v('bse_body'),
                    image_url: v('bse_image_url'),
                    image_alt: v('bse_image_alt'),
                    reversed: chk('bse_reversed')
                };
                break;
            case 'gallery':
                cfg = {
                    columns: parseInt(v('bse_columns'), 10) || 3,
                    items: collectGalleryItems($panel)
                };
                break;
            case 'skills':
                var skills = v('bse_skills').split('\n').filter(function(s) {
                    return s.trim();
                }).map(function(line) {
                    var parts = line.split(':');
                    var lvl = parseInt(parts[1], 10);
                    if (isNaN(lvl) || lvl < 1) { lvl = 1; }
                    if (lvl > 5) { lvl = 5; }
                    return {name: parts[0].trim(), level: lvl};
                });
                cfg = {heading: v('bse_heading'), skills: skills};
                break;
            case 'timeline':
                var tlItems = v('bse_items').split('\n').filter(function(s) {
                    return s.trim();
                }).map(function(line) {
                    var parts = line.split('|');
                    return {
                        date: (parts[0] || '').trim(),
                        title: (parts[1] || '').trim(),
                        description: (parts[2] || '').trim()
                    };
                });
                cfg = {heading: v('bse_heading'), items: tlItems};
                break;
            case 'badges':
                cfg = {heading: v('bse_heading'), show: chk('bse_show')};
                break;
            case 'completions':
                cfg = {heading: v('bse_heading'), show: chk('bse_show')};
                break;
            case 'social':
                var links = [];
                $panel.find('.bse-social-url').each(function() {
                    var url = $(this).val().trim();
                    if (url) {
                        links.push({platform: $(this).data('platform'), url: url});
                    }
                });
                cfg = {links: links};
                break;
            case 'cta':
                cfg = {
                    heading: v('bse_heading'),
                    body: v('bse_body'),
                    button_text: v('bse_button_text'),
                    button_url: v('bse_button_url'),
                    bg_color: v('bse_bg_color')
                };
                break;
            case 'divider':
                cfg = {style: v('bse_style'), spacing: v('bse_spacing')};
                break;
            case 'custom':
                cfg = {};
                content = v('bse_html');
                break;
            case 'chart':
                var seriesRaw = (v('bse_chart_series') || '').trim();
                var seriesList = seriesRaw
                    ? seriesRaw.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s; })
                    : [];
                cfg = {
                    heading: v('bse_heading'),
                    type: v('bse_chart_type') || 'bar',
                    orientation: v('bse_chart_orientation') || 'horizontal',
                    sort: v('bse_chart_sort') || 'input',
                    color: v('bse_chart_color') || '#0d6efd',
                    x_label: v('bse_chart_xlabel') || '',
                    y_label: v('bse_chart_ylabel') || '',
                    unit_suffix: v('bse_chart_unit') || '',
                    caption: v('bse_chart_caption') || '',
                    show_values: $panel.find('#bse_chart_showvalues').is(':checked'),
                    series: seriesList,
                    items: collectChartItems($panel)
                };
                break;
            case 'cloud':
                cfg = {
                    heading: v('bse_heading'),
                    color: v('bse_cloud_color') || '#0d6efd',
                    items: collectCloudItems($panel)
                };
                break;
            case 'quote':
                cfg = {
                    body: v('bse_body'),
                    attribution: v('bse_attribution'),
                    source: v('bse_source')
                };
                break;
            case 'stats':
                cfg = {
                    heading: v('bse_heading'),
                    items: collectStatsItems($panel)
                };
                break;
            case 'citations':
                cfg = {
                    heading: v('bse_heading'),
                    style: v('bse_citations_style') || 'plain',
                    items: collectCitationsItems($panel)
                };
                break;
            case 'files':
                cfg = {
                    heading: v('bse_heading'),
                    display: v('bse_files_display') || 'list',
                    items: collectFilesItems($panel)
                };
                break;
            case 'pagenav':
                cfg = {
                    heading: v('bse_heading'),
                    source: v('bse_pn_source') || 'collection',
                    collectionid: parseInt(v('bse_pn_collection'), 10) || 0,
                    pageids: collectPagenavItems($panel),
                    display: v('bse_pn_display') || 'pills',
                    show_descriptions: chk('bse_pn_showdescs')
                };
                break;
            case 'youtube':
                cfg = {
                    heading: v('bse_heading'),
                    url: v('bse_yt_url'),
                    description: v('bse_yt_desc'),
                    start: parseInt(v('bse_yt_start'), 10) || 0,
                    alignment: v('bse_yt_align') || 'full',
                    body: v('bse_yt_body') || ''
                };
                break;
            case 'alignment':
                cfg = {
                    heading: v('bse_heading'),
                    intro: v('bse_align_intro'),
                    source: 'freeform',
                    frameworkid: 0,
                    outcomes: collectAlignmentOutcomes($panel)
                };
                break;
            case 'goals':
                cfg = {
                    heading: v('bse_heading'),
                    mode: v('bse_goals_mode') || 'all_active',
                    goalids: collectGoalIds($panel)
                };
                break;
        }
        return {cfg: cfg, content: content};
    }

    /**
     * Collect chart data-point rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{label: string, value: number}>}
     */
    /**
     * Wire up the chart editor: tabs, visual type picker, drag-to-reorder on
     * the data table, and a debounced live preview that re-renders via the
     * local_byblos_render_chart_preview WS.
     *
     * @param {jQuery} $panel The section edit panel.
     * @param {Object} cfg Initial configdata.
     */
    function initChartEditor($panel, cfg) {
        var $chartContainer = $panel.find('#bse_chart_items');
        (cfg.items || []).forEach(function(item) {
            addChartItemRow($chartContainer, item, $panel);
        });
        if ($chartContainer.children().length === 0) {
            addChartItemRow($chartContainer, {}, $panel);
            addChartItemRow($chartContainer, {}, $panel);
        }
        $panel.find('.bse-chart-add').on('click', function() {
            addChartItemRow($chartContainer, {}, $panel);
            schedulePreviewRefresh($panel);
        });

        // Tab switching.
        $panel.find('.bse-chart-tabs .nav-link').on('click', function(ev) {
            ev.preventDefault();
            var key = $(this).attr('data-tab');
            $panel.find('.bse-chart-tabs .nav-link').removeClass('active');
            $(this).addClass('active');
            $panel.find('.bse-chart-tab-pane').removeClass('active');
            $panel.find('#bse-chart-' + key).addClass('active');
        });

        // Visual chart-type picker. Tiles toggle the hidden #bse_chart_type
        // input that the cfg collector reads.
        $panel.find('.bse-chart-typetile').on('click', function() {
            var t = $(this).attr('data-type');
            $panel.find('.bse-chart-typetile').removeClass('active');
            $(this).addClass('active');
            $panel.find('#bse_chart_type').val(t);
            $panel.find('.bse-chart-orientation').toggleClass('d-none', t !== 'bar');
            schedulePreviewRefresh($panel);
        });

        // Series-count change rebuilds rows to match.
        $panel.find('#bse_chart_series').on('change blur', function() {
            rebuildChartItemRows($panel);
            schedulePreviewRefresh($panel);
        });

        // Drag-to-reorder data rows.
        var $tbody = $panel.find('#bse_chart_items');
        $tbody.on('dragstart', '.bse-chart-item', function(ev) {
            var oe = ev.originalEvent || ev;
            $(this).addClass('bse-ci-dragging');
            if (oe.dataTransfer) {
                oe.dataTransfer.effectAllowed = 'move';
                // Some browsers refuse to start a drag without payload.
                oe.dataTransfer.setData('text/plain', 'row');
            }
        });
        $tbody.on('dragend', '.bse-chart-item', function() {
            $(this).removeClass('bse-ci-dragging');
            schedulePreviewRefresh($panel);
        });
        $tbody.on('dragover', '.bse-chart-item', function(ev) {
            ev.preventDefault();
            var $dragging = $tbody.find('.bse-ci-dragging');
            if (!$dragging.length || $dragging[0] === this) {
                return;
            }
            var rect = this.getBoundingClientRect();
            var after = ((ev.originalEvent || ev).clientY - rect.top) > rect.height / 2;
            if (after) {
                $(this).after($dragging);
            } else {
                $(this).before($dragging);
            }
        });

        // Anything in the form changes → debounced preview refresh.
        $panel.find('.bse-chart-editor-form').on('input change', function() {
            schedulePreviewRefresh($panel);
        });

        // Kick off an initial preview render.
        schedulePreviewRefresh($panel, 0);
    }

    /** @type {?number} Debounce timer for the chart preview refresh. */
    var chartPreviewTimer = null;

    /**
     * Refresh the chart preview pane from the current form state, debounced so
     * fast typing doesn't fire a WS call on every keystroke.
     *
     * @param {jQuery} $panel The section edit panel.
     * @param {number=} delay Optional override of the debounce window (ms).
     */
    function schedulePreviewRefresh($panel, delay) {
        if (delay === undefined) { delay = 220; }
        if (chartPreviewTimer) {
            clearTimeout(chartPreviewTimer);
        }
        chartPreviewTimer = setTimeout(function() {
            chartPreviewTimer = null;
            refreshChartPreview($panel);
        }, delay);
    }

    /**
     * Build the current configdata from the form state, send it to the
     * render_chart_preview WS, and drop the resulting SVG into the preview pane.
     *
     * @param {jQuery} $panel The section edit panel.
     */
    function refreshChartPreview($panel) {
        var $target = $panel.find('.bse-chart-preview-content');
        if (!$target.length) {
            return;
        }
        var seriesRaw = ($panel.find('#bse_chart_series').val() || '').trim();
        var seriesList = seriesRaw
            ? seriesRaw.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s; })
            : [];
        var cfg = {
            heading: $panel.find('#bse_heading').val() || '',
            type: $panel.find('#bse_chart_type').val() || 'bar',
            orientation: $panel.find('#bse_chart_orientation').val() || 'horizontal',
            sort: $panel.find('#bse_chart_sort').val() || 'input',
            color: $panel.find('#bse_chart_color').val() || '#0d6efd',
            x_label: $panel.find('#bse_chart_xlabel').val() || '',
            y_label: $panel.find('#bse_chart_ylabel').val() || '',
            unit_suffix: $panel.find('#bse_chart_unit').val() || '',
            caption: $panel.find('#bse_chart_caption').val() || '',
            show_values: $panel.find('#bse_chart_showvalues').is(':checked'),
            series: seriesList,
            items: collectChartItems($panel)
        };
        callExternal('local_byblos_render_chart_preview', {
            configdata: JSON.stringify(cfg)
        }).then(function(res) {
            $target.html(res && res.html ? res.html : '<p class="text-muted small mb-0">Empty preview.</p>');
            return res;
        }).catch(function() {
            $target.html('<p class="text-danger small mb-0">Preview failed to render.</p>');
        });
    }

    /**
     * Build the chart editor markup: two-pane layout with a tabbed form on the
     * left (Data / Appearance / Labels / Advanced) and a live preview pane on
     * the right.
     *
     * @param {Object} cfg Existing chart configdata.
     * @returns {string} HTML string.
     */
    function buildChartEditor(cfg) {
        cfg = cfg || {};
        var type = cfg.type || 'bar';
        var orientation = cfg.orientation || 'horizontal';
        var sort = cfg.sort || 'input';
        var series = (cfg.series || []).join(', ');
        var showValues = (cfg.show_values === undefined || cfg.show_values);

        var typeTile = function(key, label, iconSvg) {
            return '<button type="button" class="bse-chart-typetile' + (type === key ? ' active' : '') + '"'
                + ' data-type="' + key + '" title="' + escHtml(label) + '">'
                + iconSvg
                + '<span class="bse-chart-typetile-label">' + escHtml(label) + '</span>'
                + '</button>';
        };

        // Inline SVG glyphs so we don't depend on an icon font for the type tiles.
        var iconBar = '<svg viewBox="0 0 32 24" width="32" height="24" aria-hidden="true">'
            + '<rect x="2"  y="10" width="5" height="12" rx="1" fill="currentColor"/>'
            + '<rect x="10" y="6"  width="5" height="16" rx="1" fill="currentColor"/>'
            + '<rect x="18" y="14" width="5" height="8"  rx="1" fill="currentColor"/>'
            + '<rect x="26" y="3"  width="5" height="19" rx="1" fill="currentColor"/></svg>';
        var iconLine = '<svg viewBox="0 0 32 24" width="32" height="24" aria-hidden="true" fill="none"'
            + ' stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round">'
            + '<polyline points="2,20 9,12 16,16 23,6 30,10"/></svg>';
        var iconPie = '<svg viewBox="0 0 32 24" width="32" height="24" aria-hidden="true">'
            + '<circle cx="16" cy="12" r="10" fill="currentColor" opacity="0.35"/>'
            + '<path d="M16 12 L16 2 A10 10 0 0 1 24.66 17 Z" fill="currentColor"/></svg>';
        var iconDonut = '<svg viewBox="0 0 32 24" width="32" height="24" aria-hidden="true">'
            + '<path d="M16 2 A10 10 0 1 1 6 12 H10 A6 6 0 1 0 16 6 Z" fill="currentColor"/>'
            + '<path d="M16 2 A10 10 0 0 1 25.5 18 L21 15.4 A6 6 0 0 0 16 6 Z" fill="currentColor" opacity="0.55"/></svg>';

        // Form pane.
        var form =
            '<ul class="nav nav-tabs nav-tabs-sm bse-chart-tabs" role="tablist">' +
                '<li class="nav-item"><a class="nav-link active" data-tab="data" href="#bse-chart-data">Data</a></li>' +
                '<li class="nav-item"><a class="nav-link" data-tab="appearance" href="#bse-chart-appearance">Appearance</a></li>' +
                '<li class="nav-item"><a class="nav-link" data-tab="labels" href="#bse-chart-labels">Labels</a></li>' +
                '<li class="nav-item"><a class="nav-link" data-tab="advanced" href="#bse-chart-advanced">Advanced</a></li>' +
            '</ul>' +
            '<div class="bse-chart-tabcontent">' +

                // Tab: Data ----------------------------------------------------
                '<div class="bse-chart-tab-pane active" id="bse-chart-data">' +
                    '<table class="table table-sm bse-chart-table">' +
                        '<thead><tr>' +
                            '<th class="bse-ct-handle"></th>' +
                            '<th>Label</th>' +
                            '<th>Value(s)</th>' +
                            '<th>Colour</th>' +
                            '<th></th>' +
                        '</tr></thead>' +
                        '<tbody id="bse_chart_items"></tbody>' +
                    '</table>' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm bse-chart-add">' +
                        '<i class="fa fa-plus"></i> Add data point</button>' +
                '</div>' +

                // Tab: Appearance ----------------------------------------------
                '<div class="bse-chart-tab-pane" id="bse-chart-appearance">' +
                    '<label class="font-weight-bold small d-block mb-2">Chart type</label>' +
                    '<div class="bse-chart-typepicker mb-3">' +
                        typeTile('bar', 'Bar', iconBar) +
                        typeTile('line', 'Line', iconLine) +
                        typeTile('pie', 'Pie', iconPie) +
                        typeTile('donut', 'Donut', iconDonut) +
                        '<input type="hidden" id="bse_chart_type" value="' + escHtml(type) + '">' +
                    '</div>' +
                    '<div class="form-group bse-chart-orientation' +
                        (type === 'bar' ? '' : ' d-none') + '">' +
                        '<label for="bse_chart_orientation">Bar orientation</label>' +
                        '<select id="bse_chart_orientation" class="custom-select custom-select-sm">' +
                            '<option value="horizontal"' + (orientation === 'horizontal' ? ' selected' : '')
                                + '>Horizontal</option>' +
                            '<option value="vertical"' + (orientation === 'vertical' ? ' selected' : '')
                                + '>Vertical</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label for="bse_chart_sort">Sort order</label>' +
                        '<select id="bse_chart_sort" class="custom-select custom-select-sm">' +
                            '<option value="input"' + (sort === 'input' ? ' selected' : '')
                                + '>As entered</option>' +
                            '<option value="desc"' + (sort === 'desc' ? ' selected' : '')
                                + '>Largest first</option>' +
                            '<option value="asc"' + (sort === 'asc' ? ' selected' : '')
                                + '>Smallest first</option>' +
                        '</select>' +
                    '</div>' +
                    '<div class="form-group">' +
                        '<label for="bse_chart_color">Palette colour</label>' +
                        '<input type="color" class="form-control form-control-sm" id="bse_chart_color"' +
                            ' value="' + escHtml(cfg.color || '#0d6efd') + '">' +
                        '<small class="form-text text-muted">' +
                            'Default for every item; per-row pickers override it. Also seeds the palette' +
                            ' for multi-series and pie/donut charts.' +
                        '</small>' +
                    '</div>' +
                '</div>' +

                // Tab: Labels --------------------------------------------------
                '<div class="bse-chart-tab-pane" id="bse-chart-labels">' +
                    buildField('Heading', 'bse_heading', cfg.heading) +
                    buildField('X-axis label', 'bse_chart_xlabel', cfg.x_label || '') +
                    buildField('Y-axis label', 'bse_chart_ylabel', cfg.y_label || '') +
                    buildField('Unit suffix (e.g. %, hrs, $)', 'bse_chart_unit', cfg.unit_suffix || '') +
                    buildField('Caption / source', 'bse_chart_caption', cfg.caption || '') +
                '</div>' +

                // Tab: Advanced ------------------------------------------------
                '<div class="bse-chart-tab-pane" id="bse-chart-advanced">' +
                    buildField(
                        'Series names (comma-separated; leave empty for single series)',
                        'bse_chart_series',
                        series
                    ) +
                    '<div class="form-check mb-2">' +
                        '<input type="checkbox" class="form-check-input" id="bse_chart_showvalues"' +
                            (showValues ? ' checked' : '') + '>' +
                        '<label class="form-check-label" for="bse_chart_showvalues">' +
                            'Show numeric values on the chart</label>' +
                    '</div>' +
                '</div>' +
            '</div>';

        // Preview pane.
        var preview =
            '<div class="bse-chart-preview-header">' +
                '<i class="fa fa-eye"></i> Live preview' +
            '</div>' +
            '<div class="bse-chart-preview-content">' +
                '<p class="text-muted small mb-0">Edit any field — the chart re-renders here.</p>' +
            '</div>';

        return '<div class="bse-chart-editor">' +
            '<div class="bse-chart-editor-form">' + form + '</div>' +
            '<div class="bse-chart-editor-preview">' + preview + '</div>' +
        '</div>';
    }

    /**
     * Collect the data-point rows from the chart edit panel.
     *
     * Each row produces either {label, value, color?} (single-series) or
     * {label, values[], color?} (multi-series). Empty rows are dropped.
     *
     * @param {jQuery} $panel The section edit panel.
     * @returns {Array<Object>}
     */
    function collectChartItems($panel) {
        var items = [];
        $panel.find('.bse-chart-item').each(function() {
            var $item = $(this);
            var label = $item.find('.bse-ci-label').val() || '';
            var $values = $item.find('.bse-ci-value');
            var values = [];
            $values.each(function() {
                var v = parseFloat($(this).val());
                values.push(isNaN(v) ? 0 : v);
            });
            var color = ($item.find('.bse-ci-color').val() || '').trim();
            // Treat the default base colour as "no override" so we don't bake
            // it into every saved item.
            if (color.toLowerCase() === ($panel.find('#bse_chart_color').val() || '').toLowerCase()) {
                color = '';
            }
            if (!label && values.every(function(v) { return v === 0; })) {
                return;
            }
            var entry = {label: label};
            if (values.length <= 1) {
                entry.value = values[0] || 0;
            } else {
                entry.values = values;
            }
            if (color) {
                entry.color = color;
            }
            items.push(entry);
        });
        return items;
    }

    /**
     * Return the number of value inputs each chart row should expose, based on
     * the comma-separated series field. Always at least 1.
     * @param {jQuery} $panel
     * @returns {number}
     */
    function chartSeriesCount($panel) {
        var raw = ($panel.find('#bse_chart_series').val() || '').trim();
        if (!raw) { return 1; }
        return raw.split(',').filter(function(s) { return s.trim() !== ''; }).length || 1;
    }

    /**
     * Re-render every chart item row to match the current series count.
     * Preserves existing label / values / colour where possible.
     * @param {jQuery} $panel
     */
    function rebuildChartItemRows($panel) {
        var existing = collectChartItems($panel);
        var $container = $panel.find('#bse_chart_items');
        $container.empty();
        if (existing.length === 0) {
            // Seed two empty rows so the editor isn't blank.
            addChartItemRow($container, {}, $panel);
            addChartItemRow($container, {}, $panel);
            return;
        }
        existing.forEach(function(item) {
            addChartItemRow($container, item, $panel);
        });
    }

    /**
     * Append a chart data-point row to the container. The row exposes one
     * value input per series plus an optional colour override.
     * @param {jQuery} $container
     * @param {Object} item
     * @param {jQuery} $panel
     */
    function addChartItemRow($container, item, $panel) {
        item = item || {};
        var seriescount = chartSeriesCount($panel);
        var values = [];
        if (Array.isArray(item.values)) {
            values = item.values.slice();
        } else if (item.value !== undefined && item.value !== null) {
            values = [item.value];
        }
        while (values.length < seriescount) { values.push(''); }
        values = values.slice(0, seriescount);

        var valueCells = '';
        values.forEach(function(v, idx) {
            valueCells += '<input type="number" step="any"'
                + ' class="form-control form-control-sm bse-ci-value"'
                + ' placeholder="Value' + (seriescount > 1 ? ' ' + (idx + 1) : '') + '"'
                + ' value="'
                + escHtml((v !== null && v !== undefined && v !== '') ? String(v) : '') + '">';
        });

        var html = '<tr class="bse-chart-item" draggable="true">'
            + '<td class="bse-ci-handle" title="Drag to reorder">'
                + '<i class="fa fa-bars"></i></td>'
            + '<td><input type="text" class="form-control form-control-sm bse-ci-label"'
                + ' placeholder="Label" value="' + escHtml(item.label || '') + '"></td>'
            + '<td class="bse-ci-values">' + valueCells + '</td>'
            + '<td><input type="color" class="form-control form-control-sm bse-ci-color"'
                + ' value="' + escHtml(item.color || '#0d6efd')
                + '" title="Per-row colour override"></td>'
            + '<td class="bse-ci-actions">'
                + '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-ci-remove"'
                + ' title="Remove row"><i class="fa fa-times"></i></button>'
            + '</td>'
            + '</tr>';
        var $row = $(html);
        $row.find('.bse-ci-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect word-cloud rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{text: string, weight: number}>}
     */
    function collectCloudItems($panel) {
        var items = [];
        $panel.find('.bse-cloud-item').each(function() {
            var $item = $(this);
            var text = $item.find('.bse-cl-text').val() || '';
            var weight = parseInt($item.find('.bse-cl-weight').val(), 10);
            if (text) {
                if (isNaN(weight) || weight < 1) {
                    weight = 1;
                }
                if (weight > 10) {
                    weight = 10;
                }
                items.push({text: text, weight: weight});
            }
        });
        return items;
    }

    /**
     * Append a word-cloud row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addCloudItemRow($container, item) {
        item = item || {};
        var w = (item.weight !== null && item.weight !== undefined) ? item.weight : 5;
        var $row = $(
            '<div class="bse-cloud-item d-flex align-items-center mb-1" style="gap:0.4rem;">' +
            '<input type="text" class="form-control form-control-sm bse-cl-text" placeholder="Word" value="' +
            escHtml(item.text || '') + '">' +
            '<input type="number" min="1" max="10" class="form-control form-control-sm bse-cl-weight" ' +
            'placeholder="Weight" style="max-width:90px;" value="' + escHtml(String(w)) + '">' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-cl-remove">' +
            '<i class="fa fa-times"></i></button></div>'
        );
        $row.find('.bse-cl-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect stat-card rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{number: string, label: string, description: string}>}
     */
    function collectStatsItems($panel) {
        var items = [];
        $panel.find('.bse-stats-item').each(function() {
            var $item = $(this);
            items.push({
                number: $item.find('.bse-st-number').val() || '',
                label: $item.find('.bse-st-label').val() || '',
                description: $item.find('.bse-st-desc').val() || ''
            });
        });
        return items.slice(0, 4);
    }

    /**
     * Append a stat-card row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addStatsItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-stats-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">Stat card</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-st-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-st-number"' +
            ' placeholder="Number (e.g. 42, 3.2x, 87%)" value="' +
            escHtml(item.number || '') + '">' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-st-label" placeholder="Label" value="' +
            escHtml(item.label || '') + '">' +
            '<input type="text" class="form-control form-control-sm bse-st-desc" placeholder="Description (optional)" value="' +
            escHtml(item.description || '') + '">' +
            '</div>'
        );
        $row.find('.bse-st-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect citation rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{text: string, url: string}>}
     */
    function collectCitationsItems($panel) {
        var items = [];
        $panel.find('.bse-citations-item').each(function() {
            var $item = $(this);
            var text = $item.find('.bse-cit-text').val() || '';
            if (text.trim()) {
                items.push({
                    text: text,
                    url: $item.find('.bse-cit-url').val() || ''
                });
            }
        });
        return items;
    }

    /**
     * Append a citation row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addCitationsItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-citations-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">Reference</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-cit-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<textarea class="form-control form-control-sm mb-1 bse-cit-text" rows="2" placeholder="Citation text">' +
            escHtml(item.text || '') + '</textarea>' +
            '<input type="url" class="form-control form-control-sm bse-cit-url" placeholder="URL (optional)" value="' +
            escHtml(item.url || '') + '">' +
            '</div>'
        );
        $row.find('.bse-cit-remove').on('click', function() {
            $row.remove();
        });
        $container.append($row);
    }

    /**
     * Collect file rows from the edit panel.
     * @param {jQuery} $panel
     * @returns {Array<{url: string, title: string, description: string, type: string}>}
     */
    function collectFilesItems($panel) {
        var items = [];
        $panel.find('.bse-files-item').each(function() {
            var $item = $(this);
            var url = ($item.find('.bse-file-url').val() || '').trim();
            if (url) {
                items.push({
                    url: url,
                    title: $item.find('.bse-file-title').val() || '',
                    description: $item.find('.bse-file-desc').val() || '',
                    type: $item.find('.bse-file-type').val() || ''
                });
            }
        });
        return items;
    }

    /**
     * Append a file row to the container.
     * @param {jQuery} $container
     * @param {Object} item
     */
    function addFilesItemRow($container, item) {
        item = item || {};
        var $row = $(
            '<div class="bse-files-item card card-body p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1">' +
            '<small class="text-muted">File</small>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-file-remove">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<div class="input-group input-group-sm mb-1">' +
                '<input type="url" class="form-control form-control-sm bse-file-url" ' +
                'placeholder="URL (required)" value="' + escHtml(item.url || '') + '">' +
                '<div class="input-group-append">' +
                    '<button type="button" class="btn btn-outline-secondary btn-sm bse-file-library" ' +
                        'title="Select from library">' +
                        '<i class="fa fa-folder-open-o"></i> Library' +
                    '</button>' +
                '</div>' +
            '</div>' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-file-title" ' +
            'placeholder="Title (optional — defaults to filename)" value="' + escHtml(item.title || '') + '">' +
            '<input type="text" class="form-control form-control-sm mb-1 bse-file-desc" ' +
            'placeholder="Description (optional)" value="' + escHtml(item.description || '') + '">' +
            '<input type="text" class="form-control form-control-sm bse-file-type" ' +
            'placeholder="Type hint (optional — e.g. pdf, image, video)" value="' + escHtml(item.type || '') + '">' +
            '</div>'
        );
        $row.find('.bse-file-remove').on('click', function() {
            $row.remove();
        });

        /**
         * Open the artefact picker for this file row. Populates URL, and
         * title/type inputs if they are empty.
         */
        $row.find('.bse-file-library').on('click', function() {
            require(['local_byblos/artefact_picker'], function(Picker) {
                Picker.open({
                    typefilter: 'any',
                    title: 'Select from library',
                    onPick: function(artefact) {
                        $row.find('.bse-file-url').val(artefact.url || '');
                        var $titleInput = $row.find('.bse-file-title');
                        if (!($titleInput.val() || '').trim()) {
                            $titleInput.val(artefact.title || '');
                        }
                        var $typeInput = $row.find('.bse-file-type');
                        if (!($typeInput.val() || '').trim() && artefact.artefacttype) {
                            $typeInput.val(artefact.artefacttype);
                        }
                    }
                });
            });
        });

        $container.append($row);
    }

    /**
     * Collect the ordered array of page ids from the pagenav manual list.
     * @param {jQuery} $panel
     * @returns {number[]}
     */
    function collectPagenavItems($panel) {
        var ids = [];
        $panel.find('.bse-pn-item').each(function() {
            var id = parseInt($(this).data('page-id'), 10);
            if (id && !isNaN(id)) {
                ids.push(id);
            }
        });
        return ids;
    }

    /**
     * Append a pagenav page row to the manual-list container.
     * @param {jQuery} $container
     * @param {{id: number, title: string}} page
     */
    function addPagenavItemRow($container, page) {
        if (!page || !page.id) {
            return;
        }
        var $row = $(
            '<div class="bse-pn-item d-flex align-items-center mb-1" style="gap:0.4rem;"' +
            ' data-page-id="' + parseInt(page.id, 10) + '">' +
            '<span class="flex-fill" style="overflow:hidden !important; text-overflow:ellipsis !important;' +
            ' white-space:nowrap !important;">' + escHtml(page.title || ('Page #' + page.id)) + '</span>' +
            '<button type="button" class="btn btn-link btn-sm bse-pn-up p-0" title="Move up">' +
            '<i class="fa fa-arrow-up"></i></button>' +
            '<button type="button" class="btn btn-link btn-sm bse-pn-down p-0" title="Move down">' +
            '<i class="fa fa-arrow-down"></i></button>' +
            '<button type="button" class="btn btn-link btn-sm text-danger p-0 bse-pn-remove" title="Remove">' +
            '<i class="fa fa-times"></i></button></div>'
        );
        $row.find('.bse-pn-remove').on('click', function() {
            $row.remove();
        });
        $row.find('.bse-pn-up').on('click', function() {
            var $prev = $row.prev('.bse-pn-item');
            if ($prev.length) {
                $row.insertBefore($prev);
            }
        });
        $row.find('.bse-pn-down').on('click', function() {
            var $next = $row.next('.bse-pn-item');
            if ($next.length) {
                $row.insertAfter($next);
            }
        });
        $container.append($row);
    }

    /**
     * Open the inline page-picker modal for a pagenav section.
     *
     * Fetches the current user's pages (excluding the host page) via
     * `local_byblos_list_user_pages` and lets the user click one to append
     * it to the manual list. Skips pages already in the list.
     *
     * @param {jQuery} $panel    The pagenav edit panel.
     * @param {jQuery} $container The manual-list container.
     */
    function openPagenavPagePicker($panel, $container) {
        var existing = {};
        $container.find('.bse-pn-item').each(function() {
            existing[parseInt($(this).data('page-id'), 10)] = true;
        });

        // Build a lightweight inline modal.
        var $overlay = $(
            '<div class="bse-pn-picker-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);' +
            'z-index:2000;display:flex;align-items:center;justify-content:center;">' +
            '<div class="card" style="width:min(520px, 90vw);max-height:80vh;display:flex;flex-direction:column;">' +
            '<div class="card-header d-flex justify-content-between align-items-center">' +
            '<strong>Pick a page</strong>' +
            '<button type="button" class="btn btn-link btn-sm bse-pn-picker-close p-0">' +
            '<i class="fa fa-times"></i></button></div>' +
            '<div class="card-body bse-pn-picker-body" style="overflow:auto;padding:0.5rem;">' +
            '<p class="text-muted small mb-0 p-2">Loading your pages...</p>' +
            '</div></div></div>'
        );
        $('body').append($overlay);

        var close = function() {
            $overlay.remove();
        };
        $overlay.find('.bse-pn-picker-close').on('click', close);
        $overlay.on('click', function(ev) {
            if (ev.target === $overlay[0]) {
                close();
            }
        });

        callExternal('local_byblos_list_user_pages', {
            excludepageid: pageId
        }).then(function(rows) {
            var $body = $overlay.find('.bse-pn-picker-body');
            $body.empty();
            var pages = (rows || []).filter(function(p) {
                return !existing[parseInt(p.id, 10)];
            });
            if (pages.length === 0) {
                $body.html('<p class="text-muted small p-2 mb-0">No more pages to add.</p>');
                return pages;
            }
            var $list = $('<div class="list-group list-group-flush"></div>');
            pages.forEach(function(p) {
                var $item = $(
                    '<button type="button" class="list-group-item list-group-item-action" ' +
                    'data-page-id="' + parseInt(p.id, 10) + '">' +
                    '<div style="font-weight:600 !important;">' + escHtml(p.title || '') + '</div>' +
                    '<small class="text-muted">' + escHtml(p.status || '') + '</small></button>'
                );
                $item.on('click', function() {
                    addPagenavItemRow($container, {id: parseInt(p.id, 10), title: p.title || ''});
                    close();
                });
                $list.append($item);
            });
            $body.append($list);
            return pages;
        }).catch(function(err) {
            $overlay.find('.bse-pn-picker-body').html(
                '<p class="text-danger small p-2 mb-0">Failed to load pages.</p>'
            );
            Notification.exception(err);
        });
    }

    /**
     * Initialise the pagenav edit panel: async-load collections, wire up
     * source-toggle show/hide, populate the manual list, bind Add-page.
     *
     * @param {jQuery} $panel
     * @param {Object} cfg
     */
    function initPagenavPanel($panel, cfg) {
        cfg = cfg || {};

        var $sourceSel = $panel.find('#bse_pn_source');
        var $collBlock = $panel.find('.bse-pn-collection-block');
        var $manualBlock = $panel.find('.bse-pn-manual-block');
        var $displaySel = $panel.find('#bse_pn_display');
        var $showdescsWrap = $panel.find('.bse-pn-showdescs-wrap');
        var $collSel = $panel.find('#bse_pn_collection');
        var $itemsContainer = $panel.find('#bse_pn_items');

        /**
         * Show / hide the source-specific blocks based on the select.
         */
        function applySourceVisibility() {
            var src = $sourceSel.val();
            if (src === 'manual') {
                $collBlock.hide();
                $manualBlock.show();
            } else {
                $collBlock.show();
                $manualBlock.hide();
            }
        }

        /**
         * Show/hide the "show descriptions" checkbox based on display mode.
         */
        function applyDisplayVisibility() {
            var d = $displaySel.val();
            if (d === 'cards') {
                $showdescsWrap.show();
            } else {
                $showdescsWrap.hide();
            }
        }

        applySourceVisibility();
        applyDisplayVisibility();

        $sourceSel.on('change', applySourceVisibility);
        $displaySel.on('change', applyDisplayVisibility);

        // Async-load the user's collections.
        callExternal('local_byblos_list_user_collections', {
            withpageid: 0
        }).then(function(rows) {
            $collSel.empty();
            var list = rows || [];
            if (list.length === 0) {
                $collSel.append('<option value="0">You have no collections yet.</option>');
                return list;
            }
            var selectedId = parseInt(cfg.collectionid, 10) || 0;
            list.forEach(function(c) {
                var cid = parseInt(c.id, 10);
                var label = escHtml(c.title || '') + ' (' + (c.pagecount || 0) + ')';
                var isSel = (cid === selectedId) ? ' selected' : '';
                $collSel.append('<option value="' + cid + '"' + isSel + '>' + label + '</option>');
            });
            return list;
        }).catch(Notification.exception);

        // Populate manual-list rows. We need titles for existing ids — fetch via list_user_pages.
        var existingIds = Array.isArray(cfg.pageids) ? cfg.pageids.map(function(x) {
            return parseInt(x, 10);
        }).filter(function(x) {
            return x > 0;
        }) : [];

        if (existingIds.length) {
            callExternal('local_byblos_list_user_pages', {
                excludepageid: 0
            }).then(function(rows) {
                var byId = {};
                (rows || []).forEach(function(p) {
                    byId[parseInt(p.id, 10)] = p;
                });
                existingIds.forEach(function(id) {
                    var p = byId[id];
                    addPagenavItemRow($itemsContainer, {
                        id: id,
                        title: p ? p.title : ('Page #' + id)
                    });
                });
                return rows;
            }).catch(function(err) {
                // On failure still add rows with fallback titles.
                existingIds.forEach(function(id) {
                    addPagenavItemRow($itemsContainer, {id: id, title: 'Page #' + id});
                });
                Notification.exception(err);
            });
        }

        $panel.find('.bse-pn-add').on('click', function() {
            openPagenavPagePicker($panel, $itemsContainer);
        });
    }

    /**
     * Build the gallery editor markup: a column-count button group, a visual
     * thumbnail grid that mirrors the published layout, and a detail panel
     * that expands inline below the grid when a tile is selected.
     *
     * @param {Object} cfg Existing gallery configdata.
     * @returns {string} HTML string.
     */
    function buildGalleryEditor(cfg) {
        cfg = cfg || {};
        var cols = parseInt(cfg.columns, 10) || 3;
        if (cols < 1) { cols = 1; }
        if (cols > 4) { cols = 4; }

        var colsBtns = '';
        [1, 2, 3, 4].forEach(function(n) {
            colsBtns += '<button type="button" class="bse-gallery-coltile'
                + (n === cols ? ' active' : '') + '"'
                + ' data-cols="' + n + '" title="' + n + ' column' + (n > 1 ? 's' : '') + '">'
                + n + '</button>';
        });

        return ''
            + '<div class="bse-gallery-editor">'
                + '<div class="bse-gallery-toolbar">'
                    + '<label class="small font-weight-bold mr-2 mb-0">Columns</label>'
                    + '<div class="bse-gallery-coltiles">' + colsBtns + '</div>'
                    + '<input type="hidden" id="bse_columns" value="' + cols + '">'
                + '</div>'
                + '<div class="bse-gallery-grid" id="bse_gallery_items"'
                    + ' data-cols="' + cols + '">'
                + '</div>'
                + '<div class="bse-gallery-detail d-none">'
                    + '<div class="bse-gd-thumb"></div>'
                    + '<div class="bse-gd-fields">'
                        + '<label class="small mb-1 d-block">Title</label>'
                        + '<input type="text" class="form-control form-control-sm bse-gd-title mb-2">'
                        + '<label class="small mb-1 d-block">Description</label>'
                        + '<input type="text" class="form-control form-control-sm bse-gd-desc mb-2">'
                        + '<div class="bse-gd-upload"></div>'
                        + '<button type="button" class="btn btn-outline-danger btn-sm bse-gd-remove mt-2">'
                            + '<i class="fa fa-trash"></i> Remove image'
                        + '</button>'
                    + '</div>'
                + '</div>'
            + '</div>';
    }

    /**
     * Collect gallery items from the visual editor.
     *
     * Each tile stores its title / image_url / description in three hidden
     * <input>s. We walk them in DOM order so drag-reorder is honoured.
     *
     * @param {jQuery} $panel The section edit panel.
     * @returns {Array<{title: string, image_url: string, description: string}>}
     */
    function collectGalleryItems($panel) {
        var items = [];
        $panel.find('.bse-gallery-tile[data-tile="1"]').each(function() {
            var $t = $(this);
            items.push({
                title: $t.find('.bse-gi-title').val() || '',
                image_url: $t.find('.bse-gi-image').val() || '',
                description: $t.find('.bse-gi-desc').val() || ''
            });
        });
        return items;
    }

    /**
     * Append a visual gallery tile to the grid.
     *
     * @param {jQuery} $grid The grid container.
     * @param {Object} item {title, image_url, description}.
     * @param {jQuery} $panel Section edit panel.
     */
    function addGalleryTile($grid, item, $panel) {
        item = item || {};
        var html = '<div class="bse-gallery-tile" data-tile="1" draggable="true">'
            + '<div class="bse-gallery-tile-thumb">'
                + (item.image_url
                    ? '<img alt="" src="' + escHtml(item.image_url) + '">'
                    : '<i class="fa fa-image bse-gallery-tile-empty"></i>')
            + '</div>'
            + '<div class="bse-gallery-tile-caption">'
                + '<span class="bse-gallery-tile-title">'
                + (item.title ? escHtml(item.title) : '<em class="text-muted">Untitled</em>')
                + '</span>'
            + '</div>'
            + '<button type="button" class="bse-gallery-tile-remove"'
                + ' title="Remove" aria-label="Remove"><i class="fa fa-times"></i></button>'
            + '<input type="hidden" class="bse-gi-title" value="' + escHtml(item.title || '') + '">'
            + '<input type="hidden" class="bse-gi-image" value="' + escHtml(item.image_url || '') + '">'
            + '<input type="hidden" class="bse-gi-desc" value="' + escHtml(item.description || '') + '">'
            + '</div>';
        var $tile = $(html);
        $tile.on('click', function(ev) {
            // Don't open the detail panel when the user is clicking the remove button.
            if ($(ev.target).closest('.bse-gallery-tile-remove').length) {
                return;
            }
            selectGalleryTile($panel, $tile);
        });
        $tile.find('.bse-gallery-tile-remove').on('click', function() {
            if ($panel.data('selected-tile') && $panel.data('selected-tile').is($tile)) {
                $panel.find('.bse-gallery-detail').addClass('d-none');
                $panel.data('selected-tile', null);
            }
            $tile.remove();
        });
        $grid.append($tile);
    }

    /**
     * Re-render the lightweight tile face (thumbnail image, title caption)
     * from the hidden inputs. Called whenever the detail panel mutates a tile.
     *
     * @param {jQuery} $tile
     */
    function refreshGalleryTileFace($tile) {
        var img = $tile.find('.bse-gi-image').val();
        var title = $tile.find('.bse-gi-title').val();
        var $thumb = $tile.find('.bse-gallery-tile-thumb');
        if (img) {
            $thumb.html('<img alt="" src="' + escHtml(img) + '">');
        } else {
            $thumb.html('<i class="fa fa-image bse-gallery-tile-empty"></i>');
        }
        $tile.find('.bse-gallery-tile-title').html(
            title ? escHtml(title) : '<em class="text-muted">Untitled</em>'
        );
    }

    /**
     * Mark a tile as selected and populate the inline detail panel with its
     * current values. The Upload widget is rebuilt against the selected tile's
     * hidden image input so picks land in the right place.
     *
     * @param {jQuery} $panel The section edit panel.
     * @param {jQuery} $tile The tile to focus.
     */
    function selectGalleryTile($panel, $tile) {
        $panel.find('.bse-gallery-tile').removeClass('active');
        $tile.addClass('active');
        $panel.data('selected-tile', $tile);

        var $detail = $panel.find('.bse-gallery-detail').removeClass('d-none');
        var $title = $detail.find('.bse-gd-title');
        var $desc  = $detail.find('.bse-gd-desc');

        $title.val($tile.find('.bse-gi-title').val() || '');
        $desc.val($tile.find('.bse-gi-desc').val() || '');

        $title.off('.bsegd').on('input.bsegd', function() {
            $tile.find('.bse-gi-title').val($(this).val());
            refreshGalleryTileFace($tile);
        });
        $desc.off('.bsegd').on('input.bsegd', function() {
            $tile.find('.bse-gi-desc').val($(this).val());
        });
        $detail.find('.bse-gd-remove').off('.bsegd').on('click.bsegd', function() {
            $tile.find('.bse-gi-image').val('');
            refreshGalleryTileFace($tile);
            rebuildGalleryUploadWidget($panel, $tile);
        });

        var $thumb = $detail.find('.bse-gd-thumb');
        var img = $tile.find('.bse-gi-image').val();
        $thumb.html(img
            ? '<img alt="" src="' + escHtml(img) + '">'
            : '<div class="bse-gd-thumb-empty"><i class="fa fa-image"></i></div>');

        rebuildGalleryUploadWidget($panel, $tile);
    }

    /**
     * (Re)mount the file-upload widget inside the detail panel, bound to the
     * selected tile's hidden image input. We rebuild on each selection so the
     * widget always targets the right tile.
     *
     * @param {jQuery} $panel
     * @param {jQuery} $tile
     */
    function rebuildGalleryUploadWidget($panel, $tile) {
        var sesskey = (window.M && window.M.cfg && window.M.cfg.sesskey) || '';
        var $hidden = $tile.find('.bse-gi-image');
        var $slot = $panel.find('.bse-gd-upload').empty();
        Upload.createWidget(
            $slot[0],
            pageId,
            sesskey,
            $hidden.val() || null,
            function(url) {
                $hidden.val(url || '');
                refreshGalleryTileFace($tile);
                var $thumb = $panel.find('.bse-gd-thumb');
                $thumb.html(url
                    ? '<img alt="" src="' + escHtml(url) + '">'
                    : '<div class="bse-gd-thumb-empty"><i class="fa fa-image"></i></div>');
            }
        );
    }

    /**
     * Wire up the gallery editor: column-count tiles, initial tiles, drag-to-
     * reorder, "+" add tile, and the per-tile selection handlers.
     *
     * @param {jQuery} $panel The section edit panel.
     * @param {Object} cfg Initial configdata.
     */
    function initGalleryEditor($panel, cfg) {
        var $grid = $panel.find('#bse_gallery_items');

        // Render existing tiles.
        (cfg.items || []).forEach(function(item) {
            addGalleryTile($grid, item, $panel);
        });

        // Trailing "+" add tile (not a data tile — collectGalleryItems ignores it).
        var $addTile = $(
            '<div class="bse-gallery-tile bse-gallery-tile-add" data-tile="0"'
            + ' title="Add image" aria-label="Add image">'
            + '<i class="fa fa-plus"></i><span class="small mt-1">Add</span>'
            + '</div>'
        );
        $addTile.on('click', function() {
            addGalleryTile($grid, {}, $panel);
            // Move the "+" tile to the end so it always trails the data tiles.
            $addTile.appendTo($grid);
            // Select the new tile so the detail panel + upload widget are
            // ready immediately.
            var $last = $grid.find('.bse-gallery-tile[data-tile="1"]').last();
            selectGalleryTile($panel, $last);
        });
        $grid.append($addTile);

        // Column-count buttons.
        $panel.find('.bse-gallery-coltile').on('click', function() {
            var n = parseInt($(this).attr('data-cols'), 10) || 3;
            $panel.find('.bse-gallery-coltile').removeClass('active');
            $(this).addClass('active');
            $panel.find('#bse_columns').val(n);
            $grid.attr('data-cols', n);
        });

        // Drag-to-reorder data tiles.
        $grid.on('dragstart', '.bse-gallery-tile[data-tile="1"]', function(ev) {
            var oe = ev.originalEvent || ev;
            $(this).addClass('bse-gallery-tile-dragging');
            if (oe.dataTransfer) {
                oe.dataTransfer.effectAllowed = 'move';
                oe.dataTransfer.setData('text/plain', 'tile');
            }
        });
        $grid.on('dragend', '.bse-gallery-tile', function() {
            $(this).removeClass('bse-gallery-tile-dragging');
            // Re-pin the add tile to the end after a reorder.
            $addTile.appendTo($grid);
        });
        $grid.on('dragover', '.bse-gallery-tile[data-tile="1"]', function(ev) {
            ev.preventDefault();
            var $dragging = $grid.find('.bse-gallery-tile-dragging');
            if (!$dragging.length || $dragging[0] === this) {
                return;
            }
            var rect = this.getBoundingClientRect();
            var midX = rect.left + rect.width / 2;
            var clientX = (ev.originalEvent || ev).clientX;
            if (clientX > midX) {
                $(this).after($dragging);
            } else {
                $(this).before($dragging);
            }
        });
    }

    /**
     * Toggle the inline edit panel for a section card.
     * @param {jQuery} $card
     */
    function toggleEditPanel($card) {
        var $panel = $card.find('.byblos-edit-panel');

        // Toggle off.
        if ($panel.is(':visible')) {
            teardownRichFields();
            $panel.hide().empty();
            return;
        }

        var stype = $card.data('sectiontype');
        var raw = $card.attr('data-configdata') || '{}';
        var cfg;
        try {
            cfg = JSON.parse(raw);
        } catch (ex) {
            cfg = {};
        }
        var content = $card.attr('data-content') || '';

        // The reflection section uses a Moodle-managed TinyMCE editor (with the
        // satsrecorder plugin), which only works inside a server-rendered form.
        // Load it through a fragment into a modal instead of the inline panel.
        if (stype === 'reflection') {
            openReflectionModal($card);
            return;
        }

        var html = buildEditForm(stype, cfg, content);
        html += '<div class="mt-2">' +
            '<button class="btn btn-primary btn-sm bse-save-section">' +
            '<i class="fa fa-check"></i> Save</button> ' +
            '<button class="btn btn-secondary btn-sm bse-cancel-section">' +
            'Cancel</button></div>';

        $panel.html(html).show();

        // Bring the newly-opened panel into view and focus its first field so
        // the user knows the Edit button worked (the panel can otherwise open
        // below the fold on long pages or near the bottom of the stack).
        scrollIntoViewSafely($panel);
        focusFirstField($panel);

        // Mount upload widgets on any image fields in the panel.
        initImageFields($panel);

        // Attach TinyMCE to any rich-text textareas.
        initRichFields($panel);

        // Wire HTML syntax highlighting on any code editor textareas.
        initCodeEditors($panel);

        // Populate gallery items if gallery type.
        if (stype === 'gallery') {
            initGalleryEditor($panel, cfg);
        }

        // Populate repeating-row widgets for the academic section types.
        if (stype === 'chart') {
            initChartEditor($panel, cfg);
        }
        if (stype === 'cloud') {
            var $cloudContainer = $panel.find('#bse_cloud_items');
            (cfg.items || []).forEach(function(item) {
                addCloudItemRow($cloudContainer, item);
            });
            $panel.find('.bse-cloud-add').on('click', function() {
                addCloudItemRow($cloudContainer, {});
            });
        }
        if (stype === 'stats') {
            var $statsContainer = $panel.find('#bse_stats_items');
            (cfg.items || []).forEach(function(item) {
                addStatsItemRow($statsContainer, item);
            });
            $panel.find('.bse-stats-add').on('click', function() {
                if ($statsContainer.find('.bse-stats-item').length >= 4) {
                    return;
                }
                addStatsItemRow($statsContainer, {});
            });
        }
        if (stype === 'citations') {
            var $citContainer = $panel.find('#bse_citations_items');
            (cfg.items || []).forEach(function(item) {
                addCitationsItemRow($citContainer, item);
            });
            $panel.find('.bse-citations-add').on('click', function() {
                addCitationsItemRow($citContainer, {});
            });
        }
        if (stype === 'files') {
            var $filesContainer = $panel.find('#bse_files_items');
            (cfg.items || []).forEach(function(item) {
                addFilesItemRow($filesContainer, item);
            });
            $panel.find('.bse-files-add').on('click', function() {
                addFilesItemRow($filesContainer, {});
            });
        }
        if (stype === 'pagenav') {
            initPagenavPanel($panel, cfg);
        }
        if (stype === 'alignment') {
            initAlignmentEditor($panel, cfg);
        }
        if (stype === 'goals') {
            initGoalsEditor($panel, cfg);
        }

        // Save handler.
        $panel.find('.bse-save-section').on('click', function() {
            teardownRichFields();
            var result = collectEditForm(stype, $panel);
            var sectionId = parseInt($card.data('section-id'), 10);

            callExternal('local_byblos_update_section', {
                sectionid: sectionId,
                configdata: JSON.stringify(result.cfg),
                content: result.content || ''
            }).then(function(data) {
                if (data && data.rendered) {
                    $card.find('.byblos-section-preview').html(data.rendered);

                    // Initialise contenteditable on text sections.
                    if (stype === 'text' || stype === 'custom') {
                        InlineEditor.initSection($card);
                    }
                }
                $card.attr('data-configdata', JSON.stringify(result.cfg));
                $card.attr('data-content', result.content || '');
                $panel.hide().empty();
                return;
            }).catch(Notification.exception);
        });

        // Cancel handler.
        $panel.find('.bse-cancel-section').on('click', function() {
            teardownRichFields();
            $panel.hide().empty();
        });
    }

    // ================================================================
    // Reflection / Alignment / Goals editors
    // ================================================================

    /**
     * Open the reflection editor in a modal. The body is a server-rendered
     * moodleform whose `editor` element attaches TinyMCE + satsrecorder.
     * @param {jQuery} $card
     */
    function openReflectionModal($card) {
        var sectionId = parseInt($card.data('section-id'), 10);
        var contextId = (window.M && window.M.cfg && window.M.cfg.contextid) || 1;
        Str.get_strings([
            {key: 'reflection_edit_title', component: 'local_byblos'},
            {key: 'savechanges', component: 'core'}
        ]).then(function(s) {
            return ModalSaveCancel.create({
                title: s[0],
                body: Fragment.loadFragment('local_byblos', 'reflection_editor', contextId,
                    {sectionid: sectionId}),
                buttons: {save: s[1]},
                large: true
            });
        }).then(function(modal) {
            modal.getRoot().on(ModalEvents.save, function(e) {
                e.preventDefault();
                if (window.tinyMCE && window.tinyMCE.triggerSave) {
                    window.tinyMCE.triggerSave();
                }
                var $r = modal.getRoot();
                callExternal('local_byblos_save_reflection', {
                    sectionid: sectionId,
                    heading: $r.find('[name="heading"]').val() || '',
                    framework: $r.find('[name="framework"]').val() || 'gibbs',
                    intro: $r.find('[name="intro"]').val() || '',
                    bodyhtml: $r.find('[name="bodyhtml_editor[text]"]').val() || '',
                    draftitemid: parseInt($r.find('[name="bodyhtml_editor[itemid]"]').val(), 10) || 0
                }).then(function(data) {
                    if (data && data.rendered) {
                        $card.find('.byblos-section-preview').html(data.rendered);
                    }
                    modal.destroy();
                    return;
                }).catch(Notification.exception);
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Open a modal to pick an artefact or page as evidence.
     * @param {Function} onPick Callback receiving {type, id, title}.
     */
    function pickEvidence(onPick) {
        var requests = Ajax.call([
            {methodname: 'local_byblos_list_artefacts', args: {}},
            {methodname: 'local_byblos_list_user_pages', args: {excludepageid: 0}}
        ]);
        $.when(requests[0], requests[1]).then(function(artefacts, pages) {
            var html = '<h6>Artefacts</h6><div class="list-group mb-2">';
            (artefacts || []).forEach(function(a) {
                html += '<button type="button" class="list-group-item list-group-item-action bse-pick" ' +
                    'data-type="artefact" data-id="' + a.id + '" data-title="' + escHtml(a.title) + '">' +
                    escHtml(a.title) + '</button>';
            });
            html += '</div><h6>Pages</h6><div class="list-group">';
            (pages || []).forEach(function(p) {
                html += '<button type="button" class="list-group-item list-group-item-action bse-pick" ' +
                    'data-type="page" data-id="' + p.id + '" data-title="' + escHtml(p.title) + '">' +
                    escHtml(p.title) + '</button>';
            });
            html += '</div>';
            return ModalSaveCancel.create({title: 'Add evidence', body: html});
        }).then(function(modal) {
            modal.getRoot().on('click', '.bse-pick', function() {
                onPick({
                    type: $(this).data('type'),
                    id: parseInt($(this).data('id'), 10),
                    title: '' + $(this).data('title')
                });
                modal.destroy();
            });
            modal.show();
            return modal;
        }).catch(Notification.exception);
    }

    /**
     * Build an evidence chip element.
     * @param {Object} ev {type, id, title}
     * @returns {jQuery}
     */
    function alignmentChip(ev) {
        return $('<span class="bse-align-chip badge badge-light border mr-1" ' +
            'data-type="' + escHtml(ev.type) + '" data-id="' + (ev.id || 0) + '">' +
            escHtml(ev.title || '') +
            ' <a href="#" class="bse-align-chip-remove text-danger">&times;</a></span>');
    }

    /**
     * Append an outcome row to the alignment editor.
     * @param {jQuery} $container
     * @param {Object} outcome
     */
    function addAlignmentOutcomeRow($container, outcome) {
        var $row = $('<div class="bse-align-outcome card card-body p-2 mb-2"></div>');
        $row.append('<div class="input-group input-group-sm mb-1">' +
            '<input type="text" class="form-control bse-align-text" placeholder="Outcome / criterion" value="' +
            escHtml(outcome.text || '') + '">' +
            '<div class="input-group-append">' +
            '<button type="button" class="btn btn-outline-danger bse-align-remove">' +
            '<i class="fa fa-trash"></i></button></div></div>');
        var $chips = $('<div class="bse-align-chips d-flex flex-wrap mb-1"></div>');
        (outcome.evidence || []).forEach(function(ev) {
            $chips.append(alignmentChip(ev));
        });
        $row.append($chips);
        $row.append('<input type="text" class="form-control form-control-sm bse-align-note mb-1" ' +
            'placeholder="How this evidence meets the outcome (optional)" value="' +
            escHtml(outcome.note || '') + '">');
        $row.append('<button type="button" class="btn btn-outline-secondary btn-sm bse-align-addevidence">' +
            '<i class="fa fa-link"></i> Add evidence</button>');
        $container.append($row);

        $row.find('.bse-align-remove').on('click', function() {
            $row.remove();
        });
        $row.find('.bse-align-addevidence').on('click', function() {
            pickEvidence(function(ev) {
                $chips.append(alignmentChip(ev));
            });
        });
        $chips.on('click', '.bse-align-chip-remove', function(ev) {
            ev.preventDefault();
            $(this).closest('.bse-align-chip').remove();
        });
    }

    /**
     * Initialise the alignment (outcome map) editor.
     * @param {jQuery} $panel
     * @param {Object} cfg
     */
    function initAlignmentEditor($panel, cfg) {
        var $outcomes = $panel.find('#bse_align_outcomes');
        (cfg.outcomes || []).forEach(function(o) {
            addAlignmentOutcomeRow($outcomes, o);
        });
        if (!$outcomes.children().length) {
            addAlignmentOutcomeRow($outcomes, {});
        }
        $panel.find('.bse-align-add').on('click', function() {
            addAlignmentOutcomeRow($outcomes, {});
        });

        // Optionally surface a competency-framework import (only when enabled).
        callExternal('local_byblos_list_competency_frameworks', {}).then(function(frameworks) {
            if (!frameworks || !frameworks.length) {
                return frameworks;
            }
            var $sel = $panel.find('#bse_align_framework');
            frameworks.forEach(function(fw) {
                $sel.append($('<option>').val(fw.id).text(fw.shortname));
            });
            $panel.find('.bse-align-import').removeClass('d-none');
            return frameworks;
        }).catch(function() {
            return null;
        });

        $panel.find('.bse-align-import-btn').on('click', function() {
            var fwid = parseInt($panel.find('#bse_align_framework').val(), 10) || 0;
            if (!fwid) {
                return;
            }
            callExternal('local_byblos_list_framework_competencies', {frameworkid: fwid})
                .then(function(comps) {
                    (comps || []).forEach(function(c) {
                        addAlignmentOutcomeRow($outcomes, {text: c.label, evidence: [], note: ''});
                    });
                    return comps;
                }).catch(Notification.exception);
        });
    }

    /**
     * Collect outcomes from the alignment editor.
     * @param {jQuery} $panel
     * @returns {Array}
     */
    function collectAlignmentOutcomes($panel) {
        var outcomes = [];
        $panel.find('.bse-align-outcome').each(function() {
            var $row = $(this);
            var text = ($row.find('.bse-align-text').val() || '').trim();
            if (!text) {
                return;
            }
            var evidence = [];
            $row.find('.bse-align-chip').each(function() {
                evidence.push({
                    type: '' + $(this).data('type'),
                    id: parseInt($(this).data('id'), 10) || 0,
                    title: $(this).clone().children().remove().end().text().trim()
                });
            });
            outcomes.push({
                text: text,
                evidence: evidence,
                note: ($row.find('.bse-align-note').val() || '').trim()
            });
        });
        return outcomes;
    }

    /**
     * Initialise the goals-section editor (heading + mode + goal multiselect).
     * @param {jQuery} $panel
     * @param {Object} cfg
     */
    function initGoalsEditor($panel, cfg) {
        var selected = {};
        (cfg.goalids || []).forEach(function(id) {
            selected[parseInt(id, 10)] = true;
        });
        $panel.find('#bse_goals_mode').on('change', function() {
            $panel.find('.bse-goals-select-block').toggleClass('d-none', $(this).val() !== 'selected');
        });
        // userid 0 => the current user (the page owner editing their own page).
        callExternal('local_byblos_list_goals', {userid: 0}).then(function(goals) {
            var $list = $panel.find('#bse_goals_list');
            $list.empty();
            if (!goals || !goals.length) {
                $list.append('<p class="text-muted small">You have no goals yet. Create them on your dashboard.</p>');
                return goals;
            }
            goals.forEach(function(g) {
                var checked = selected[g.id] ? ' checked' : '';
                $list.append('<div class="form-check">' +
                    '<input type="checkbox" class="form-check-input bse-goal-check" value="' + g.id + '"' +
                    checked + ' id="bse_goal_' + g.id + '">' +
                    '<label class="form-check-label small" for="bse_goal_' + g.id + '">' +
                    escHtml(g.title) + '</label></div>');
            });
            return goals;
        }).catch(Notification.exception);
    }

    /**
     * Collect the selected goal ids from the goals editor.
     * @param {jQuery} $panel
     * @returns {Array}
     */
    function collectGoalIds($panel) {
        var ids = [];
        $panel.find('.bse-goal-check:checked').each(function() {
            ids.push(parseInt($(this).val(), 10));
        });
        return ids;
    }

    // ================================================================
    // Preview Toggle
    // ================================================================

    /**
     * Wire up the Edit/Preview mode toggle button.
     */
    function initPreviewToggle() {
        $('#byblos-preview-toggle').on('click', function() {
            $root.toggleClass('byblos-preview-mode');
            var inPreview = $root.hasClass('byblos-preview-mode');
            $(this).html(
                inPreview
                    ? '<i class="fa fa-pencil"></i> Edit'
                    : '<i class="fa fa-eye"></i> Preview'
            );
        });
    }

    // ================================================================
    // Theme Picker
    // ================================================================

    /**
     * Wire up the theme picker cards to persist the selection.
     */
    function initThemePicker() {
        $(document).on('click', '.byblos-theme-card', function() {
            var themeKey = $(this).data('theme');
            $('#byblos-selected-theme').val(themeKey);
            $('.byblos-theme-card').removeClass('byblos-theme-active');
            $(this).addClass('byblos-theme-active');

            callExternal('local_byblos_save_page_settings', {
                pageid: pageId,
                layoutkey: 'single',
                themekey: themeKey
            }).then(function() {
                window.location.reload();
                return;
            }).catch(Notification.exception);
        });
    }

    // ================================================================
    // Add Section Buttons
    // ================================================================

    /**
     * Wire up the "Add section" buttons between existing sections.
     */
    function initAddSectionButtons() {
        $(document).on('click', '.byblos-add-section-btn', function() {
            var position = parseInt($(this).data('position'), 10) || 0;
            openTypePicker(position);
        });
    }

    // ================================================================
    // Advisory Assessment Checklist
    // ================================================================

    /** @type {?Array} Cached checklist data after first fetch. */
    var checklistData = null;
    /** @type {boolean} Whether the checklist panel has been populated. */
    var checklistLoaded = false;

    /**
     * HTML-escape helper for checklist rendering.
     * @param {string} s
     * @returns {string}
     */
    function checklistEsc(s) {
        return escHtml(String((s === null || s === undefined) ? '' : s));
    }

    /**
     * Format a unix timestamp as a short local date string for the "Due ..." hint.
     * Returns empty string for 0.
     * @param {number} ts
     * @returns {string}
     */
    function formatDue(ts) {
        if (!ts) {
            return '';
        }
        var d = new Date(ts * 1000);
        // Use locale date + short time, kept short to fit the sidebar.
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
    }

    /**
     * Build the HTML for a single assignment's checklist items.
     * @param {Object} a Assignment record { assignmentid, name, coursename, duedate, items }.
     * @returns {string} HTML.
     */
    function renderChecklistItems(a) {
        var html = '';
        html += '<div class="byblos-checklist-assignment">';
        html += '<h6 class="mb-1">' + checklistEsc(a.name) + '</h6>';
        var meta = [];
        if (a.coursename) {
            meta.push(checklistEsc(a.coursename));
        }
        if (a.duedate && a.duedate > 0) {
            meta.push('Due ' + checklistEsc(formatDue(a.duedate)));
        }
        if (meta.length) {
            html += '<div class="text-muted small mb-2">' + meta.join(' &middot; ') + '</div>';
        }
        html += '<p class="small text-muted mb-2"><em>Items below are guidance only &mdash; '
              + 'they are not enforced when you submit.</em></p>';
        html += '<ul class="list-unstyled mb-0">';
        (a.items || []).forEach(function(item, idx) {
            var cbid = 'byblos-ck-' + a.assignmentid + '-' + idx;
            html += '<li class="byblos-checklist-item">';
            html += '<input type="checkbox" id="' + cbid + '">';
            html += '<label for="' + cbid + '" class="mb-0">' + checklistEsc(item) + '</label>';
            html += '</li>';
        });
        html += '</ul>';
        html += '</div>';
        return html;
    }

    /**
     * Render the full checklist panel content into #byblos-checklist-content.
     * Handles 0 / 1 / 2+ assignments with the appropriate UI.
     */
    function renderChecklistPanel() {
        var $content = $('#byblos-checklist-content');
        if (!checklistData || checklistData.length === 0) {
            $content.html('<p class="text-muted mb-0">No active assignments with a checklist for this page.</p>');
            return;
        }
        if (checklistData.length === 1) {
            $content.html(renderChecklistItems(checklistData[0]));
            return;
        }
        // Multiple: show a dropdown + the currently selected assignment.
        var html = '';
        html += '<div class="form-group mb-3">';
        html += '<label for="byblos-checklist-select" class="mb-1">Show checklist for:</label>';
        html += '<select class="form-control form-control-sm" id="byblos-checklist-select">';
        checklistData.forEach(function(a, i) {
            html += '<option value="' + i + '">' + checklistEsc(a.name) + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div id="byblos-checklist-selected">' + renderChecklistItems(checklistData[0]) + '</div>';
        $content.html(html);

        $content.off('change.byblosck').on('change.byblosck', '#byblos-checklist-select', function() {
            var idx = parseInt($(this).val(), 10) || 0;
            $('#byblos-checklist-selected').html(renderChecklistItems(checklistData[idx]));
        });
    }

    /**
     * Show the checklist panel and auto-expand its collapsible body.
     */
    function revealChecklistPanel() {
        var $panel = $('#byblos-checklist-panel');
        $panel.removeAttr('hidden');
        // Expand the Bootstrap collapse if it isn't already open.
        var $body = $('#byblos-checklist-body');
        if (!$body.hasClass('show')) {
            $body.addClass('show');
            $panel.find('.card-header').attr('aria-expanded', 'true');
        }
    }

    /**
     * Click-to-edit on the page title. Swaps the h2 for an input; Enter/blur
     * saves via local_byblos_save_page_settings, Escape cancels. Restores the
     * heading with the new text on success.
     */
    function initTitleEditor() {
        var $h2 = $('#byblos-page-title');
        if (!$h2.length) {
            return;
        }

        /**
         * Swap the heading for a text input and wire up save/cancel handlers.
         */
        function enterEdit() {
            if ($h2.find('input').length) {
                return; // Already editing.
            }
            var current = ($h2.text() || '').trim();
            var $input = $('<input type="text" class="form-control form-control-sm byblos-title-input" maxlength="255">');
            $input.val(current);
            $h2.empty().append($input);
            $input.trigger('focus').trigger('select');

            var cancelled = false;

            /**
             * Commit or discard the edit. No-ops on second call.
             * @param {boolean} save True to persist; false to revert.
             */
            function finish(save) {
                if (cancelled) {
                    return;
                }
                cancelled = true;
                var next = ($input.val() || '').trim();
                if (!save || next === '' || next === current) {
                    $h2.text(current);
                    return;
                }
                callExternal('local_byblos_save_page_settings', {
                    pageid: pageId,
                    title: next
                }).then(function() {
                    $h2.text(next);
                    document.title = next;
                    return;
                }).catch(function(err) {
                    $h2.text(current);
                    Notification.exception(err);
                });
            }

            $input.on('keydown', function(ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    finish(true);
                } else if (ev.key === 'Escape') {
                    ev.preventDefault();
                    finish(false);
                }
            });
            $input.on('blur', function() {
                finish(true);
            });
        }

        $h2.on('click', enterEdit);
        $h2.on('keydown', function(ev) {
            // Only respond when the focus is on the heading itself. Without this
            // guard, the bubble from a keydown inside the inline input would
            // hit preventDefault — eating spaces before the user can type them.
            if (ev.target !== this) {
                return;
            }
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                enterEdit();
            }
        });
    }

    /**
     * Wire up the "Checklist" toggle button in the editor header.
     * First click fetches checklists via web service and renders them;
     * subsequent clicks toggle the panel's visibility.
     */
    function initChecklist() {
        $(document).on('click', '#byblos-checklist-toggle', function(ev) {
            ev.preventDefault();

            if (!checklistLoaded) {
                callExternal('local_byblos_get_assignment_checklists', {
                    pageid: pageId
                }).then(function(rows) {
                    checklistData = rows || [];
                    checklistLoaded = true;
                    renderChecklistPanel();
                    revealChecklistPanel();
                    return;
                }).catch(Notification.exception);
                return;
            }

            // Subsequent clicks: toggle visibility.
            var $panel = $('#byblos-checklist-panel');
            if ($panel.attr('hidden')) {
                revealChecklistPanel();
            } else {
                $panel.attr('hidden', 'hidden');
            }
        });
    }

    // ================================================================
    // Collection control (header dropdown)
    // ================================================================

    /** @type {?Array} Last-fetched collection list for this page. */
    var collectionsCache = null;
    /** @type {?Array} Groups the current user belongs to, for new-group-collection picker. */
    var userGroupsCache = null;
    /** @type {boolean} Whether the dropdown has been populated this session. */
    var collectionsLoaded = false;

    /**
     * Update the dropdown button label with the current primary collection title
     * (or fall back to the "no collection" string).
     */
    function updateCollectionButtonLabel() {
        var $label = $('#byblos-collection-control .byblos-collection-current-label');
        if (!collectionsCache) {
            return;
        }
        var primary = collectionsCache.find(function(c) {
            return c.is_primary;
        });
        if (primary) {
            $label.text(primary.title || '');
        } else {
            $label.text('No collection');
        }
    }

    /**
     * Build a single row in the collections dropdown.
     * @param {Object} c Collection descriptor: {id, title, contains_page, is_primary}.
     * @returns {jQuery}
     */
    function buildCollectionRow(c) {
        var id = parseInt(c.id, 10);
        var contains = !!c.contains_page;
        var primary = !!c.is_primary;
        var groupLabel = '';
        if (c.is_group) {
            groupLabel = ' <span class="badge badge-info byblos-coll-group-badge" title="'
                + escHtml(c.group_name || '') + '">'
                + '<i class="fa fa-users"></i> ' + escHtml(c.group_name || 'Group')
                + '</span>';
        }
        var $row = $(
            '<div class="byblos-collection-row d-flex align-items-center mb-1" data-cid="' + id + '">' +
            '<div class="form-check mb-0 flex-fill">' +
            '<input type="checkbox" class="form-check-input byblos-coll-member" id="bcc_m_' + id + '"' +
            (contains ? ' checked' : '') + '>' +
            '<label class="form-check-label small" for="bcc_m_' + id + '">' +
            escHtml(c.title || '') + groupLabel + '</label></div>' +
            '<div class="form-check form-check-inline mb-0" title="Set as primary">' +
            '<input type="radio" class="form-check-input byblos-coll-primary" name="byblos-coll-primary" ' +
            'id="bcc_p_' + id + '" value="' + id + '"' + (primary ? ' checked' : '') +
            (contains ? '' : ' disabled') + '>' +
            '<label class="form-check-label small" for="bcc_p_' + id + '">Primary</label></div>' +
            '</div>'
        );
        return $row;
    }

    /**
     * Render the dropdown body from the current collectionsCache.
     */
    function renderCollectionDropdown() {
        var $menu = $('#byblos-collection-control .byblos-collection-dropdown');
        $menu.empty();
        if (!collectionsCache || collectionsCache.length === 0) {
            $menu.append('<p class="text-muted small mb-2">You have no collections yet.</p>');
        } else {
            collectionsCache.forEach(function(c) {
                $menu.append(buildCollectionRow(c));
            });
        }

        $menu.append('<hr class="my-2">');
        var groupOptions = '<option value="0">Personal (just me)</option>';
        (userGroupsCache || []).forEach(function(g) {
            var label = escHtml(g.name);
            if (g.coursecode) {
                label += ' — ' + escHtml(g.coursecode);
            }
            groupOptions += '<option value="' + parseInt(g.id, 10) + '">' + label + '</option>';
        });
        $menu.append(
            '<div class="form-group mb-0">' +
            '<label class="small mb-1" for="bcc_newtitle">Create new collection</label>' +
            '<input type="text" class="form-control form-control-sm mb-1" id="bcc_newtitle" ' +
            'placeholder="Collection title">' +
            '<select class="form-control form-control-sm mb-1" id="bcc_newgroup">' +
            groupOptions + '</select>' +
            '<button type="button" class="btn btn-primary btn-sm" id="bcc_create">' +
            'Create collection</button>' +
            '</div>'
        );

        updateCollectionButtonLabel();
    }

    /**
     * Apply a server response that includes `primary_collectionid` to the
     * local cache, so UI stays in sync without a full refetch.
     *
     * @param {?number} primaryId
     */
    function applyPrimaryFromServer(primaryId) {
        if (!collectionsCache) {
            return;
        }
        var pid = parseInt(primaryId, 10) || 0;
        collectionsCache.forEach(function(c) {
            c.is_primary = (parseInt(c.id, 10) === pid);
        });
        // Reflect in the DOM.
        $('#byblos-collection-control .byblos-coll-primary').each(function() {
            var cid = parseInt($(this).val(), 10);
            $(this).prop('checked', cid === pid);
        });
        updateCollectionButtonLabel();
    }

    /**
     * Wire event handlers for rows and the create-new inline form.
     */
    function bindCollectionDropdownHandlers() {
        var $menu = $('#byblos-collection-control .byblos-collection-dropdown');

        // Keep the dropdown open when clicking inside it (Bootstrap closes on
        // any link or form-control click by default with some themes).
        $menu.off('click.bccstop').on('click.bccstop', function(ev) {
            ev.stopPropagation();
        });

        // Checkbox — add or remove the page.
        $menu.off('change.bccmember').on('change.bccmember', '.byblos-coll-member', function() {
            var $row = $(this).closest('.byblos-collection-row');
            var cid = parseInt($row.data('cid'), 10);
            var checked = $(this).is(':checked');
            var $primary = $row.find('.byblos-coll-primary');

            if (checked) {
                callExternal('local_byblos_add_page_to_collection', {
                    pageid: pageId,
                    collectionid: cid,
                    setprimary: false
                }).then(function(res) {
                    $primary.prop('disabled', false);
                    var c = collectionsCache.find(function(x) {
                        return parseInt(x.id, 10) === cid;
                    });
                    if (c) {
                        c.contains_page = true;
                    }
                    applyPrimaryFromServer(res && res.primary_collectionid);
                    return res;
                }).catch(function(err) {
                    $(this).prop('checked', false);
                    Notification.exception(err);
                }.bind(this));
            } else {
                callExternal('local_byblos_remove_page_from_collection', {
                    pageid: pageId,
                    collectionid: cid
                }).then(function(res) {
                    $primary.prop('disabled', true).prop('checked', false);
                    var c = collectionsCache.find(function(x) {
                        return parseInt(x.id, 10) === cid;
                    });
                    if (c) {
                        c.contains_page = false;
                        c.is_primary = false;
                    }
                    applyPrimaryFromServer(res && res.primary_collectionid);
                    return res;
                }).catch(function(err) {
                    $(this).prop('checked', true);
                    Notification.exception(err);
                }.bind(this));
            }
        });

        // Primary radio.
        $menu.off('change.bccprimary').on('change.bccprimary', '.byblos-coll-primary', function() {
            var cid = parseInt($(this).val(), 10);
            callExternal('local_byblos_set_primary_collection', {
                pageid: pageId,
                collectionid: cid
            }).then(function(res) {
                applyPrimaryFromServer(res && res.primary_collectionid);
                return res;
            }).catch(Notification.exception);
        });

        // Create new collection.
        $menu.off('click.bcccreate').on('click.bcccreate', '#bcc_create', function() {
            var $input = $menu.find('#bcc_newtitle');
            var title = ($input.val() || '').trim();
            if (!title) {
                $input.trigger('focus');
                return;
            }
            var groupId = parseInt($menu.find('#bcc_newgroup').val(), 10) || 0;
            var groupName = '';
            if (groupId > 0) {
                (userGroupsCache || []).some(function(g) {
                    if (parseInt(g.id, 10) === groupId) {
                        groupName = g.name;
                        return true;
                    }
                    return false;
                });
            }
            callExternal('local_byblos_create_collection', {
                title: title,
                description: '',
                addpageid: pageId,
                groupid: groupId
            }).then(function(res) {
                var newId = parseInt(res && res.collectionid, 10);
                if (!newId) {
                    return res;
                }
                collectionsCache.unshift({
                    id: newId,
                    title: title,
                    description: '',
                    pagecount: 1,
                    contains_page: true,
                    is_primary: (parseInt(res.primary_collectionid, 10) === newId),
                    is_group: groupId > 0,
                    is_creator: true,
                    group_name: groupName
                });
                renderCollectionDropdown();
                bindCollectionDropdownHandlers();
                applyPrimaryFromServer(res.primary_collectionid);
                return res;
            }).catch(Notification.exception);
        });
    }

    /**
     * Initialise the collection control in the editor header. Lazily fetches
     * the user's collections with `contains_page` / `is_primary` flags the
     * first time the dropdown opens.
     */
    function initCollectionControl() {
        var $toggle = $('#byblos-collection-toggle');
        if (!$toggle.length) {
            return;
        }

        $toggle.on('click', function() {
            if (collectionsLoaded) {
                return;
            }
            collectionsLoaded = true;

            var $menu = $('#byblos-collection-control .byblos-collection-dropdown');
            $menu.html('<p class="text-muted small mb-0">Loading collections...</p>');

            $.when(
                callExternal('local_byblos_list_user_collections', {withpageid: pageId}),
                callExternal('local_byblos_list_user_groups', {})
            ).then(function(rows, groups) {
                collectionsCache = (rows || []).map(function(c) {
                    return {
                        id: parseInt(c.id, 10),
                        title: c.title || '',
                        description: c.description || '',
                        pagecount: parseInt(c.pagecount, 10) || 0,
                        contains_page: !!c.contains_page,
                        is_primary: !!c.is_primary,
                        is_group: !!c.is_group,
                        is_creator: !!c.is_creator,
                        group_name: c.group_name || ''
                    };
                });
                userGroupsCache = (groups || []).map(function(g) {
                    return {
                        id: parseInt(g.id, 10),
                        name: g.name || '',
                        courseid: parseInt(g.courseid, 10) || 0,
                        coursecode: g.coursecode || ''
                    };
                });
                renderCollectionDropdown();
                bindCollectionDropdownHandlers();
                return rows;
            }).catch(function(err) {
                collectionsLoaded = false;
                $menu.html('<p class="text-danger small mb-0">Failed to load collections.</p>');
                Notification.exception(err);
            });
        });
    }

    // ================================================================
    // Init
    // ================================================================

    return {
        /**
         * Initialise the section editor.
         *
         * @param {number} _pageId - Portfolio page ID.
         */
        init: function(_pageId) {
            pageId = _pageId;
            $root = $('#byblos-editor');

            if (!$root.length) {
                return;
            }

            // Bind actions on existing section cards.
            $root.find('.byblos-section-card').each(function() {
                bindSectionActions($(this));
            });

            // Init sub-systems.
            initTypePicker();
            initAddSectionButtons();
            initPreviewToggle();
            initThemePicker();
            initChecklist();
            initTitleEditor();
            initCollectionControl();

            // Init contenteditable on text sections.
            InlineEditor.initAll($root);
        }
    };
});
