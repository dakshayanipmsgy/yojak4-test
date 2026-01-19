<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('templates_manage');
    $tplId = trim((string)($_GET['id'] ?? ''));
    $download = ($_GET['download'] ?? '') === '1';
    $template = null;
    if ($tplId !== '') {
        $template = template_load('global', $tplId);
        if (!$template) {
            render_error_page('Template not found.');
            return;
        }
    }

    if ($download) {
        if (!$template) {
            render_error_page('Template not found.');
            return;
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($template['id'] ?? 'template') . '.json"');
        echo json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return;
    }

    $groups = template_placeholder_groups(($template['owner']['yojId'] ?? '') !== '' ? (string)$template['owner']['yojId'] : 'YOJAK');
    $title = get_app_config()['appName'] . ' | Template Editor';

    render_layout($title, function () use ($template, $groups) {
        $jsonValue = $template ? json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '';
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize($template ? 'Edit Global Template' : 'Create Global Template'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Staff can edit via form or Advanced JSON.'); ?></p>
            </div>
            <div class="tabs">
                <button class="tab active" data-tab="editor"><?= sanitize('Editor'); ?></button>
                <button class="tab" data-tab="json"><?= sanitize('Advanced JSON'); ?></button>
            </div>

            <div class="tab-content active" id="tab-editor">
                <form method="post" action="/superadmin/template_save.php" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <?php if ($template): ?>
                        <input type="hidden" name="id" value="<?= sanitize($template['id'] ?? ''); ?>">
                    <?php endif; ?>
                    <div style="display:grid;gap:12px;grid-template-columns: minmax(0,1fr) minmax(240px, 320px);">
                        <div style="display:grid;gap:12px;">
                            <label>
                                <?= sanitize('Title') ?>
                                <input type="text" name="title" required value="<?= sanitize($template['title'] ?? ''); ?>">
                            </label>
                            <label>
                                <?= sanitize('Category') ?>
                                <select name="category">
                                    <?php foreach (template_allowed_categories() as $category): ?>
                                        <option value="<?= sanitize($category); ?>" <?= (($template['category'] ?? '') === $category) ? 'selected' : ''; ?>><?= sanitize($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <?= sanitize('Description') ?>
                                <textarea name="description" rows="2"><?= sanitize($template['description'] ?? ''); ?></textarea>
                            </label>
                            <label>
                                <?= sanitize('Template body') ?>
                                <textarea id="templateBody" name="body" rows="14" required><?= sanitize($template['body'] ?? ''); ?></textarea>
                            </label>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <button class="btn" type="submit"><?= sanitize('Save Template'); ?></button>
                                <?php if ($template): ?>
                                    <a class="btn secondary" href="/superadmin/template_edit.php?id=<?= sanitize($template['id'] ?? ''); ?>&download=1"><?= sanitize('Export JSON'); ?></a>
                                <?php endif; ?>
                                <a class="btn secondary" href="/superadmin/templates.php"><?= sanitize('Back'); ?></a>
                            </div>
                        </div>
                        <aside style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:10px;">
                            <h4 style="margin:0;"><?= sanitize('Placeholder guidance'); ?></h4>
                            <p class="muted" style="margin:0;"><?= sanitize('Click to insert. Tokens look like {{field:key}}.'); ?></p>
                            <?php foreach ($groups as $group => $items): ?>
                                <div>
                                    <strong><?= sanitize($group); ?></strong>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                                        <?php foreach ($items as $item): ?>
                                            <button type="button" class="tag insert-placeholder" data-token="{{field:<?= sanitize($item['key']); ?>}}"><?= sanitize($item['label']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </aside>
                    </div>
                </form>
            </div>

            <div class="tab-content" id="tab-json">
                <form method="post" action="/superadmin/template_import_json.php" style="display:grid;gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <?php if ($template): ?>
                        <input type="hidden" name="id" value="<?= sanitize($template['id'] ?? ''); ?>">
                    <?php endif; ?>
                    <label>
                        <?= sanitize('Paste template JSON') ?>
                        <textarea name="json" rows="14" required><?= sanitize($jsonValue); ?></textarea>
                    </label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn" type="submit"><?= sanitize('Validate & Apply JSON'); ?></button>
                        <a class="btn secondary" href="/superadmin/templates.php"><?= sanitize('Back'); ?></a>
                    </div>
                    <p class="muted"><?= sanitize('JSON must match the schema. Bid/rate values are not allowed.'); ?></p>
                </form>
            </div>
        </div>
        <style>
            .tabs{display:flex;gap:8px;flex-wrap:wrap;}
            .tab{border:1px solid var(--border);background:var(--surface-2);padding:6px 12px;border-radius:999px;cursor:pointer;color:var(--text);}
            .tab.active{border-color:#1f6feb;background:#0b1f3a;color:#fff;}
            .tab-content{display:none;}
            .tab-content.active{display:block;}
        </style>
        <script>
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    tab.classList.add('active');
                    const target = document.getElementById('tab-' + tab.dataset.tab);
                    if (target) {
                        target.classList.add('active');
                    }
                });
            });
            document.querySelectorAll('.insert-placeholder').forEach(btn => {
                btn.addEventListener('click', () => {
                    const token = btn.dataset.token || '';
                    const textarea = document.getElementById('templateBody');
                    if (!textarea) return;
                    const start = textarea.selectionStart || 0;
                    const end = textarea.selectionEnd || 0;
                    const text = textarea.value;
                    textarea.value = text.slice(0, start) + token + text.slice(end);
                    textarea.focus();
                    textarea.selectionStart = textarea.selectionEnd = start + token.length;
                });
            });
        </script>
        <?php
    });
});
