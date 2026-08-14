@php
    $paperSize    = $template?->paper_size ?? 'A4';
    $orientation  = $template?->orientation ?? 'portrait';
    $templateName = $template?->name ?? '';
    $editMode     = $template !== null;
    $saveUrl      = $editMode
        ? route('result-templates.update', $template)
        : route('result-templates.store');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $editMode ? 'Edit' : 'Create' }} Result Template</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/grapesjs/0.21.6/css/grapes.min.css">
    <link rel="stylesheet" href="{{ asset('css/result-builder.css') }}">
</head>
<body class="rb-page">
    <header class="rb-topbar">
        <div class="rb-topbar-left">
            <a class="rb-back" href="{{ route('result-templates.index') }}">&larr; Templates</a>
            <input id="template-name" type="text" value="{{ $templateName }}" placeholder="Template name" class="rb-input">
        </div>
        <div class="rb-topbar-right">
            <label>Paper
                <select id="paper-size" class="rb-input">
                    @foreach ($paperSizes as $value => $label)
                        <option value="{{ $value }}" @selected($paperSize === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Orientation
                <select id="orientation" class="rb-input">
                    @foreach ($orientations as $value => $label)
                        <option value="{{ $value }}" @selected($orientation === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button id="btn-preview" type="button" class="rb-btn rb-btn-ghost">Preview</button>
            <button id="btn-save" type="button" class="rb-btn rb-btn-primary">Save Template</button>
            <span id="save-status" class="rb-status" role="status"></span>
        </div>
    </header>

    <div id="gjs"></div>

    {{-- Boot data: rendered by Blade before the @verbatim editor script. --}}
    <script>
        window.RB_BOOT = {
            variables: {!! json_encode($availableVariables) !!},
            projectData: {!! $template?->grapes_json ?: 'null' !!},
            compiledCss: {!! $template ? json_encode($template->compiled_css ?? '') : 'null' !!},
            paperSize: '{{ $paperSize }}',
            orientation: '{{ $orientation }}',
            saveUrl: '{{ $saveUrl }}',
            editMode: {{ $editMode ? 'true' : 'false' }},
            csrfToken: '{{ csrf_token() }}'
        };
    </script>

    @verbatim
    <script>
    // ============================================================
    //  STRIDE Result Builder Flexibility Add-on
    //  Keeps official blocks protected; exposes appearance controls.
    // ============================================================
    function installResultBuilderFlexibility(editor, options) {
        var variables = options && options.variables ? options.variables : {};

        var ALLOWED_STYLE_PROPS = [
            'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration',
            'color', 'background-color', 'text-align', 'line-height', 'letter-spacing',
            'width', 'height', 'min-height', 'max-width', 'padding', 'padding-top',
            'padding-right', 'padding-bottom', 'padding-left', 'margin', 'margin-top',
            'margin-right', 'margin-bottom', 'margin-left', 'border', 'border-top',
            'border-right', 'border-bottom', 'border-left', 'border-color', 'border-width',
            'border-style', 'border-radius', 'display', 'vertical-align'
        ];

        var TABLE_STYLE_PROPS = ALLOWED_STYLE_PROPS.concat([
            'border-collapse', 'table-layout'
        ]);

        function baseProtectedDefaults() {
            return {
                editable: false,
                droppable: false,
                stylable: ALLOWED_STYLE_PROPS,
                resizable: true,
                copyable: true,
                removable: true,
                draggable: true,
                toolbar: [
                    { label: '✥', command: 'tlb-move' },
                    { label: '⧉', command: 'tlb-clone' },
                    { label: '🗑', command: 'tlb-delete' }
                ]
            };
        }

        function htmlHasResultVariable(html) {
            return /\{\{[A-Z_a-z0-9]+\}\}/.test(html || '');
        }

        function isOfficialResultComponent(cmp) {
            if (!cmp) return false;
            var attrs = cmp.getAttributes ? cmp.getAttributes() : {};
            var cls = (attrs.class || '').toString();
            var html = '';
            try { html = cmp.toHTML(); } catch (e) { html = ''; }

            return !!(
                attrs['data-result-block'] ||
                attrs['data-result-protected'] ||
                attrs['data-result-variable'] ||
                attrs['data-result-type'] ||
                cls.indexOf('result-') !== -1 ||
                htmlHasResultVariable(html)
            );
        }

        function protectTree(cmp) {
            if (!cmp) return;

            if (isOfficialResultComponent(cmp)) {
                var tagName = (cmp.get && cmp.get('tagName')) || '';
                var isTable = ['table', 'thead', 'tbody', 'tr', 'td', 'th'].indexOf(String(tagName).toLowerCase()) !== -1;

                cmp.set({
                    editable: false,
                    droppable: false,
                    stylable: isTable ? TABLE_STYLE_PROPS : ALLOWED_STYLE_PROPS,
                    resizable: true,
                    copyable: true,
                    removable: true,
                    draggable: true
                });

                var attrs = cmp.getAttributes ? cmp.getAttributes() : {};
                if (htmlHasResultVariable(cmp.toHTML && cmp.toHTML()) && !attrs['data-result-protected']) {
                    cmp.addAttributes({ 'data-result-protected': 'true' });
                }
            }

            if (cmp.components) {
                cmp.components().forEach(function (child) { protectTree(child); });
            }
        }

        function variableTraitOptions() {
            var out = [];
            Object.keys(variables).forEach(function (key) {
                out.push({ id: key, name: variables[key] });
            });
            return out;
        }

        // Protected display text: styled/moved/resized/hidden, never rewritten.
        editor.DomComponents.addType('result-protected', {
            isComponent: function (el) {
                return el.getAttribute && (
                    el.getAttribute('data-result-protected') ||
                    el.getAttribute('data-result-variable') ||
                    el.getAttribute('data-result-block')
                );
            },
            model: {
                defaults: Object.assign(baseProtectedDefaults(), {
                    traits: [
                        {
                            type: 'checkbox',
                            name: 'data-hidden-print',
                            label: 'Hide on result',
                            changeProp: 1
                        },
                        {
                            type: 'select',
                            name: 'data-result-variable',
                            label: 'Result variable',
                            options: variableTraitOptions(),
                            changeProp: 1
                        }
                    ]
                }),
                init: function () {
                    this.on('change:attributes:data-hidden-print', this.updateVisibility);
                    this.on('change:attributes:data-result-variable', this.applyVariable);
                },
                updateVisibility: function () {
                    var attrs = this.getAttributes();
                    var hide = attrs['data-hidden-print'];
                    var current = this.getStyle() || {};
                    if (hide === true || hide === 'true') {
                        this.setStyle(Object.assign({}, current, { display: 'none' }));
                    } else if (current.display === 'none') {
                        delete current.display;
                        this.setStyle(current);
                    }
                },
                applyVariable: function () {
                    var v = this.getAttributes()['data-result-variable'];
                    if (!v) return;
                    var children = this.components();
                    var isLeaf = children.length === 0 || (children.length === 1 && children.models[0].get('type') === 'textnode');
                    if (isLeaf) {
                        this.components(v);
                    }
                }
            }
        });

        // Comment/signature/remark line boxes with controlled line count.
        editor.DomComponents.addType('result-line-box', {
            isComponent: function (el) {
                return el.getAttribute && el.getAttribute('data-result-type') === 'line-box';
            },
            model: {
                defaults: Object.assign(baseProtectedDefaults(), {
                    attributes: {
                        'data-result-type': 'line-box',
                        'data-lines': '3',
                        'data-line-style': 'solid',
                        'data-line-thickness': '1',
                        'data-line-spacing': '12'
                    },
                    traits: [
                        { type: 'number', name: 'data-lines', label: 'Number of lines', min: 1, max: 10, changeProp: 1 },
                        { type: 'select', name: 'data-line-style', label: 'Line style', options: [
                            { id: 'solid', name: 'Solid' },
                            { id: 'dotted', name: 'Dotted' },
                            { id: 'dashed', name: 'Dashed' }
                        ], changeProp: 1 },
                        { type: 'number', name: 'data-line-thickness', label: 'Line thickness', min: 1, max: 5, changeProp: 1 },
                        { type: 'number', name: 'data-line-spacing', label: 'Line spacing', min: 6, max: 32, changeProp: 1 }
                    ]
                }),
                init: function () {
                    this.on('change:attributes:data-lines change:attributes:data-line-style change:attributes:data-line-thickness change:attributes:data-line-spacing', this.refreshLines);
                    this.refreshLines();
                },
                refreshLines: function () {
                    var attrs = this.getAttributes();
                    var count = parseInt(attrs['data-lines'] || 3, 10);
                    var style = attrs['data-line-style'] || 'solid';
                    var thickness = parseInt(attrs['data-line-thickness'] || 1, 10);
                    var spacing = parseInt(attrs['data-line-spacing'] || 12, 10);
                    count = Math.max(1, Math.min(10, count || 3));

                    var lines = '';
                    for (var i = 0; i < count; i++) {
                        lines += '<div data-result-protected="true" style="border-bottom:' + thickness + 'px ' + style + ' #333;height:' + spacing + 'px;margin-bottom:4px;"></div>';
                    }
                    this.components(lines);
                }
            }
        });

        // Style Manager: expose only the appearance controls non-developers need.
        editor.StyleManager.getSectors().reset([
            {
                name: 'Size & Position', open: true, buildProps: ['width', 'height', 'min-height', 'max-width', 'display', 'vertical-align']
            },
            {
                name: 'Typography', open: true, buildProps: [
                    'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration',
                    'color', 'text-align', 'line-height', 'letter-spacing'
                ]
            },
            {
                name: 'Spacing', open: false, buildProps: [
                    'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
                    'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left'
                ]
            },
            {
                name: 'Background', open: false, buildProps: ['background-color']
            },
            {
                name: 'Border', open: false, buildProps: ['border', 'border-width', 'border-style', 'border-color', 'border-radius']
            }
        ]);

        editor.on('component:add', function (cmp) {
            setTimeout(function () { protectTree(cmp); }, 0);
        });

        editor.on('load', function () {
            var wrapper = editor.getWrapper();
            if (wrapper) protectTree(wrapper);
        });

        editor.on('component:selected', function (cmp) {
            if (cmp) protectTree(cmp);
        });
    }

    // ============================================================
    //  Editor bootstrap
    // ============================================================
    var RB = window.RB_BOOT;

    var PAPER_SIZES = {
        A4:     { portrait: ['210mm', '297mm'], landscape: ['297mm', '210mm'] },
        A5:     { portrait: ['148mm', '210mm'], landscape: ['210mm', '148mm'] },
        Legal:  { portrait: ['216mm', '356mm'], landscape: ['356mm', '216mm'] },
        Letter: { portrait: ['216mm', '279mm'], landscape: ['279mm', '216mm'] }
    };

    // Default panels are kept (Block Manager left, Style Manager + Traits
    // right). Style Manager sectors are replaced by the add-on below.
    var editor = grapesjs.init({
        container: '#gjs',
        height: '100%',
        storageManager: false,
        fromElement: false,
        selectorManager: { componentFirst: true }
    });

    // ----- Custom toolbar commands used by protected blocks -----
    editor.Commands.add('tlb-move', {
        run: function (ed) {
            var sel = ed.getSelected();
            if (!sel) return;
            var parent = sel.parent();
            if (parent) { ed.select(parent); ed.refresh(); }
        }
    });
    editor.Commands.add('tlb-clone', {
        run: function (ed) {
            var sel = ed.getSelected();
            if (!sel) return;
            var parent = sel.parent();
            var copy = sel.clone();
            if (parent) { parent.append(copy); }
            ed.select(copy);
            ed.refresh();
        }
    });
    editor.Commands.add('tlb-delete', {
        run: function (ed) { ed.runCommand('core:component-delete'); }
    });

    // ----- Blocks -----
    var BLOCKS = [
        {
            id: 'school-header', label: 'School Header',
            content: '<div data-result-block="school-header" data-result-protected="true" style="text-align:center;padding:8px 0;margin-bottom:14px;">' +
                '<h2 data-result-protected="label" data-result-variable="{{school_name}}" style="margin:0 0 4px;font-size:22px;">{{school_name}}</h2>' +
                '<p data-result-protected="label" data-result-variable="{{school_address}}" style="margin:0;font-size:12px;color:#555;">{{school_address}}</p>' +
                '</div>'
        },
        {
            id: 'student-info', label: 'Student Info',
            content: '<div data-result-block="student-info" data-result-protected="true" style="margin-bottom:14px;">' +
                '<table style="width:100%;border-collapse:collapse;">' +
                '<tr><td data-result-protected="label" style="padding:3px 6px;font-weight:bold;width:180px;">Student Name:</td><td data-result-variable="{{student_name}}" data-result-protected="true" style="padding:3px 6px;border-bottom:1px solid #ddd;">{{student_name}}</td></tr>' +
                '<tr><td data-result-protected="label" style="padding:3px 6px;font-weight:bold;">Class:</td><td data-result-variable="{{student_class}}" data-result-protected="true" style="padding:3px 6px;border-bottom:1px solid #ddd;">{{student_class}}</td></tr>' +
                '<tr><td data-result-protected="label" style="padding:3px 6px;font-weight:bold;">Registration No:</td><td data-result-variable="{{student_id}}" data-result-protected="true" style="padding:3px 6px;border-bottom:1px solid #ddd;">{{student_id}}</td></tr>' +
                '</table></div>'
        },
        {
            id: 'scores-table', label: 'Scores Table',
            content: '<div data-result-block="scores-table" data-result-protected="true" style="margin-bottom:14px;">' +
                '<h3 data-result-protected="label" style="margin:0 0 6px;font-size:14px;">Academic Performance</h3>' +
                '<div data-result-variable="{{SCORES_TABLE}}" data-result-protected="true">{{SCORES_TABLE}}</div>' +
                '</div>'
        },
        {
            id: 'attendance-summary', label: 'Attendance Summary',
            content: '<div data-result-block="attendance-summary" data-result-protected="true" style="margin-bottom:14px;">' +
                '<h3 data-result-protected="label" style="margin:0 0 6px;font-size:14px;">Attendance</h3>' +
                '<div data-result-variable="{{ATTENDANCE_SUMMARY}}" data-result-protected="true">{{ATTENDANCE_SUMMARY}}</div>' +
                '</div>'
        },
        {
            id: 'grading-key', label: 'Grading Key',
            content: '<div data-result-block="grading-key" data-result-protected="true" style="margin-bottom:14px;">' +
                '<h3 data-result-protected="label" style="margin:0 0 6px;font-size:14px;">Grading Scale</h3>' +
                '<div data-result-variable="{{GRADING_SCALE}}" data-result-protected="true">{{GRADING_SCALE}}</div>' +
                '</div>'
        },
        {
            id: 'remark', label: 'Teacher Remark',
            content: '<div data-result-block="remark" data-result-protected="true" style="margin-bottom:14px;">' +
                '<p data-result-protected="label" style="margin:0 0 4px;font-size:13px;"><strong>Class Teacher\'s Remark:</strong> <span data-result-variable="{{teacher_remark}}" data-result-protected="true">{{teacher_remark}}</span></p>' +
                '<div data-result-type="line-box" data-result-protected="true" style="width:100%;padding:6px 0;margin-bottom:12px;"></div>' +
                '</div>'
        },
        {
            id: 'signatures', label: 'Signatures',
            content: '<div data-result-block="signatures" data-result-protected="true" style="margin-top:20px;">' +
                '<table style="width:100%;border-collapse:collapse;">' +
                '<tr>' +
                '<td data-result-protected="true" style="width:50%;padding:4px 10px;text-align:center;">' +
                '<div data-result-type="line-box" data-lines="1" data-result-protected="true" style="width:80%;padding:14px 0;margin:0 auto;"></div>' +
                '<p data-result-variable="{{head_teacher_name}}" data-result-protected="true" style="margin:0;font-size:12px;">{{head_teacher_name}}</p>' +
                '<p data-result-protected="label" style="margin:0;font-size:11px;color:#777;">Head Teacher</p></td>' +
                '<td data-result-protected="true" style="width:50%;padding:4px 10px;text-align:center;">' +
                '<div data-result-type="line-box" data-lines="1" data-result-protected="true" style="width:80%;padding:14px 0;margin:0 auto;"></div>' +
                '<p data-result-variable="{{principal_name}}" data-result-protected="true" style="margin:0;font-size:12px;">{{principal_name}}</p>' +
                '<p data-result-protected="label" style="margin:0;font-size:11px;color:#777;">Principal</p></td>' +
                '</tr></table></div>'
        },
        {
            id: 'result-line-box', label: 'Line Box',
            content: '<div data-result-type="line-box" data-result-protected="true" style="width:100%;padding:6px 0;margin-bottom:12px;"></div>'
        },
        {
            id: 'result-variable', label: 'Result Variable',
            content: '<span data-result-protected="true" data-result-variable="{{school_name}}" style="display:inline-block;">{{school_name}}</span>'
        }
    ];

    BLOCKS.forEach(function (b) {
        editor.BlockManager.add(b.id, {
            label: b.label,
            category: 'Result Blocks',
            content: b.content
        });
    });

    // ----- Canvas / paper styling -----
    function currentPaper() {
        var size = document.getElementById('paper-size').value;
        var orient = document.getElementById('orientation').value;
        return PAPER_SIZES[size] ? PAPER_SIZES[size][orient] : PAPER_SIZES.A4.portrait;
    }

    function canvasCss() {
        var p = currentPaper();
        return 'body { background:#e7e9ee; margin:0; padding:0; }' +
            'body > [data-result-block], body > [data-result-type] { max-width:' + p[0] + '; min-height:' + p[1] + '; margin:24px auto; padding:14mm 12mm;' +
            ' background:#fff; box-shadow:0 3px 10px rgba(0,0,0,.15); box-sizing:border-box; }' +
            'table { width:100%; border-collapse:collapse; }' +
            (RB.compiledCss || '');
    }

    function applyCanvasCss() {
        editor.setStyle(canvasCss());
    }

    // ----- Load existing template -----
    if (RB.projectData) {
        try {
            editor.setProjectData(JSON.parse(RB.projectData));
        } catch (e) {
            console.warn('Invalid grapes_json, starting blank.', e);
        }
    }
    applyCanvasCss();

    // ----- Wire the add-on -----
    installResultBuilderFlexibility(editor, { variables: RB.variables });

    // ----- Paper controls -----
    ['paper-size', 'orientation'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', applyCanvasCss);
    });

    // ----- Preview (hides side panels; canvas expands) -----
    var previewing = false;
    document.getElementById('btn-preview').addEventListener('click', function () {
        previewing = !previewing;
        document.body.classList.toggle('rb-preview', previewing);
        this.textContent = previewing ? 'Exit Preview' : 'Preview';
        editor.refresh();
    });

    // ----- Save via API -----
    function setStatus(msg, kind) {
        var el = document.getElementById('save-status');
        el.textContent = msg;
        el.className = 'rb-status ' + (kind || '');
        clearTimeout(el._t);
        if (kind === 'ok' || kind === 'err') {
            el._t = setTimeout(function () { el.textContent = ''; }, 4000);
        }
    }

    document.getElementById('btn-save').addEventListener('click', function () {
        var name = document.getElementById('template-name').value.trim();
        if (!name) {
            setStatus('Enter a template name first.', 'err');
            document.getElementById('template-name').focus();
            return;
        }

        var payload = {
            name: name,
            paper_size: document.getElementById('paper-size').value,
            orientation: document.getElementById('orientation').value,
            grapes_json: JSON.stringify(editor.getProjectData()),
            compiled_html: editor.getHtml(),
            compiled_css: editor.getCss()
        };

        setStatus('Saving…', '');

        fetch(RB.saveUrl, {
            method: RB.editMode ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': RB.csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            setStatus('Saved ' + new Date().toLocaleTimeString(), 'ok');
            if (!RB.editMode && data.template && data.template.id) {
                RB.editMode = true;
                RB.saveUrl = RB.saveUrl.replace(/\/result-templates$/, '/result-templates/' + data.template.id);
            }
        })
        .catch(function (err) {
            setStatus('Save failed: ' + err.message, 'err');
            console.error(err);
        });
    });

    // Keyboard: Ctrl/Cmd+S saves.
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            document.getElementById('btn-save').click();
        }
    });
    </script>
    @endverbatim
</body>
</html>
