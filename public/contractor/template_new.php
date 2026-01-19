<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];

    $contractor = load_contractor($yojId) ?? [];
    $memory = load_profile_memory($yojId);
    $missingFields = templates_missing_profile_fields($contractor);
    $fieldCatalog = templates_field_catalog();

    $title = get_app_config()['appName'] . ' | New Template';

    render_layout($title, function () use ($missingFields, $fieldCatalog, $memory) {
        $groups = [
            'Contractor Profile Fields' => ['Contractor Contact', 'Bank Details', 'Signatory'],
            'Common Tender Fields' => ['Tender Meta'],
            'Additional Fields' => ['Other', 'Compliance Table'],
        ];
        $customFields = $memory['fields'] ?? [];
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
                    <div class="muted" style="font-size:13px;">Tip: Use {{field:contractor.firm_name}}-style placeholders from the right panel.</div>
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
                        <?php foreach ($groups as $label => $groupNames): ?>
                            <div>
                                <strong><?= sanitize($label); ?></strong>
                                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                                    <?php foreach ($fieldCatalog as $key => $meta): ?>
                                        <?php if (!in_array($meta['group'] ?? '', $groupNames, true)) { continue; } ?>
                                        <button type="button" class="btn secondary field-btn" data-key="<?= sanitize($key); ?>" data-label="<?= sanitize($meta['label'] ?? $key); ?>">
                                            <?= sanitize($meta['label'] ?? $key); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div>
                            <strong>Custom Saved Fields</strong>
                            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                                <?php if (!$customFields): ?>
                                    <span class="muted">No custom fields saved yet.</span>
                                <?php endif; ?>
                                <?php foreach ($customFields as $key => $entry): ?>
                                    <button type="button" class="btn secondary field-btn" data-key="<?= sanitize($key); ?>" data-label="<?= sanitize(profile_memory_label_from_key((string)$key)); ?>">
                                        <?= sanitize(profile_memory_label_from_key((string)$key)); ?>
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
            const fieldMap = {};
            fieldButtons.forEach(btn => { fieldMap[btn.dataset.key.toLowerCase()] = btn.dataset.label; });

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

            const updatePreview = () => {
                const raw = bodyEl.value || '';
                const printMode = printToggle.checked;
                const html = raw.replace(/{{\s*field:([^}]+)}}/gi, (match, key) => {
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
