<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');

    $packId = trim((string)($_GET['id'] ?? ''));
    $requestId = trim((string)($_GET['requestId'] ?? ''));
    $pack = null;
    $request = null;
    $scope = 'global';
    $ownerYojId = '';

    if ($requestId !== '') {
        $request = load_request($requestId);
        if ($request) {
            $ownerYojId = $request['yojId'] ?? '';
            $scope = 'contractor';
        }
    }

    if ($packId !== '') {
        $pack = load_global_pack($packId);
        if (!$pack) {
            render_error_page('Pack blueprint not found.');
            return;
        }
        $scope = 'global';
    }

    $globalTemplates = array_values(array_filter(load_global_templates(), fn($tpl) => !empty($tpl['published'])));

    $title = get_app_config()['appName'] . ' | ' . ($pack ? 'Edit Pack Blueprint' : 'New Pack Blueprint');

    render_layout($title, function () use ($pack, $requestId, $scope, $ownerYojId, $globalTemplates) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= $pack ? 'Edit Global Pack Blueprint' : 'Create Pack Blueprint'; ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Staff can manage default packs and deliver requests.</p>
                </div>
                <a class="btn secondary" href="/superadmin/packs.php">Back to Packs</a>
            </div>
        </div>

        <form method="post" action="/superadmin/pack_update.php" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="packId" value="<?= sanitize($pack['id'] ?? ''); ?>">
            <input type="hidden" name="requestId" value="<?= sanitize($requestId); ?>">

            <div class="card" style="display:grid; gap:12px;">
                <?php if (!$pack): ?>
                    <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <label class="field">
                            <span>Scope</span>
                            <select name="scope" id="scope-select" required>
                                <option value="global" <?= $scope === 'global' ? 'selected' : ''; ?>>Global Default</option>
                                <option value="contractor" <?= $scope === 'contractor' ? 'selected' : ''; ?>>Contractor Custom</option>
                            </select>
                        </label>
                        <label class="field" id="owner-yoj" style="<?= $scope === 'contractor' ? '' : 'display:none;'; ?>">
                            <span>Contractor YOJ ID</span>
                            <input type="text" name="owner_yoj" value="<?= sanitize($ownerYojId); ?>" placeholder="YOJ-XXXXX">
                        </label>
                    </div>
                <?php endif; ?>

                <label class="field">
                    <span>Title</span>
                    <input type="text" name="title" required value="<?= sanitize($pack['title'] ?? ''); ?>">
                </label>
                <label class="field">
                    <span>Description</span>
                    <textarea name="description" rows="2"><?= sanitize($pack['description'] ?? ''); ?></textarea>
                </label>
                <div>
                    <strong>Items</strong>
                    <div id="items" style="display:grid; gap:10px; margin-top:8px;"></div>
                    <button class="btn secondary" type="button" id="add-item">Add Item</button>
                </div>
            </div>

            <div class="card" style="margin-top:12px;">
                <h3 style="margin:0 0 8px 0;">Advanced JSON (Staff only)</h3>
                <p class="muted" style="margin:0 0 8px 0;">Paste JSON to validate and apply. This overrides fields above.</p>
                <textarea name="json_payload" rows="8" placeholder="{ ... }"><?= sanitize($pack ? json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ''); ?></textarea>
                <div style="margin-top:8px;">
                    <button class="btn secondary" type="submit" name="apply_json" value="1">Validate &amp; Apply JSON</button>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:12px;">
                <button class="btn" type="submit">Save Pack Blueprint</button>
                <a class="btn secondary" href="/superadmin/packs.php">Cancel</a>
            </div>
        </form>

        <template id="item-template">
            <div class="card" style="display:grid; gap:8px;">
                <div style="display:grid; gap:8px; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); align-items:center;">
                    <label class="field">
                        <span>Type</span>
                        <select name="item_type[]" class="item-type" required>
                            <option value="checklist_item">Checklist Item</option>
                            <option value="upload_required">Upload Required</option>
                            <option value="template_ref">Template Reference</option>
                        </select>
                    </label>
                    <label class="field item-title">
                        <span>Title</span>
                        <input type="text" name="item_title[]" class="item-title-input" placeholder="e.g., PAN">
                    </label>
                    <label class="field item-template" style="display:none;">
                        <span>Template</span>
                        <select name="item_template[]" class="item-template-select">
                            <option value="">Select template</option>
                            <optgroup label="YOJAK Defaults">
                                <?php foreach ($globalTemplates as $tpl): ?>
                                    <option value="<?= sanitize($tpl['id'] ?? ''); ?>"><?= sanitize($tpl['title'] ?? 'Template'); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </label>
                    <label class="field" style="display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="item_required[]" class="item-required" value="1" checked>
                        Required
                    </label>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn secondary move-up" type="button">Move Up</button>
                    <button class="btn secondary move-down" type="button">Move Down</button>
                    <button class="btn secondary remove-item" type="button">Remove</button>
                </div>
            </div>
        </template>

        <script>
            const itemsContainer = document.getElementById('items');
            const template = document.getElementById('item-template');
            const existingItems = <?= json_encode($pack['items'] ?? []); ?>;

            const updateNames = () => {
                [...itemsContainer.children].forEach((card, idx) => {
                    const typeSelect = card.querySelector('.item-type');
                    const titleInput = card.querySelector('.item-title-input');
                    const templateSelect = card.querySelector('.item-template-select');
                    const requiredBox = card.querySelector('.item-required');
                    if (typeSelect) typeSelect.name = `item_type[${idx}]`;
                    if (titleInput) titleInput.name = `item_title[${idx}]`;
                    if (templateSelect) templateSelect.name = `item_template[${idx}]`;
                    if (requiredBox) requiredBox.name = `item_required[${idx}]`;
                });
            };

            const attachHandlers = (card) => {
                const typeSelect = card.querySelector('.item-type');
                const titleField = card.querySelector('.item-title');
                const templateField = card.querySelector('.item-template');
                const updateVisibility = () => {
                    const type = typeSelect.value;
                    if (type === 'template_ref') {
                        templateField.style.display = '';
                        titleField.style.display = 'none';
                    } else {
                        templateField.style.display = 'none';
                        titleField.style.display = '';
                    }
                };
                typeSelect.addEventListener('change', updateVisibility);
                updateVisibility();

                card.querySelector('.remove-item').addEventListener('click', () => {
                    card.remove();
                    updateNames();
                });
                card.querySelector('.move-up').addEventListener('click', () => {
                    const prev = card.previousElementSibling;
                    if (prev) itemsContainer.insertBefore(card, prev);
                    updateNames();
                });
                card.querySelector('.move-down').addEventListener('click', () => {
                    const next = card.nextElementSibling;
                    if (next) itemsContainer.insertBefore(next, card);
                    updateNames();
                });
            };

            const addItem = (item = {}) => {
                const node = template.content.cloneNode(true);
                const card = node.querySelector('.card');
                const typeSelect = card.querySelector('.item-type');
                const titleInput = card.querySelector('input[name="item_title[]"]');
                const templateSelect = card.querySelector('select[name="item_template[]"]');
                const requiredBox = card.querySelector('input[name="item_required[]"]');

                typeSelect.value = item.type || 'checklist_item';
                titleInput.value = item.title || '';
                templateSelect.value = item.templateId || '';
                requiredBox.checked = item.required !== false;

                itemsContainer.appendChild(node);
                attachHandlers(card);
                updateNames();
            };

            document.getElementById('add-item').addEventListener('click', () => addItem());

            if (existingItems.length) {
                existingItems.forEach(item => addItem(item));
            } else {
                addItem();
            }

            const scopeSelect = document.getElementById('scope-select');
            if (scopeSelect) {
                const ownerBlock = document.getElementById('owner-yoj');
                scopeSelect.addEventListener('change', () => {
                    if (scopeSelect.value === 'contractor') {
                        ownerBlock.style.display = '';
                    } else {
                        ownerBlock.style.display = 'none';
                    }
                });
            }
        </script>
        <?php
    });
});
