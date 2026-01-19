<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_staff_actor();
    $templateId = trim((string)($_GET['id'] ?? ''));
    if ($templateId === '') {
        render_error_page('Template not found.');
        return;
    }
    $template = load_template_record_by_scope('global', $templateId);
    if (!$template) {
        render_error_page('Template not found.');
        return;
    }

    $contractor = [];
    $placeholders = template_placeholder_groups($contractor, '', null);

    $title = get_app_config()['appName'] . ' | Edit Global Template';
    render_layout($title, function () use ($template, $placeholders) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Edit Global Template</h2>
                <p class="muted" style="margin:4px 0 0;">Guided editor with advanced JSON.</p>
            </div>
            <form method="post" action="/superadmin/template_update.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($template['templateId'] ?? ''); ?>">
                <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                    <label>Title
                        <input name="title" required minlength="3" maxlength="80" value="<?= sanitize($template['title'] ?? ''); ?>">
                    </label>
                    <label>Category
                        <select name="category">
                            <?php foreach (['Affidavit','Letter','Declaration','Form','Other'] as $category): ?>
                                <option value="<?= sanitize($category); ?>" <?= ($template['category'] ?? '') === $category ? 'selected' : ''; ?>><?= sanitize($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Description
                    <input name="description" maxlength="180" value="<?= sanitize($template['description'] ?? ''); ?>">
                </label>
                <div class="template-editor" style="display:grid;gap:12px;grid-template-columns:minmax(0,2fr) minmax(240px,1fr);align-items:start;">
                    <div>
                        <label>Template Body
                            <textarea id="templateBody" name="bodyHtml" rows="16" required><?= sanitize($template['bodyHtml'] ?? ''); ?></textarea>
                        </label>
                        <p class="muted" style="margin:4px 0 0;">Unresolved placeholders print as blanks.</p>
                    </div>
                    <aside class="card" style="background:var(--surface-2);border:1px solid var(--border);display:grid;gap:10px;">
                        <div style="font-weight:600;">Available Fields</div>
                        <?php foreach ($placeholders as $group => $items): ?>
                            <div>
                                <div class="muted" style="font-size:12px;margin-bottom:6px;"><?= sanitize($group); ?></div>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <?php foreach ($items as $item): ?>
                                        <button type="button" class="tag insert-token" data-token="<?= sanitize($item['token']); ?>" title="<?= sanitize($item['key']); ?>">
                                            <?= sanitize($item['label']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </aside>
                </div>
                <details>
                    <summary>Advanced JSON (staff only)</summary>
                    <p class="muted">Paste validated JSON. Forbidden pricing placeholders are blocked.</p>
                    <textarea name="advanced_json" rows="10" style="width:100%;"><?= sanitize(json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>
                    <label class="pill" style="display:inline-flex;gap:6px;align-items:center;margin-top:6px;">
                        <input type="checkbox" name="apply_json" value="1"> Apply Advanced JSON
                    </label>
                </details>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Update Template</button>
                    <a class="btn secondary" href="/superadmin/templates.php">Cancel</a>
                </div>
            </form>
            <form method="post" action="/superadmin/template_delete.php" onsubmit="return confirm('Delete this global template?');">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($template['templateId'] ?? ''); ?>">
                <button class="btn secondary" type="submit">Delete Template</button>
            </form>
        </div>
        <script>
            (function () {
                const textarea = document.getElementById('templateBody');
                const buttons = document.querySelectorAll('.insert-token');
                buttons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const token = btn.getAttribute('data-token');
                        if (!token || !textarea) return;
                        const start = textarea.selectionStart || 0;
                        const end = textarea.selectionEnd || 0;
                        const value = textarea.value || '';
                        textarea.value = value.substring(0, start) + token + value.substring(end);
                        const nextPos = start + token.length;
                        textarea.selectionStart = textarea.selectionEnd = nextPos;
                        textarea.focus();
                    });
                });
            })();
        </script>
        <style>
            @media (max-width: 900px) {
                .template-editor { grid-template-columns: 1fr !important; }
            }
            .insert-token { cursor: pointer; border: 1px solid transparent; }
            .insert-token:hover { border-color: var(--border); }
        </style>
        <?php
    });
});
