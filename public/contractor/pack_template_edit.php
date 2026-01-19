<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function (): void {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_pack_templates_env($yojId);
    ensure_contractor_templates_env($yojId);
    ensure_global_templates_seeded();

    $packTemplateId = trim((string)($_GET['packTemplateId'] ?? ''));
    $template = null;
    if ($packTemplateId !== '') {
        $template = load_contractor_pack_template($yojId, $packTemplateId);
        if (!$template) {
            render_error_page('Pack template not found.');
            return;
        }
    }

    $contractorTemplates = load_contractor_templates_full($yojId);
    $globalTemplates = load_global_templates_full();

    $title = get_app_config()['appName'] . ' | Pack Template Editor';

    render_layout($title, function () use ($template, $contractorTemplates, $globalTemplates) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div>
                <h2 style="margin:0;"><?= sanitize($template ? 'Edit Pack Template' : 'Create My Pack Template'); ?></h2>
                <p class="muted" style="margin:4px 0 0;">Pack templates make repeating tender submissions faster.</p>
            </div>
            <form method="post" action="/contractor/pack_template_save.php" id="pack-template-form" style="display:grid;gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <?php if ($template): ?>
                    <input type="hidden" name="packTemplateId" value="<?= sanitize($template['packTemplateId']); ?>">
                <?php endif; ?>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Title</span>
                        <input class="input" type="text" name="title" value="<?= sanitize($template['title'] ?? ''); ?>" required>
                    </label>
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Description</span>
                        <input class="input" type="text" name="description" value="<?= sanitize($template['description'] ?? ''); ?>">
                    </label>
                </div>

                <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:12px;">
                    <h3 style="margin:0;">Items</h3>
                    <p class="muted" style="margin:0;">Add template references, required uploads, or custom checklist items.</p>
                    <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <label style="display:grid;gap:6px;">
                            <span class="muted">Item Type</span>
                            <select class="input" id="item-type">
                                <option value="templateRef">Template Reference</option>
                                <option value="upload">Upload Requirement</option>
                                <option value="checklist">Checklist Item</option>
                            </select>
                        </label>
                        <label style="display:grid;gap:6px;" id="template-select-wrap">
                            <span class="muted">Template</span>
                            <select class="input" id="template-select">
                                <option value="">Select template</option>
                                <?php foreach ($globalTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId']); ?>">[Default] <?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                                <?php foreach ($contractorTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['templateId']); ?>">[My] <?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="display:grid;gap:6px;" id="label-wrap">
                            <span class="muted">Label</span>
                            <input class="input" type="text" id="item-label" placeholder="GST Certificate">
                        </label>
                        <label style="display:grid;gap:6px;" id="vault-wrap">
                            <span class="muted">Vault Tag Hint</span>
                            <input class="input" type="text" id="vault-tag" placeholder="GST">
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;margin-top:24px;">
                            <input type="checkbox" id="item-required" checked>
                            <span class="muted">Required</span>
                        </label>
                    </div>
                    <button class="btn secondary" type="button" id="add-item">Add Item</button>
                    <div id="item-list" style="display:grid;gap:8px;"></div>
                    <input type="hidden" name="items_json" id="items-json" value='<?= sanitize(json_encode($template['items'] ?? [])); ?>'>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit"><?= sanitize($template ? 'Save Pack Template' : 'Create Pack Template'); ?></button>
                    <a class="btn secondary" href="/contractor/packs.php">Cancel</a>
                </div>
            </form>
        </div>

        <script>
            const itemsJsonField = document.getElementById('items-json');
            const itemList = document.getElementById('item-list');
            const itemType = document.getElementById('item-type');
            const templateSelect = document.getElementById('template-select');
            const labelInput = document.getElementById('item-label');
            const vaultInput = document.getElementById('vault-tag');
            const requiredInput = document.getElementById('item-required');
            const templateSelectWrap = document.getElementById('template-select-wrap');
            const labelWrap = document.getElementById('label-wrap');
            const vaultWrap = document.getElementById('vault-wrap');

            let items = [];
            try {
                items = JSON.parse(itemsJsonField.value || '[]');
                if (!Array.isArray(items)) {
                    items = [];
                }
            } catch (e) {
                items = [];
            }

            const refreshForm = () => {
                const type = itemType.value;
                templateSelectWrap.style.display = type === 'templateRef' ? 'grid' : 'none';
                labelWrap.style.display = type === 'templateRef' ? 'none' : 'grid';
                vaultWrap.style.display = type === 'upload' ? 'grid' : 'none';
            };

            const renderItems = () => {
                itemList.innerHTML = '';
                items.forEach((item, index) => {
                    const wrapper = document.createElement('div');
                    wrapper.style.border = '1px solid var(--border)';
                    wrapper.style.borderRadius = '10px';
                    wrapper.style.padding = '10px';
                    wrapper.style.display = 'grid';
                    wrapper.style.gap = '6px';
                    wrapper.style.background = 'var(--surface-2)';
                    wrapper.innerHTML = `
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                            <strong>${item.label || item.templateId || 'Item'}</strong>
                            <button type="button" class="btn secondary" data-remove="${index}">Remove</button>
                        </div>
                        <div class="muted">Type: ${item.type || 'checklist'}</div>
                        <div class="muted">Required: ${item.required ? 'Yes' : 'No'}</div>
                    `;
                    itemList.appendChild(wrapper);
                });
                itemsJsonField.value = JSON.stringify(items);
                document.querySelectorAll('[data-remove]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const idx = parseInt(btn.getAttribute('data-remove'), 10);
                        items.splice(idx, 1);
                        renderItems();
                    });
                });
            };

            document.getElementById('add-item').addEventListener('click', () => {
                const type = itemType.value;
                if (type === 'templateRef') {
                    const templateId = templateSelect.value;
                    if (!templateId) {
                        alert('Select a template.');
                        return;
                    }
                    items.push({
                        type: 'templateRef',
                        templateId,
                        required: requiredInput.checked,
                    });
                } else {
                    const label = labelInput.value.trim();
                    if (!label) {
                        alert('Enter a label.');
                        return;
                    }
                    const item = {
                        type: type === 'upload' ? 'upload' : 'checklist',
                        label,
                        required: requiredInput.checked,
                    };
                    if (type === 'upload') {
                        item.vaultTagHint = vaultInput.value.trim();
                    }
                    items.push(item);
                    labelInput.value = '';
                    vaultInput.value = '';
                }
                renderItems();
            });

            itemType.addEventListener('change', refreshForm);
            refreshForm();
            renderItems();
        </script>
        <?php
    });
});
