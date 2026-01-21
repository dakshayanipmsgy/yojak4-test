<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');

    $tplId = trim((string)($_GET['id'] ?? ''));
    $requestId = trim((string)($_GET['requestId'] ?? ''));
    $template = null;
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

    if ($tplId !== '') {
        $template = load_global_template($tplId);
        if (!$template) {
            render_error_page('Template not found.');
            return;
        }
        $scope = 'global';
    }
    $migrationStats = [];
    if ($template) {
        $templateBody = (string)($template['body'] ?? '');
        $migratedBody = migrate_placeholders_to_canonical($templateBody, $migrationStats);
        $template['body'] = $migratedBody;
    }
    $wasMigrated = !empty($template) && !empty($migrationStats['migrated']);

    $registry = placeholder_registry();
    $fieldCatalog = $registry['fields'];
    $tableCatalog = $registry['tables'];

    $title = get_app_config()['appName'] . ' | ' . ($template ? 'Edit Template' : 'New Template');

    render_layout($title, function () use ($template, $request, $requestId, $scope, $ownerYojId, $fieldCatalog, $tableCatalog, $wasMigrated) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= $template ? 'Edit Global Template' : 'Create Template'; ?></h2>
                    <p class="muted" style="margin:4px 0 0;">Staff can edit with guided UI or advanced JSON.</p>
                </div>
                <a class="btn secondary" href="/superadmin/templates.php">Back to Templates</a>
            </div>
        </div>

        <?php if ($wasMigrated): ?>
            <div class="card" style="margin-top:12px;border-color:#fbbf24;background:#fff8e1;">
                <strong>Template upgraded:</strong> Placeholder syntax has been migrated to the canonical format. Review and save to keep the changes.
            </div>
        <?php endif; ?>

        <form method="post" action="/superadmin/template_update.php" style="margin-top:12px;">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <input type="hidden" name="tplId" value="<?= sanitize($template['id'] ?? ''); ?>">
            <input type="hidden" name="requestId" value="<?= sanitize($requestId); ?>">

            <div style="display:grid; gap:12px; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);">
                <div class="card" style="display:grid; gap:12px;">
                    <?php if (!$template): ?>
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

                    <div style="display:grid; gap:10px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                        <label class="field">
                            <span>Title</span>
                            <input type="text" name="title" required value="<?= sanitize($template['title'] ?? ''); ?>">
                        </label>
                        <label class="field">
                            <span>Category</span>
                            <?php $category = $template['category'] ?? 'General'; ?>
                            <select name="category" required>
                                <option value="Tender" <?= $category === 'Tender' ? 'selected' : ''; ?>>Tender</option>
                                <option value="Workorder" <?= $category === 'Workorder' ? 'selected' : ''; ?>>Workorder</option>
                                <option value="General" <?= $category === 'General' ? 'selected' : ''; ?>>General</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Published</span>
                            <select name="published">
                                <option value="1" <?= !empty($template['published']) ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?= empty($template) || !empty($template['published']) ? '' : 'selected'; ?>>No</option>
                            </select>
                        </label>
                    </div>
                    <label class="field">
                        <span>Description</span>
                        <textarea name="description" rows="2"><?= sanitize($template['description'] ?? ''); ?></textarea>
                    </label>
                    <label class="field">
                        <span>Template Body</span>
                        <textarea id="template-body" name="body" rows="12" required><?= sanitize($template['body'] ?? ''); ?></textarea>
                    </label>
                </div>
                <div class="card" style="display:grid; gap:10px; align-content:start;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <h3 style="margin:0;">Insert Field</h3>
                    </div>
                    <input type="search" id="field-search" placeholder="Search fields..." style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);">
                    <div>
                        <strong>Contractor</strong>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                            <?php foreach ($fieldCatalog as $key => $meta): ?>
                                <?php if (!str_starts_with($key, 'contractor.')) { continue; } ?>
                                <button type="button" class="btn secondary field-btn" data-key="<?= sanitize($key); ?>" data-label="<?= sanitize($meta['label'] ?? $key); ?>">
                                    <?= sanitize($meta['label'] ?? $key); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <strong>Tender</strong>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                            <?php foreach ($fieldCatalog as $key => $meta): ?>
                                <?php if (!str_starts_with($key, 'tender.')) { continue; } ?>
                                <button type="button" class="btn secondary field-btn" data-key="<?= sanitize($key); ?>" data-label="<?= sanitize($meta['label'] ?? $key); ?>">
                                    <?= sanitize($meta['label'] ?? $key); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <strong>Tables</strong>
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                            <?php foreach ($tableCatalog as $key => $meta): ?>
                                <button type="button" class="btn secondary table-btn" data-key="<?= sanitize($key); ?>" data-label="<?= sanitize($meta['label'] ?? $key); ?>">
                                    <?= sanitize($meta['label'] ?? $key); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:12px;">
                <h3 style="margin:0 0 8px 0;">Advanced JSON (Staff only)</h3>
                <p class="muted" style="margin:0 0 8px 0;">Paste JSON to validate and apply. This will override fields above.</p>
                <textarea name="json_payload" rows="8" placeholder="{ ... }"><?= sanitize($template ? json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : ''); ?></textarea>
                <div style="margin-top:8px;">
                    <button class="btn secondary" type="submit" name="apply_json" value="1">Validate &amp; Apply JSON</button>
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:12px;">
                <button class="btn" type="submit">Save Template</button>
                <a class="btn secondary" href="/superadmin/templates.php">Cancel</a>
            </div>
        </form>

        <script>
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

            const bodyEl = document.getElementById('template-body');
            const fieldButtons = document.querySelectorAll('.field-btn');
            const tableButtons = document.querySelectorAll('.table-btn');
            const searchInput = document.getElementById('field-search');
            const insertAtCursor = (text) => {
                if (!bodyEl) return;
                const start = bodyEl.selectionStart || 0;
                const end = bodyEl.selectionEnd || 0;
                const before = bodyEl.value.substring(0, start);
                const after = bodyEl.value.substring(end);
                bodyEl.value = before + text + after;
                bodyEl.focus();
                bodyEl.selectionStart = bodyEl.selectionEnd = start + text.length;
            };
            fieldButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.key || '';
                    insertAtCursor(`{{field:${key}}}`);
                });
            });
            tableButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.key || '';
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
        </script>
        <?php
    });
});
