<?php
declare(strict_types=1);

function render_pack_template_editor(array $options): void
{
    $pack = $options['pack'] ?? [];
    $action = $options['action'] ?? '';
    $submitLabel = $options['submitLabel'] ?? 'Save Pack Template';
    $cancelUrl = $options['cancelUrl'] ?? '/contractor/packs_library.php';
    $isStaff = (bool)($options['isStaff'] ?? false);
    $showAdvanced = (bool)($options['showAdvanced'] ?? false);
    $scope = $options['scope'] ?? ($pack['scope'] ?? 'contractor');
    $templates = $options['templates'] ?? [];
    $fieldTypes = template_library_field_types();
    $packJson = json_encode($pack, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $packJson = $packJson !== false ? $packJson : '{}';
    ?>
    <style>
        .editor-grid { display:grid; grid-template-columns: minmax(240px, 320px) minmax(0, 1fr); gap:16px; }
        .editor-panel { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface-2); display:grid; gap:10px; }
        .field-list { display:grid; gap:6px; max-height:320px; overflow:auto; }
        .field-list button { text-align:left; border:1px solid var(--border); background:var(--surface); padding:8px; border-radius:10px; cursor:pointer; color:var(--text); }
        .field-list button:hover { border-color:var(--primary); }
        .item-row { display:grid; gap:8px; padding:10px; border:1px solid var(--border); border-radius:12px; background:var(--surface); }
        .item-row select, .item-row input { width:100%; }
        .field-table { display:grid; gap:8px; }
        .field-table .row { display:grid; grid-template-columns: 1.2fr 0.8fr 0.6fr; gap:8px; align-items:center; }
        .advanced-json textarea { min-height:220px; }
        @media (max-width: 900px) {
            .editor-grid { grid-template-columns: 1fr; }
        }
    </style>
    <form method="post" action="<?= sanitize($action); ?>" style="display:grid;gap:16px;">
        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
        <?php if (!empty($pack['packTemplateId'])): ?>
            <input type="hidden" name="packTemplateId" value="<?= sanitize($pack['packTemplateId']); ?>">
        <?php endif; ?>
        <?php if ($isStaff): ?>
            <input type="hidden" name="scope" value="<?= sanitize($scope); ?>">
        <?php endif; ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                <label style="display:grid;gap:6px;">
                    <span class="muted">Title</span>
                    <input class="input" type="text" name="title" value="<?= sanitize($pack['title'] ?? ''); ?>" required>
                </label>
                <label style="display:grid;gap:6px;">
                    <span class="muted">Status</span>
                    <select class="input" name="status" <?= $isStaff ? '' : 'disabled'; ?>>
                        <?php $status = $pack['status'] ?? 'active'; ?>
                        <option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </label>
            </div>
            <label style="display:grid;gap:6px;">
                <span class="muted">Description</span>
                <textarea class="input" name="description" rows="3" placeholder="Describe when to use this pack template."><?= sanitize($pack['description'] ?? ''); ?></textarea>
            </label>
        </div>
        <div class="card" style="display:grid;gap:12px;">
            <h3 style="margin:0;">Pack Items</h3>
            <p class="muted" style="margin:0;">Add checklist items, templates, or upload requirements.</p>
            <div id="items-list" style="display:grid;gap:10px;"></div>
            <input type="hidden" name="items" id="items-input" value="">
            <button class="btn secondary" type="button" id="add-item">Add Item</button>
        </div>
        <div class="editor-grid">
            <div class="editor-panel">
                <h3 style="margin:0;">Available Fields</h3>
                <p class="muted" style="margin:0;">These fields can be used when filling the pack.</p>
                <div class="field-list" id="field-list"></div>
            </div>
            <div class="editor-panel">
                <h3 style="margin:0;">Fields used in this pack</h3>
                <div class="field-table" id="field-catalog-list"></div>
                <input type="hidden" name="field_catalog" id="field-catalog" value="">
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
        </div>
        <?php if ($showAdvanced): ?>
            <div class="card advanced-json" style="display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;">Advanced JSON (Staff Only)</h3>
                    <p class="muted" style="margin:4px 0 0;">Paste JSON, validate, and apply. Bid/rate fields are blocked.</p>
                </div>
                <textarea class="input" name="advanced_json" id="advanced-json"><?= sanitize($packJson); ?></textarea>
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
        const templateOptions = <?= json_encode($templates, JSON_UNESCAPED_SLASHES); ?>;
        const profileFields = <?= json_encode(template_library_profile_fields(), JSON_UNESCAPED_SLASHES); ?>;
        const packData = <?= json_encode($pack, JSON_UNESCAPED_SLASHES); ?>;
        const fieldCatalog = [...(packData.fieldCatalog || [])];
        const fieldIndex = {};
        profileFields.forEach(field => fieldIndex[field.key] = field);
        fieldCatalog.forEach(field => fieldIndex[field.key] = field);
        const items = [...(packData.items || [])];

        const itemsList = document.getElementById('items-list');
        const itemsInput = document.getElementById('items-input');
        const fieldList = document.getElementById('field-list');
        const fieldCatalogInput = document.getElementById('field-catalog');
        const fieldCatalogList = document.getElementById('field-catalog-list');
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
                button.addEventListener('click', () => {
                    if (!fieldCatalog.find(existing => existing.key === field.key)) {
                        fieldCatalog.push(field);
                        refreshCatalog();
                    }
                });
                fieldList.appendChild(button);
            });
        };

        const refreshCatalog = () => {
            fieldCatalogList.innerHTML = '';
            if (!fieldCatalog.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'No fields added yet.';
                fieldCatalogList.appendChild(empty);
                return;
            }
            fieldCatalog.forEach(field => {
                const row = document.createElement('div');
                row.className = 'row';
                row.innerHTML = `<span>${field.label}</span><span class="muted">${field.key}</span><span class="muted">${field.required ? 'Required' : 'Optional'}</span>`;
                fieldCatalogList.appendChild(row);
            });
            fieldCatalogInput.value = JSON.stringify(fieldCatalog);
        };

        const renderItems = () => {
            itemsList.innerHTML = '';
            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'No items yet. Add checklists, templates, or uploads.';
                itemsList.appendChild(empty);
            }
            items.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'item-row';
                row.innerHTML = `
                    <div style="display:grid;gap:6px;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
                        <select class="input" data-field="type">
                            <option value="checklist" ${item.type === 'checklist' ? 'selected' : ''}>Checklist</option>
                            <option value="template" ${item.type === 'template' ? 'selected' : ''}>Template</option>
                            <option value="upload" ${item.type === 'upload' ? 'selected' : ''}>Upload</option>
                        </select>
                        <input class="input" data-field="title" type="text" placeholder="Item title" value="${item.title || ''}">
                        <select class="input" data-field="required">
                            <option value="1" ${item.required ? 'selected' : ''}>Required</option>
                            <option value="0" ${!item.required ? 'selected' : ''}>Optional</option>
                        </select>
                    </div>
                    <div class="template-select" style="display:${item.type === 'template' ? 'block' : 'none'};">
                        <select class="input" data-field="templateId">
                            <option value="">Select template</option>
                            ${templateOptions.map(option => `<option value="${option.templateId}" ${option.templateId === item.templateId ? 'selected' : ''}>${option.title}</option>`).join('')}
                        </select>
                    </div>
                    <button class="btn secondary" type="button" data-action="remove">Remove</button>
                `;
                row.querySelectorAll('[data-field]').forEach(input => {
                    input.addEventListener('change', (event) => {
                        const field = event.target.dataset.field;
                        let value = event.target.value;
                        if (field === 'required') {
                            value = value === '1';
                        }
                        item[field] = value;
                        if (field === 'type') {
                            row.querySelector('.template-select').style.display = value === 'template' ? 'block' : 'none';
                        }
                        syncItems();
                    });
                });
                row.querySelector('[data-action="remove"]').addEventListener('click', () => {
                    items.splice(index, 1);
                    renderItems();
                    syncItems();
                });
                itemsList.appendChild(row);
            });
            syncItems();
        };

        const syncItems = () => {
            itemsInput.value = JSON.stringify(items);
        };

        document.getElementById('add-item').addEventListener('click', () => {
            items.push({ type: 'checklist', title: '', required: true });
            renderItems();
        });

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
            document.getElementById('custom-label').value = '';
            document.getElementById('custom-guidance').value = '';
        });

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
        renderItems();
    </script>
    <?php
}
