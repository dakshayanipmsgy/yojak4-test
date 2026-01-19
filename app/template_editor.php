<?php
declare(strict_types=1);

function render_template_editor(array $options): void
{
    $template = $options['template'] ?? [];
    $action = $options['action'] ?? '';
    $submitLabel = $options['submitLabel'] ?? 'Save Template';
    $cancelUrl = $options['cancelUrl'] ?? '/contractor/templates.php';
    $isStaff = (bool)($options['isStaff'] ?? false);
    $showAdvanced = (bool)($options['showAdvanced'] ?? false);
    $scope = $options['scope'] ?? ($template['scope'] ?? 'contractor');
    $profileFields = template_library_profile_fields();
    $fieldTypes = template_library_field_types();
    $categories = template_library_categories();
    $templateJson = json_encode($template, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $templateJson = $templateJson !== false ? $templateJson : '{}';
    ?>
    <style>
        .editor-grid { display:grid; grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); gap:16px; }
        .editor-panel { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface-2); display:grid; gap:10px; }
        .field-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:rgba(37, 99, 235, 0.12); border:1px solid rgba(37, 99, 235, 0.3); color:#1d4ed8; font-size:12px; margin:2px 4px; }
        .template-editor { min-height:240px; padding:12px; border:1px solid var(--border); border-radius:12px; background:#fff; color:#111; line-height:1.6; }
        .template-editor:focus { outline:2px solid rgba(37, 99, 235, 0.3); }
        .field-list { display:grid; gap:6px; max-height:320px; overflow:auto; }
        .field-list button { text-align:left; border:1px solid var(--border); background:var(--surface); padding:8px; border-radius:10px; cursor:pointer; color:var(--text); }
        .field-list button:hover { border-color:var(--primary); }
        .preview-area { white-space:pre-wrap; background:#0f172a; color:#f8fafc; padding:12px; border-radius:12px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size:12px; }
        .field-table { display:grid; gap:8px; }
        .field-table .row { display:grid; grid-template-columns: 1.2fr 0.8fr 0.6fr; gap:8px; align-items:center; }
        .field-table .row span { font-size:12px; }
        .advanced-json textarea { min-height:220px; }
        @media (max-width: 900px) {
            .editor-grid { grid-template-columns: 1fr; }
        }
    </style>
    <form method="post" action="<?= sanitize($action); ?>" style="display:grid;gap:16px;">
        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
        <?php if (!empty($template['templateId'])): ?>
            <input type="hidden" name="templateId" value="<?= sanitize($template['templateId']); ?>">
        <?php endif; ?>
        <?php if ($isStaff): ?>
            <input type="hidden" name="scope" value="<?= sanitize($scope); ?>">
        <?php endif; ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                <label style="display:grid;gap:6px;">
                    <span class="muted">Title</span>
                    <input class="input" type="text" name="title" value="<?= sanitize($template['title'] ?? ''); ?>" required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span class="muted">Category</span>
                    <select class="input" name="category">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= sanitize($category); ?>" <?= (($template['category'] ?? '') === $category) ? 'selected' : ''; ?>>
                                <?= sanitize($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="display:grid;gap:6px;">
                    <span class="muted">Status</span>
                    <select class="input" name="status" <?= $isStaff ? '' : 'disabled'; ?>>
                        <?php $status = $template['status'] ?? 'active'; ?>
                        <option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </label>
            </div>
            <label style="display:grid;gap:6px;">
                <span class="muted">Description</span>
                <textarea class="input" name="description" rows="3" placeholder="When should this template be used?"><?= sanitize($template['description'] ?? ''); ?></textarea>
            </label>
        </div>
        <div class="editor-grid">
            <div class="editor-panel">
                <h3 style="margin:0;">Available Fields</h3>
                <p class="muted" style="margin:0;">Click a field to insert a placeholder chip into the body.</p>
                <div class="field-list" id="field-list"></div>
            </div>
            <div class="editor-panel" style="gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div>
                        <h3 style="margin:0;">Template Body</h3>
                        <p class="muted" style="margin:4px 0 0;">Insert fields using the left panel. Manual placeholder typing is discouraged.</p>
                    </div>
                    <button class="btn secondary" type="button" id="toggle-preview">Preview placeholders</button>
                </div>
                <div id="template-editor" class="template-editor" contenteditable="true"></div>
                <input type="hidden" name="body" id="body-input" value="">
                <input type="hidden" name="field_catalog" id="field-catalog" value="">
                <div id="placeholder-preview" class="preview-area" style="display:none;"></div>
            </div>
        </div>
        <div class="card" style="display:grid;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;">Fields used in this template</h3>
                    <p class="muted" style="margin:4px 0 0;">Add custom fields and ensure they appear in the catalog.</p>
                </div>
            </div>
            <div class="field-table" id="field-catalog-list"></div>
            <div style="border-top:1px solid var(--border);padding-top:12px;display:grid;gap:10px;">
                <h4 style="margin:0;">Add Custom Field</h4>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                    <input class="input" type="text" id="custom-label" placeholder="Field label">
                    <select class="input" id="custom-type">
                        <?php foreach ($fieldTypes as $value => $label): ?>
                            <option value="<?= sanitize($value); ?>"><?= sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="input" id="custom-required">
                        <option value="0">Optional</option>
                        <option value="1">Required</option>
                    </select>
                </div>
                <input class="input" type="text" id="custom-guidance" placeholder="Guidance (where to find this info)">
                <button class="btn secondary" type="button" id="add-custom">Add Custom Field</button>
            </div>
        </div>
        <?php if ($showAdvanced): ?>
            <div class="card advanced-json" style="display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;">Advanced JSON (Staff Only)</h3>
                    <p class="muted" style="margin:4px 0 0;">Paste JSON, validate, and apply. Bid/rate fields are blocked.</p>
                </div>
                <textarea class="input" name="advanced_json" id="advanced-json"><?= sanitize($templateJson); ?></textarea>
                <div style="display:flex;gap:8px;">
                    <button class="btn secondary" type="button" id="validate-json">Validate JSON</button>
                    <button class="btn" type="submit" name="apply_json" value="1">Apply JSON</button>
                </div>
                <p id="json-status" class="muted" style="margin:0;"></p>
            </div>
        <?php endif; ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn" type="submit"><?= sanitize($submitLabel); ?></button>
            <a class="btn secondary" href="<?= sanitize($cancelUrl); ?>">Cancel</a>
        </div>
    </form>

    <script>
        const profileFields = <?= json_encode($profileFields, JSON_UNESCAPED_SLASHES); ?>;
        const templateData = <?= json_encode($template, JSON_UNESCAPED_SLASHES); ?>;
        const fieldCatalog = [...(templateData.fieldCatalog || [])];
        const fieldIndex = {};
        profileFields.forEach(field => fieldIndex[field.key] = field);
        fieldCatalog.forEach(field => fieldIndex[field.key] = field);

        const fieldList = document.getElementById('field-list');
        const editor = document.getElementById('template-editor');
        const bodyInput = document.getElementById('body-input');
        const fieldCatalogInput = document.getElementById('field-catalog');
        const fieldCatalogList = document.getElementById('field-catalog-list');
        const preview = document.getElementById('placeholder-preview');
        const previewToggle = document.getElementById('toggle-preview');
        const advancedJson = document.getElementById('advanced-json');
        const jsonStatus = document.getElementById('json-status');

        const slugify = (value) => value.toLowerCase().trim()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');

        const refreshFieldList = () => {
            fieldList.innerHTML = '';
            const allFields = [...profileFields, ...fieldCatalog.filter(field => field.source === 'user_input')];
            allFields.forEach(field => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = `${field.label} (${field.key})`;
                button.addEventListener('click', () => insertChip(field));
                fieldList.appendChild(button);
            });
        };

        const refreshCatalog = () => {
            fieldCatalogList.innerHTML = '';
            if (!fieldCatalog.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'No fields added yet. Insert a field from the left or add a custom field.';
                fieldCatalogList.appendChild(empty);
                return;
            }
            fieldCatalog.forEach(field => {
                const row = document.createElement('div');
                row.className = 'row';
                row.innerHTML = `<span>${field.label}</span><span class="muted">${field.key}</span><span class="muted">${field.required ? 'Required' : 'Optional'}</span>`;
                fieldCatalogList.appendChild(row);
            });
        };

        const insertChip = (field) => {
            const span = document.createElement('span');
            span.className = 'field-chip';
            span.dataset.key = field.key;
            span.textContent = field.label;
            const selection = window.getSelection();
            if (!selection || selection.rangeCount === 0) {
                editor.appendChild(span);
                editor.appendChild(document.createTextNode(' '));
            } else {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                range.insertNode(span);
                range.setStartAfter(span);
                range.insertNode(document.createTextNode(' '));
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            }
            if (!fieldCatalog.find(existing => existing.key === field.key)) {
                if (field.source !== 'contractor_profile') {
                    fieldCatalog.push(field);
                } else {
                    fieldCatalog.push(fieldIndex[field.key] || field);
                }
                refreshCatalog();
            }
            syncInputs();
        };

        const serializeEditor = () => {
            const walker = document.createTreeWalker(editor, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, null);
            let output = '';
            let node = walker.currentNode;
            const appendText = (text) => {
                output += text.replace(/\u00A0/g, ' ');
            };
            while (node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    appendText(node.textContent || '');
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    const element = node;
                    if (element.classList && element.classList.contains('field-chip')) {
                        const key = element.dataset.key || '';
                        output += `{{field:${key}}}`;
                    } else if (element.tagName === 'DIV' || element.tagName === 'P') {
                        output += '\n';
                    } else if (element.tagName === 'BR') {
                        output += '\n';
                    }
                }
                node = walker.nextNode();
            }
            return output.trim();
        };

        const loadBodyIntoEditor = (body) => {
            editor.innerHTML = '';
            const regex = /\{\{\s*field:([^}]+)\s*\}\}/g;
            let lastIndex = 0;
            let match;
            while ((match = regex.exec(body)) !== null) {
                if (match.index > lastIndex) {
                    editor.appendChild(document.createTextNode(body.slice(lastIndex, match.index)));
                }
                const key = match[1].trim();
                const field = fieldIndex[key] || { key, label: key, type: 'text', source: 'user_input', required: false };
                if (!fieldCatalog.find(existing => existing.key === key)) {
                    fieldCatalog.push(field);
                }
                const span = document.createElement('span');
                span.className = 'field-chip';
                span.dataset.key = field.key;
                span.textContent = field.label;
                editor.appendChild(span);
                editor.appendChild(document.createTextNode(' '));
                lastIndex = regex.lastIndex;
            }
            if (lastIndex < body.length) {
                editor.appendChild(document.createTextNode(body.slice(lastIndex)));
            }
        };

        const syncInputs = () => {
            const bodyValue = serializeEditor();
            bodyInput.value = bodyValue;
            fieldCatalogInput.value = JSON.stringify(fieldCatalog);
            if (preview && preview.style.display !== 'none') {
                preview.textContent = bodyValue || 'No content yet.';
            }
        };

        document.getElementById('add-custom').addEventListener('click', () => {
            const label = document.getElementById('custom-label').value.trim();
            const type = document.getElementById('custom-type').value;
            const required = document.getElementById('custom-required').value === '1';
            const guidance = document.getElementById('custom-guidance').value.trim();
            if (!label) {
                alert('Please enter a label for the custom field.');
                return;
            }
            const key = `custom.${slugify(label)}`;
            const field = { key, label, type, source: 'user_input', required, guidance };
            fieldCatalog.push(field);
            fieldIndex[key] = field;
            refreshFieldList();
            refreshCatalog();
            insertChip(field);
            document.getElementById('custom-label').value = '';
            document.getElementById('custom-guidance').value = '';
        });

        previewToggle?.addEventListener('click', () => {
            if (preview.style.display === 'none') {
                preview.style.display = 'block';
                previewToggle.textContent = 'Hide placeholders';
                syncInputs();
            } else {
                preview.style.display = 'none';
                previewToggle.textContent = 'Preview placeholders';
            }
        });

        editor.addEventListener('input', syncInputs);
        editor.addEventListener('blur', syncInputs);

        if (advancedJson) {
            document.getElementById('validate-json')?.addEventListener('click', () => {
                try {
                    JSON.parse(advancedJson.value);
                    jsonStatus.textContent = 'JSON looks valid.';
                    jsonStatus.style.color = '#16a34a';
                } catch (error) {
                    jsonStatus.textContent = 'Invalid JSON: ' + error.message;
                    jsonStatus.style.color = '#dc2626';
                }
            });
        }

        refreshFieldList();
        refreshCatalog();
        if (templateData.body) {
            loadBodyIntoEditor(templateData.body);
        }
        syncInputs();
    </script>
    <?php
}
