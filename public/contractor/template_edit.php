<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_templates_env($yojId);

    $templateId = trim((string)($_GET['templateId'] ?? ''));
    $template = null;
    if ($templateId !== '') {
        $template = load_contractor_template($yojId, $templateId);
        if (!$template) {
            render_error_page('Template not found.');
            return;
        }
        $template = normalize_template_schema($template, 'contractor', $yojId);
    }

    $fields = template_guidance_fields($yojId);
    $title = get_app_config()['appName'] . ' | Template Editor';

    render_layout($title, function () use ($template, $fields) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;"><?= sanitize($template ? 'Edit Template' : 'Create My Template'); ?></h2>
                <p class="muted" style="margin:4px 0 0;">Use the guidance panel to insert placeholders. No JSON is shown to contractors.</p>
            </div>
            <form method="post" action="/contractor/template_save.php" id="template-form" style="display:grid;gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <?php if ($template): ?>
                    <input type="hidden" name="templateId" value="<?= sanitize($template['templateId']); ?>">
                <?php endif; ?>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Title</span>
                        <input class="input" type="text" name="title" value="<?= sanitize($template['title'] ?? ''); ?>" required>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Category</span>
                        <select class="input" name="category">
                            <?php
                            $categories = ['tender' => 'Tender', 'workorder' => 'Workorder', 'billing' => 'Billing', 'general' => 'General'];
                            $current = $template['category'] ?? 'tender';
                            foreach ($categories as $key => $label) {
                                $selected = $current === $key ? 'selected' : '';
                                echo '<option value="' . sanitize($key) . '" ' . $selected . '>' . sanitize($label) . '</option>';
                            }
                            ?>
                        </select>
                    </label>
                </div>
                <label style="display:grid;gap:6px;">
                    <span class="muted">Description</span>
                    <textarea class="input" name="description" rows="3"><?= sanitize($template['description'] ?? ''); ?></textarea>
                </label>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));align-items:start;">
                    <div style="display:grid;gap:8px;">
                        <label class="muted">Body</label>
                        <textarea class="input" name="body" id="template-body" rows="16" placeholder="Start writing your template..."><?= sanitize($template['body'] ?? ''); ?></textarea>
                        <p class="muted" style="margin:0;">Placeholders print as blanks and do not expose raw tokens on print.</p>
                    </div>
                    <div style="display:grid;gap:10px;border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);">
                        <h3 style="margin:0;">Placeholder Guidance</h3>
                        <p class="muted" style="margin:0;">Click a field to insert <code>{{field:key}}</code> into the body.</p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="btn secondary" type="button" data-insert="{{blank:short}}">Insert Blank (Short)</button>
                            <button class="btn secondary" type="button" data-insert="{{blank:long}}">Insert Blank (Long)</button>
                            <button class="btn secondary" type="button" data-insert="{{yesno:Option}}">Insert Yes/No</button>
                        </div>
                        <div style="max-height:260px;overflow:auto;display:grid;gap:6px;padding-right:6px;">
                            <?php foreach ($fields as $field): ?>
                                <button class="btn secondary" type="button" data-insert="{{field:<?= sanitize($field['key']); ?>}}" style="justify-content:flex-start;">
                                    <?= sanitize($field['label']); ?>
                                    <span class="muted" style="margin-left:auto;"><?= sanitize($field['key']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:10px;">
                    <h3 style="margin:0;">Tables</h3>
                    <p class="muted" style="margin:0;">Add tables to insert <code>{{field:table:&lt;tableId&gt;}}</code>. Rate columns are for internal manual entry only.</p>
                    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Table ID</span>
                            <input class="input" type="text" id="table-id" placeholder="items">
                        </label>
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Title</span>
                            <input class="input" type="text" id="table-title" placeholder="Items">
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn secondary" type="button" id="add-standard-columns">Add standard columns</button>
                        <button class="btn secondary" type="button" id="add-table">Add table</button>
                    </div>
                    <div id="table-columns" style="display:grid;gap:8px;"></div>
                    <div id="table-list" style="display:grid;gap:8px;"></div>
                    <input type="hidden" name="tables_json" id="tables-json" value='<?= sanitize(json_encode($template['tables'] ?? [])); ?>'>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit"><?= sanitize($template ? 'Save Template' : 'Create Template'); ?></button>
                    <a class="btn secondary" href="/contractor/templates.php">Cancel</a>
                </div>
            </form>
        </div>

        <script>
            const bodyField = document.getElementById('template-body');
            document.querySelectorAll('[data-insert]').forEach(button => {
                button.addEventListener('click', () => {
                    const token = button.getAttribute('data-insert') || '';
                    const start = bodyField.selectionStart || 0;
                    const end = bodyField.selectionEnd || 0;
                    const value = bodyField.value;
                    bodyField.value = value.substring(0, start) + token + value.substring(end);
                    bodyField.focus();
                    bodyField.selectionStart = bodyField.selectionEnd = start + token.length;
                });
            });

            const tablesJsonField = document.getElementById('tables-json');
            const tableList = document.getElementById('table-list');
            const tableColumns = document.getElementById('table-columns');
            let tables = [];
            try {
                tables = JSON.parse(tablesJsonField.value || '[]');
                if (!Array.isArray(tables)) {
                    tables = [];
                }
            } catch (e) {
                tables = [];
            }

            const renderTables = () => {
                tableList.innerHTML = '';
                tables.forEach((table, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.style.border = '1px solid var(--border)';
                    wrapper.style.borderRadius = '10px';
                    wrapper.style.padding = '10px';
                    wrapper.style.display = 'grid';
                    wrapper.style.gap = '8px';
                    wrapper.style.background = 'var(--surface-2)';
                    wrapper.innerHTML = `
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                            <strong>${table.title || table.tableId || 'Table'}</strong>
                            <button type="button" class="btn secondary" data-remove="${index}">Remove</button>
                        </div>
                        <div class="muted">Table ID: ${table.tableId || ''}</div>
                        <div class="muted">Columns: ${(table.columns || []).map(col => col.label || col.key).join(', ')}</div>
                        <button type="button" class="btn secondary" data-insert="{{field:table:${table.tableId}}}">Insert Table Placeholder</button>
                    `;
                    tableList.appendChild(wrapper);
                });
                tablesJsonField.value = JSON.stringify(tables);
                document.querySelectorAll('[data-remove]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const idx = parseInt(btn.getAttribute('data-remove'), 10);
                        tables.splice(idx, 1);
                        renderTables();
                    });
                });
                document.querySelectorAll('[data-insert]').forEach(button => {
                    if (!button.closest('#table-list')) {
                        return;
                    }
                    button.addEventListener('click', () => {
                        const token = button.getAttribute('data-insert') || '';
                        const start = bodyField.selectionStart || 0;
                        const end = bodyField.selectionEnd || 0;
                        const value = bodyField.value;
                        bodyField.value = value.substring(0, start) + token + value.substring(end);
                        bodyField.focus();
                        bodyField.selectionStart = bodyField.selectionEnd = start + token.length;
                    });
                });
            };

            const renderColumnsEditor = (columns = []) => {
                tableColumns.innerHTML = '';
                columns.forEach((col, idx) => {
                    const row = document.createElement('div');
                    row.style.display = 'grid';
                    row.style.gap = '6px';
                    row.style.gridTemplateColumns = '1fr 1fr 1fr auto';
                    row.innerHTML = `
                        <input class="input" type="text" data-col-key="${idx}" placeholder="key" value="${col.key || ''}">
                        <input class="input" type="text" data-col-label="${idx}" placeholder="label" value="${col.label || ''}">
                        <select class="input" data-col-type="${idx}">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="computed">Computed</option>
                        </select>
                        <button class="btn secondary" type="button" data-col-remove="${idx}">Remove</button>
                    `;
                    tableColumns.appendChild(row);
                    const select = row.querySelector(`[data-col-type="${idx}"]`);
                    if (select) {
                        select.value = col.type || 'text';
                    }
                });
                document.querySelectorAll('[data-col-remove]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const idx = parseInt(btn.getAttribute('data-col-remove'), 10);
                        columns.splice(idx, 1);
                        renderColumnsEditor(columns);
                    });
                });
            };

            let draftColumns = [];
            renderColumnsEditor(draftColumns);

            document.getElementById('add-standard-columns').addEventListener('click', () => {
                draftColumns = [
                    { key: 'desc', label: 'Item Description', type: 'text' },
                    { key: 'qty', label: 'Qty', type: 'number' },
                    { key: 'unit', label: 'Unit', type: 'text' },
                    { key: 'rate', label: 'Rate', type: 'number', allowManual: true, defaultBlank: true },
                    { key: 'amount', label: 'Amount', type: 'computed', formula: 'qty*rate', defaultBlank: true },
                ];
                renderColumnsEditor(draftColumns);
            });

            document.getElementById('add-table').addEventListener('click', () => {
                const tableId = document.getElementById('table-id').value.trim();
                const title = document.getElementById('table-title').value.trim();
                if (!tableId) {
                    alert('Table ID is required.');
                    return;
                }
                const columns = [];
                const keys = tableColumns.querySelectorAll('[data-col-key]');
                keys.forEach((input, idx) => {
                    const key = input.value.trim();
                    const labelInput = tableColumns.querySelector(`[data-col-label="${idx}"]`);
                    const typeInput = tableColumns.querySelector(`[data-col-type="${idx}"]`);
                    if (!key) {
                        return;
                    }
                    const label = labelInput ? labelInput.value.trim() : key;
                    const type = typeInput ? typeInput.value : 'text';
                    const column = { key, label, type };
                    if (key === 'rate') {
                        column.allowManual = true;
                        column.defaultBlank = true;
                    }
                    if (key === 'amount') {
                        column.defaultBlank = true;
                    }
                    columns.push(column);
                });
                if (!columns.length) {
                    alert('Add at least one column.');
                    return;
                }
                tables.push({ tableId, title, columns });
                document.getElementById('table-id').value = '';
                document.getElementById('table-title').value = '';
                draftColumns = [];
                renderColumnsEditor(draftColumns);
                renderTables();
            });

            renderTables();
        </script>
        <?php
    });
});
