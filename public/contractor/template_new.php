<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $contractor = load_contractor($yojId) ?? [];
    $memory = load_profile_memory($yojId);
    $missingFields = templates_missing_profile_fields($contractor);
    $registry = placeholder_registry([
        'contractor' => $contractor,
        'memory' => $memory,
    ]);
    $fieldCatalog = $registry['fields'];
    $tableCatalog = $registry['tables'];

    $title = get_app_config()['appName'] . ' | New Template';

    render_layout($title, function () use ($missingFields, $fieldCatalog, $tableCatalog) {
        $grouped = [
            'Contractor' => [],
            'Tender' => [],
            'Case/Scheme' => [],
            'Custom Saved Fields' => [],
        ];
        foreach ($fieldCatalog as $key => $meta) {
            $label = $meta['label'] ?? $key;
            if (str_starts_with($key, 'contractor.')) {
                $grouped['Contractor'][] = ['key' => $key, 'label' => $label];
            } elseif (str_starts_with($key, 'tender.')) {
                $grouped['Tender'][] = ['key' => $key, 'label' => $label];
            } elseif (str_starts_with($key, 'case.') || str_starts_with($key, 'module.')) {
                $grouped['Case/Scheme'][] = ['key' => $key, 'label' => $label];
            } elseif (str_starts_with($key, 'custom.')) {
                $grouped['Custom Saved Fields'][] = ['key' => $key, 'label' => $label];
            } else {
                $grouped['Custom Saved Fields'][] = ['key' => $key, 'label' => $label];
            }
        }
        $tables = [];
        foreach ($tableCatalog as $key => $meta) {
            $tables[] = ['key' => $key, 'label' => $meta['label'] ?? $key];
        }
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Create Template</h2>
                    <p class="muted" style="margin:4px 0 0;">Compose your template with guided placeholders. JSON is hidden from contractors.</p>
                </div>
                <a class="btn secondary" href="/contractor/templates.php?tab=mine">Back to Templates</a>
            </div>
        </div>

        <form method="post" action="/contractor/template_create.php" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <div style="display:grid; gap:12px; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr);">
                <div class="card" style="display:grid; gap:12px;">
                    <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <label class="field">
                            <span>Title</span>
                            <input type="text" name="title" required placeholder="e.g., Affidavit - Non Blacklisting">
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <select name="category" required>
                                <option value="Tender">Tender</option>
                                <option value="Workorder">Workorder</option>
                                <option value="General">General</option>
                            </select>
                        </label>
                    </div>
                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="2" placeholder="Short purpose of this template"></textarea>
                    </label>
                    <label class="field">
                        <span>Template Body</span>
                        <textarea id="template-body" name="body" rows="14" required placeholder="Write content here. Use the Insert Fields panel to add placeholders."></textarea>
                    </label>
                    <div class="muted" style="font-size:13px;">Tip: Use {{field:contractor.firm_name}} or {{field:table:items}} placeholders from the right panel.</div>
                </div>
                <div style="display:grid; gap:12px;">
                    <div class="card" style="display:grid; gap:10px;">
                        <h3 style="margin:0;">Field Helper</h3>
                        <p class="muted" style="margin:0;">Placeholders auto-fill from your profile. Missing fields are shown below.</p>
                        <?php if ($missingFields): ?>
                            <ul style="margin:0; padding-left:18px;">
                                <?php foreach ($missingFields as $label): ?>
                                    <li><?= sanitize($label); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="btn secondary" href="/contractor/profile.php">Go to Profile</a>
                        <?php else: ?>
                            <p class="muted" style="margin:0;">All key profile fields are filled ✅</p>
                        <?php endif; ?>
                    </div>
                    <div class="card" style="display:grid; gap:10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <h3 style="margin:0;">Insert Fields</h3>
                            <label style="display:flex; gap:6px; align-items:center; font-size:12px;">
                                <input type="checkbox" id="print-preview"> Print preview
                            </label>
                        </div>
                        <input type="search" id="field-search" placeholder="Search fields..." style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);">
                        <?php foreach ($grouped as $label => $items): ?>
                            <div>
                                <strong><?= sanitize($label); ?></strong>
                                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                                    <?php foreach ($items as $item): ?>
                                        <button type="button" class="btn secondary field-btn" data-key="<?= sanitize($item['key']); ?>" data-label="<?= sanitize($item['label']); ?>">
                                            <?= sanitize($item['label']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div>
                            <strong>Tables</strong>
                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                                <?php if (!$tables): ?>
                                    <span class="muted">No table placeholders available.</span>
                                <?php endif; ?>
                                <?php foreach ($tables as $table): ?>
                                    <button type="button" class="btn secondary table-btn" data-key="<?= sanitize($table['key']); ?>" data-label="<?= sanitize($table['label']); ?>">
                                        <?= sanitize($table['label']); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:12px;">
                <h3 style="margin:0 0 6px 0;">Live Preview</h3>
                <p class="muted" style="margin:0 0 10px 0;">Placeholders show friendly labels; switch to print preview to see blanks.</p>
                <div id="template-preview" style="background:var(--surface-2); border:1px dashed var(--border); padding:14px; border-radius:12px; min-height:120px; white-space:pre-wrap; line-height:1.6;"></div>
            </div>

            <div style="display:flex; gap:10px; margin-top:12px;">
                <button class="btn" type="submit">Save Template</button>
                <a class="btn secondary" href="/contractor/templates.php?tab=mine">Cancel</a>
            </div>
        </form>

        <script>
            const bodyEl = document.getElementById('template-body');
            const previewEl = document.getElementById('template-preview');
            const printToggle = document.getElementById('print-preview');
            const fieldButtons = document.querySelectorAll('.field-btn');
            const tableButtons = document.querySelectorAll('.table-btn');
            const searchInput = document.getElementById('field-search');
            const fieldMap = {};
            fieldButtons.forEach(btn => { fieldMap[btn.dataset.key.toLowerCase()] = btn.dataset.label; });
            const tableMap = {};
            tableButtons.forEach(btn => { tableMap[btn.dataset.key.toLowerCase()] = btn.dataset.label; });

            const insertAtCursor = (text) => {
                const start = bodyEl.selectionStart || 0;
                const end = bodyEl.selectionEnd || 0;
                const before = bodyEl.value.substring(0, start);
                const after = bodyEl.value.substring(end);
                bodyEl.value = before + text + after;
                bodyEl.focus();
                bodyEl.selectionStart = bodyEl.selectionEnd = start + text.length;
                updatePreview();
            };

            fieldButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.key;
                    insertAtCursor(`{{field:${key}}}`);
                });
            });
            tableButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.key;
                    insertAtCursor(`{{field:table:${key}}}`);
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    const term = (searchInput.value || '').toLowerCase();
                    document.querySelectorAll('.field-btn, .table-btn').forEach(btn => {
                        const label = (btn.dataset.label || '').toLowerCase();
                        const key = (btn.dataset.key || '').toLowerCase();
                        const match = term === '' || label.includes(term) || key.includes(term);
                        btn.style.display = match ? '' : 'none';
                    });
                });
            }

            const updatePreview = () => {
                const raw = bodyEl.value || '';
                const printMode = printToggle.checked;
                const html = raw
                    .replace(/{{\s*field:table:([^}]+)}}/gi, (match, key) => {
                        const cleaned = (key || '').trim().toLowerCase();
                        if (printMode) {
                            return '__________';
                        }
                        return `[Table: ${tableMap[cleaned] || cleaned}]`;
                    })
                    .replace(/{{\s*field:([^}]+)}}/gi, (match, key) => {
                        const cleaned = (key || '').trim().toLowerCase();
                        if (printMode) {
                            return '__________';
                        }
                        return `[${fieldMap[cleaned] || cleaned}]`;
                    });
                previewEl.textContent = html;
            };

            bodyEl.addEventListener('input', updatePreview);
            printToggle.addEventListener('change', updatePreview);
            updatePreview();
        </script>
        <?php
    });
});
