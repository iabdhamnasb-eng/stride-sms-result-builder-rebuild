// ============================================================
//  RESULT BUILDER ADD-ON — Line split & format · Move / align · Grid layout
//  ------------------------------------------------------------------
//  Fully additive. Registers its own traits, event listeners and the
//  render mirror; it does NOT touch any existing code on the page.
//  Place this file's content inside a <script> tag located WITHIN the
//  page's @verbatim region (the file contains {{variable}} strings that
//  Blade must not process). See deploy/README.md.
// ============================================================
(function () {
    'use strict';

    var editor = window.editor;
    if (!editor) return;

var RB_LINE_FONTS = ['Arial', 'Helvetica', 'Verdana', 'Tahoma', 'Trebuchet MS',
        'Georgia', 'Times New Roman', 'Courier New', 'Lucida Console'];

    function rbClampInt(value, min, max) {
        var n = parseInt(value, 10);
        if (isNaN(n)) return min;
        return Math.max(min, Math.min(max, n));
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function rbFontOptionsHtml() {
        var out = '<option value="">Default</option>';
        RB_LINE_FONTS.forEach(function (f) {
            out += '<option value="' + escapeHtml(f) + '">' + escapeHtml(f) + '</option>';
        });
        return out;
    }

    function rbLineRowsHtml(count) {
        var rows = '';
        for (var i = 1; i <= count; i++) {
            rows += '<div class="rb-lines-row" data-line="' + i + '">'
                + '<div class="rb-lines-row-head">Line ' + i + '</div>'
                + '<div class="rb-lines-grid">'
                + '<label class="rb-lines-field rb-lines-wide">Font<select class="rb-line-font">' + rbFontOptionsHtml() + '</select></label>'
                + '<label class="rb-lines-field">Max words (0 = auto)<input type="number" class="rb-line-words" min="0" max="30" step="1" value="0"></label>'
                + '<label class="rb-lines-field">Size (px)<input type="number" class="rb-line-size" min="6" max="72" step="1"></label>'
                + '<label class="rb-lines-field">Color<input type="color" class="rb-line-color" value="#000000"></label>'
                + '<label class="rb-lines-field">Weight<select class="rb-line-weight">'
                + '<option value="">Default</option><option value="400">Normal</option>'
                + '<option value="600">Semibold</option><option value="700">Bold</option></select></label>'
                + '<label class="rb-lines-field">Align<select class="rb-line-align">'
                + '<option value="">Default</option><option value="left">Left</option>'
                + '<option value="center">Center</option><option value="right">Right</option>'
                + '<option value="justify">Justify</option></select></label>'
                + '</div></div>';
        }
        return rows;
    }

editor.Traits.addType('rb-lines', {
        createInput: function () {
            var view = this;
            var target = view.target || (view.model && view.model.get('target'));
            var container = document.createElement('div');
            container.className = 'rb-lines-trait';
            container.innerHTML =
                '<div class="rb-lines-top">'
                + '<label class="rb-lines-field">Lines'
                + '<input type="number" class="rb-lines-count" min="1" max="10" step="1" value="1"></label>'
                + '</div>'
                + '<div class="rb-lines-list"></div>';

            var list = container.querySelector('.rb-lines-list');
            var countInput = container.querySelector('.rb-lines-count');

            function syncRowsFromAttrs() {
                if (!target || !target.getAttributes) return;
                var attrs = target.getAttributes() || {};
                var rows = list.querySelectorAll('.rb-lines-row');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var n = parseInt(row.getAttribute('data-line'), 10);
                    row.querySelector('.rb-line-font').value = attrs['data-line-' + n + '-font'] || '';
                    row.querySelector('.rb-line-words').value = attrs['data-line-' + n + '-words'] || '0';
                    row.querySelector('.rb-line-size').value = attrs['data-line-' + n + '-size'] || '';
                    row.querySelector('.rb-line-color').value = attrs['data-line-' + n + '-color'] || '#000000';
                    row.querySelector('.rb-line-weight').value = attrs['data-line-' + n + '-weight'] || '';
                    row.querySelector('.rb-line-align').value = attrs['data-line-' + n + '-align'] || '';
                }
            }

            function syncFromTarget() {
                if (!target || !target.getAttributes) return;
                var attrs = target.getAttributes() || {};
                var count = rbClampInt(attrs['data-lines'], 1, 10);
                countInput.value = count;
                if (list.childElementCount !== count) {
                    list.innerHTML = rbLineRowsHtml(count);
                }
                syncRowsFromAttrs();
            }

            function writeToTarget() {
                if (!target || !target.getAttributes) return;
                var attrs = target.getAttributes() || {};
                var count = rbClampInt(countInput.value, 1, 10);
                var next = {
                    'data-lines': String(count)
                };
                var keep = {};
                var rows = list.querySelectorAll('.rb-lines-row');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var n = parseInt(row.getAttribute('data-line'), 10);
                    if (n > count) continue;
                    keep['data-line-' + n + '-font'] = true;
                    keep['data-line-' + n + '-words'] = true;
                    keep['data-line-' + n + '-size'] = true;
                    keep['data-line-' + n + '-color'] = true;
                    keep['data-line-' + n + '-weight'] = true;
                    keep['data-line-' + n + '-align'] = true;
                    var font = row.querySelector('.rb-line-font').value;
                    var words = row.querySelector('.rb-line-words').value;
                    var size = row.querySelector('.rb-line-size').value;
                    var color = row.querySelector('.rb-line-color').value;
                    var weight = row.querySelector('.rb-line-weight').value;
                    var align = row.querySelector('.rb-line-align').value;
                    if (font !== '') next['data-line-' + n + '-font'] = font;
                    if (words !== '' && words !== '0') next['data-line-' + n + '-words'] = words;
                    if (size !== '') next['data-line-' + n + '-size'] = size;
                    if (color !== '') next['data-line-' + n + '-color'] = color;
                    if (weight !== '') next['data-line-' + n + '-weight'] = weight;
                    if (align !== '') next['data-line-' + n + '-align'] = align;
                }
                Object.keys(attrs).forEach(function (key) {
                    if (key.indexOf('data-line-') === 0 && key !== 'data-lines' && !keep[key]) {
                        next[key] = '';
                    }
                });
                Object.keys(next).forEach(function (key) {
                    var patch = {};
                    patch[key] = next[key];
                    if (patch[key] === '') {
                        delete patch[key];
                        if (target.removeAttributes) target.removeAttributes([key]);
                    } else if (target.addAttributes) {
                        target.addAttributes(patch);
                    }
                });
                if (target.removeAttributes && attrs['data-words-per-line'] !== undefined) {
                    target.removeAttributes(['data-words-per-line']);
                }
            }

            function onAnyChange(ev) {
                if (ev.target.classList.contains('rb-lines-count')) {
                    var count = rbClampInt(countInput.value, 1, 10);
                    if (list.childElementCount !== count) {
                        list.innerHTML = rbLineRowsHtml(count);
                        syncRowsFromAttrs();
                    }
                }
                writeToTarget();
            }

            container.addEventListener('input', onAnyChange);
            container.addEventListener('change', onAnyChange);

            view._syncFromTarget = syncFromTarget;
            syncFromTarget();
            return container;
        },
        onUpdate: function () {
            if (this._syncFromTarget) this._syncFromTarget();
        }
    });

editor.Traits.addType('rb-position', {
        createInput: function () {
            var view = this;
            var target = view.target || (view.model && view.model.get('target'));
            var container = document.createElement('div');
            container.className = 'rb-position-trait';
            container.innerHTML =
                '<div class="rb-position-row">'
                + '<button type="button" class="rb-pos-btn" data-align="left">Left</button>'
                + '<button type="button" class="rb-pos-btn" data-align="center">Center</button>'
                + '<button type="button" class="rb-pos-btn" data-align="right">Right</button>'
                + '<button type="button" class="rb-pos-btn" data-align="full">Full width</button>'
                + '</div>';

            container.addEventListener('click', function (ev) {
                var btn = ev.target && ev.target.closest ? ev.target.closest('[data-align]') : null;
                if (!btn || !target || !target.get || !target.getStyle) return;
                var align = btn.getAttribute('data-align');
                var tag = String(target.get('tagName') || '').toLowerCase();
                var style = Object.assign({}, target.getStyle() || {});
                if (tag === 'td' || tag === 'th') {
                    style['text-align'] = align === 'full' ? 'left' : align;
                } else {
                    style['display'] = 'block';
                    if (align === 'left') { style['margin-left'] = '0'; style['margin-right'] = 'auto'; }
                    if (align === 'center') { style['margin-left'] = 'auto'; style['margin-right'] = 'auto'; }
                    if (align === 'right') { style['margin-left'] = 'auto'; style['margin-right'] = '0'; }
                    if (align === 'full') { style['margin-left'] = '0'; style['margin-right'] = '0'; }
                }
                target.setStyle(style);
            });

            return container;
        }
    });

editor.Traits.addType('rb-grid', {
        createInput: function () {
            var view = this;
            var target = view.target || (view.model && view.model.get('target'));
            var container = document.createElement('div');
            container.className = 'rb-grid-trait';
            container.innerHTML =
                '<div class="rb-grid-top">'
                + '<label>Rows<input type="number" class="rb-grid-rows-input" min="1" max="10" step="1" value="1"></label>'
                + '<span class="rb-grid-count"></span>'
                + '</div>'
                + '<div class="rb-grid-list"></div>';

            var list = container.querySelector('.rb-grid-list');
            var rowsInput = container.querySelector('.rb-grid-rows-input');
            var countEl = container.querySelector('.rb-grid-count');

            function walkVariables(cmp, cb) {
                if (!cmp || !cmp.components) return;
                var key = elementVariableKey(cmp);
                if (key) cb(cmp, key);
                cmp.components().forEach(function (ch) { walkVariables(ch, cb); });
            }

            function distribute(count, groups) {
                var out = [];
                var remaining = count;
                for (var i = 0; i < groups; i++) {
                    var left = groups - i;
                    var take = left === 1 ? remaining : Math.ceil(remaining / left);
                    out.push(take);
                    remaining -= take;
                }
                return out;
            }

            function gridState() {
                var vars = [];
                walkVariables(target, function (cmp, key) { vars.push(key); });
                var attrs = target && target.getAttributes ? target.getAttributes() : {};
                var stored = parseInt(attrs['data-grid-rows'], 10);
                var rows = isNaN(stored) || stored < 1
                    ? Math.max(1, vars.length)
                    : Math.min(10, stored);
                var perRow = distribute(vars.length, rows);
                var cols = [];
                var assignments = [];
                var cursor = 0;
                for (var i = 0; i < rows; i++) {
                    var v = parseInt(attrs['data-grid-row-' + (i + 1) + '-cols'], 10);
                    var c = isNaN(v) || v < 1 ? 1 : Math.min(12, v);
                    cols.push(c);
                    var rowVars = vars.slice(cursor, cursor + perRow[i]);
                    cursor += perRow[i];
                    var perCol = distribute(rowVars.length, c);
                    var cells = [];
                    var c2 = 0;
                    for (var k = 0; k < c; k++) {
                        cells.push(rowVars.slice(c2, c2 + perCol[k]));
                        c2 += perCol[k];
                    }
                    assignments.push(cells);
                }
                return { rows: rows, varsCount: vars.length, cols: cols, assignments: assignments };
            }

            function cellClass(n) {
                return n === 2 ? 'is-col2' : n === 3 ? 'is-col3' : n === 4 ? 'is-col4' : '';
            }

            function buildRows(state) {
                var html = '';
                for (var i = 0; i < state.rows; i++) {
                    var n = i + 1;
                    var c = state.cols[i] || 1;
                    var cells = '';
                    for (var k = 0; k < c; k++) {
                        var labels = (state.assignments[i][k] || []).map(function (key) {
                            return '<span class="rb-grid-cell-label">' + key + '</span>';
                        }).join('');
                        cells += '<div class="rb-grid-cell ' + cellClass(c) + '">' + labels + '</div>';
                    }
                    html += '<div class="rb-grid-row" data-grid-row="' + n + '">'
                        + '<span class="rb-grid-row-label">Row ' + n + '</span>'
                        + '<label>Cols<input type="number" class="rb-grid-cols" min="1" max="12" step="1" value="' + c + '"></label>'
                        + '<div class="rb-grid-diagram" style="grid-template-columns:repeat(' + c + ',1fr);">' + cells + '</div>'
                        + '</div>';
                }
                return html;
            }

            function redraw(force) {
                if (!target || !target.getAttributes) return;
                var state = gridState();
                var snapshot = JSON.stringify([state.rows, state.varsCount, state.cols, state.assignments]);
                if (!force && snapshot === view._gridSnapshot) return;
                view._gridSnapshot = snapshot;
                rowsInput.value = String(state.rows);
                countEl.textContent = 'Variables: ' + state.varsCount;
                if (list.childElementCount !== state.rows || force) {
                    list.innerHTML = buildRows(state);
                } else {
                    var diagrams = list.querySelectorAll('.rb-grid-diagram');
                    for (var i = 0; i < diagrams.length; i++) {
                        var n = i + 1;
                        var c = state.cols[i] || 1;
                        diagrams[i].style.gridTemplateColumns = 'repeat(' + c + ',1fr)';
                        diagrams[i].innerHTML = '';
                        for (var k = 0; k < c; k++) {
                            var labels = (state.assignments[i][k] || []).map(function (key) {
                                return '<span class="rb-grid-cell-label">' + key + '</span>';
                            }).join('');
                            diagrams[i].innerHTML += '<div class="rb-grid-cell ' + cellClass(c) + '">' + labels + '</div>';
                        }
                    }
                    var colsInputs = list.querySelectorAll('.rb-grid-cols');
                    for (var m = 0; m < colsInputs.length; m++) {
                        var vv = state.cols[m] || 1;
                        if (colsInputs[m].value !== String(vv)) colsInputs[m].value = vv;
                    }
                }
                var attrs = target.getAttributes() || {};
                var stale = Object.keys(attrs).filter(function (key) {
                    var m = /^data-grid-row-(\d+)-cols$/.exec(key);
                    return m && parseInt(m[1], 10) > state.rows;
                });
                if (stale.length && target.removeAttributes) target.removeAttributes(stale);
            }

            function writeCols(n, value) {
                if (!target || !target.getAttributes) return;
                var parsed = parseInt(value, 10);
                var val = isNaN(parsed) ? 1 : Math.min(12, Math.max(1, parsed));
                var key = 'data-grid-row-' + n + '-cols';
                var attrs = target.getAttributes() || {};
                if (val > 1) {
                    var patch = {};
                    patch[key] = String(val);
                    if (target.addAttributes) target.addAttributes(patch);
                } else if (attrs[key] !== undefined) {
                    if (target.removeAttributes) target.removeAttributes([key]);
                }
                redraw(true);
            }

            function writeRows(value) {
                if (!target || !target.addAttributes) return;
                var parsed = parseInt(value, 10);
                var val = isNaN(parsed) ? 1 : Math.min(10, Math.max(1, parsed));
                var patch = {};
                patch['data-grid-rows'] = String(val);
                target.addAttributes(patch);
                redraw(true);
            }

            container.addEventListener('change', function (ev) {
                if (ev.target.classList.contains('rb-grid-rows-input')) {
                    writeRows(ev.target.value);
                    return;
                }
                if (!ev.target.classList.contains('rb-grid-cols')) return;
                var row = ev.target.closest('.rb-grid-row');
                if (!row) return;
                writeCols(parseInt(row.getAttribute('data-grid-row'), 10), ev.target.value);
            });

            var em = view.em || (target && target.em);
            if (em) {
                var onChange = function (updated) {
                    if (!updated) return;
                    var c = updated;
                    var inScope = updated === target;
                    while (!inScope && c && c.parent) {
                        c = c.parent();
                        inScope = c === target;
                    }
                    if (inScope) redraw(false);
                };
                var cleanup = function () {
                    em.off('component:update', onChange);
                    em.off('component:add', onChange);
                    em.off('component:remove', onChange);
                    em.off('component:deselected', cleanup);
                };
                em.on('component:update', onChange);
                em.on('component:add', onChange);
                em.on('component:remove', onChange);
                em.on('component:deselected', cleanup);
            }

            view._redraw = redraw;
            redraw(true);
            return container;
        },
        onUpdate: function () {
            if (this._redraw) this._redraw(false);
        }
    });

function elementVariableKey(cmp) {
        if (!cmp || !cmp.getAttributes || !cmp.components) return null;
        var attrs = cmp.getAttributes() || {};
        if (attrs['data-result-variable']) {
            var stored = String(attrs['data-result-variable']).trim();
            if (stored.indexOf('{{') !== 0) stored = '{{' + stored + '}}';
            return stored;
        }
        var children = cmp.components();
        var text = '';
        var hasNested = false;
        children.forEach(function (ch) {
            var t = ch.get('type');
            if (t === 'textnode' || t === 'text') {
                text += ch.get('content') || '';
            } else {
                hasNested = true;
            }
        });
        if (hasNested || !text) return null;
        var trimmed = text.trim();
        var matches = trimmed.match(/\{\{[A-Z_a-z0-9]+\}\}/g) || [];
        if (matches.length !== 1) return null;
        var rest = trimmed.replace(/\{\{[A-Z_a-z0-9]+\}\}/g, '').trim();
        if (rest !== '' && !/^["'\u201C\u201D\u2018\u2019]+$/.test(rest)) return null;
        return matches[0];
    }

    function ensureLineFormatTrait(cmp) {
        if (!cmp || !cmp.get || !cmp.getAttributes) return;
        var key = elementVariableKey(cmp);
        if (!key) return;
        var attrs = cmp.getAttributes() || {};
        if (!attrs['data-result-variable']) {
            cmp.addAttributes({ 'data-result-variable': key });
        }
        var traits = (cmp.get('traits') || []).slice();
        var has = traits.some(function (t) {
            return t && (t.type === 'rb-lines' || t.name === 'rb-lines');
        });
        if (!has) {
            traits.push({ type: 'rb-lines', name: 'rb-lines', label: 'Line split & format' });
            cmp.set('traits', traits);
        }
    }

    function ensureLineFormatTraitsInTree(cmp) {
        if (!cmp) return;
        ensureLineFormatTrait(cmp);
        ensureGridTrait(cmp);
        if (cmp.components) {
            cmp.components().forEach(function (child) { ensureLineFormatTraitsInTree(child); });
        }
    }

    function hasBlockAncestor(cmp) {
        var p = cmp.parent ? cmp.parent() : null;
        while (p) {
            var attrs = (p.getAttributes && p.getAttributes()) || {};
            if (attrs['data-result-block']) return true;
            p = p.parent ? p.parent() : null;
        }
        return false;
    }

    function ensureGridTrait(cmp) {
        if (!cmp || !cmp.get || !cmp.getAttributes || !cmp.components) return;
        var attrs = cmp.getAttributes() || {};
        var isBlock = !!attrs['data-result-block'];
        var isVariable = !!elementVariableKey(cmp);
        if (!isBlock && !isVariable) return;
        if (isVariable && !isBlock && hasBlockAncestor(cmp)) return;
        var traits = (cmp.get('traits') || []).slice();
        var has = traits.some(function (t) {
            return t && (t.type === 'rb-grid' || t.name === 'rb-grid');
        });
        if (!has) {
            traits.push({ type: 'rb-grid', name: 'rb-grid', label: 'Grid layout' });
            cmp.set('traits', traits);
        }
    }

function splitVariableText(text, lines, caps) {
        var words = String(text == null ? '' : text).trim().split(/\s+/).filter(function (w) { return w.length > 0; });
        if (words.length === 0) return [];
        lines = rbClampInt(lines, 1, 10);
        caps = caps || [];
        var out = [];
        var remaining = words.length;
        for (var i = 0; i < lines && remaining > 0; i++) {
            var linesLeft = lines - i;
            var take = Math.ceil(remaining / linesLeft);
            var cap = parseInt(caps[i], 10);
            if (!isNaN(cap) && cap > 0) take = Math.min(take, cap);
            var start = words.length - remaining;
            out.push(words.slice(start, start + take).join(' '));
            remaining -= take;
        }
        if (remaining > 0) {
            out[out.length - 1] += ' ' + words.slice(words.length - remaining).join(' ');
        }
        return out;
    }

    function lineCapsFromAttrs(attrs, lines) {
        var caps = [];
        for (var i = 1; i <= lines; i++) {
            var v = attrs['data-line-' + i + '-words'];
            if (v === undefined || v === '') v = attrs['data-words-per-line'] || 0;
            caps.push(v);
        }
        return caps;
    }

    function formatVariableHtml(text, attrs) {
        var lines = rbClampInt(attrs['data-lines'], 1, 10);
        var caps = lineCapsFromAttrs(attrs, lines);
        var linesArr = splitVariableText(text, lines, caps);
        var html = '';
        linesArr.forEach(function (line, i) {
            var n = i + 1;
            var style = 'display:block;';
            if (attrs['data-line-' + n + '-font']) style += 'font-family:' + attrs['data-line-' + n + '-font'] + ',sans-serif;';
            if (attrs['data-line-' + n + '-size']) style += 'font-size:' + attrs['data-line-' + n + '-size'] + 'px;';
            if (attrs['data-line-' + n + '-color']) style += 'color:' + attrs['data-line-' + n + '-color'] + ';';
            if (attrs['data-line-' + n + '-weight']) style += 'font-weight:' + attrs['data-line-' + n + '-weight'] + ';';
            if (attrs['data-line-' + n + '-align']) style += 'text-align:' + attrs['data-line-' + n + '-align'] + ';';
            html += '<span style="' + style + '">' + escapeHtml(line) + '</span>';
        });
        return html;
    }

    window.resultBuilderRender = {
        splitVariableText: splitVariableText,
        formatVariableHtml: formatVariableHtml,
        escapeHtml: escapeHtml
    };

    // ── Move / align on every official element (blocks, line boxes,
    //    variable elements) — the page's own component types are left
    //    untouched, so the trait is attached per element instead.
    function ensurePositionTrait(cmp) {
        if (!cmp || !cmp.get || !cmp.getAttributes) return;
        var attrs = cmp.getAttributes() || {};
        var isOfficial = !!(
            attrs['data-result-block'] ||
            attrs['data-result-type'] === 'line-box' ||
            attrs['data-result-variable'] ||
            elementVariableKey(cmp)
        );
        if (!isOfficial) return;
        var traits = (cmp.get('traits') || []).slice();
        var has = traits.some(function (t) {
            return t && (t.type === 'rb-position' || t.name === 'rb-position');
        });
        if (!has) {
            traits.push({ type: 'rb-position', name: 'rb-position', label: 'Move / align' });
            cmp.set('traits', traits);
        }
    }

    function ensureAddonTraitsInTree(cmp) {
        if (!cmp) return;
        ensureLineFormatTrait(cmp);
        ensurePositionTrait(cmp);
        ensureGridTrait(cmp);
        if (cmp.components) {
            cmp.components().forEach(function (child) { ensureAddonTraitsInTree(child); });
        }
    }

    // ── Attach traits on add / load / selection (additive listeners) ──
    editor.on('component:add', function (cmp) {
        setTimeout(function () { ensureAddonTraitsInTree(cmp); }, 0);
    });

    editor.on('load', function () {
        var wrapper = editor.getWrapper();
        if (wrapper) ensureAddonTraitsInTree(wrapper);
    });

    editor.on('component:selected', function (cmp) {
        if (cmp) {
            ensureLineFormatTrait(cmp);
            ensurePositionTrait(cmp);
            ensureGridTrait(cmp);
        }
    });

    // If the editor was already initialised (add-on added to a live page),
    // the 'load' event has already fired — walk the tree immediately.
    var wrapper = editor.getWrapper();
    if (wrapper) setTimeout(function () { ensureAddonTraitsInTree(wrapper); }, 0);
})();
