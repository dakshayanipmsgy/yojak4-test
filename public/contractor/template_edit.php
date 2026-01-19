<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $tplId = trim((string)($_GET['id'] ?? ''));
    if ($tplId === '') {
        render_error_page('Missing template id.');
        return;
    }

    $template = template_load('contractor', $tplId, $yojId);
    if (!$template || (($template['owner']['yojId'] ?? '') !== $yojId)) {
        render_error_page('Template not found.');
        return;
    }

    $groups = template_placeholder_groups($yojId);
    $title = get_app_config()['appName'] . ' | Edit Template';

    render_layout($title, function () use ($template, $groups) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;"><?= sanitize('Edit Template'); ?></h2>
                <p class="muted" style="margin:4px 0 0;"><?= sanitize('Use placeholders to keep templates reusable.'); ?></p>
            </div>
            <form method="post" action="/contractor/template_update.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($template['id'] ?? ''); ?>">
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
                            <button class="btn" type="submit"><?= sanitize('Save Changes'); ?></button>
                            <a class="btn secondary" href="/contractor/template_preview.php?tplId=<?= sanitize($template['id'] ?? ''); ?>" target="_blank" rel="noopener"><?= sanitize('Preview'); ?></a>
                            <a class="btn secondary" href="/contractor/templates.php"><?= sanitize('Back'); ?></a>
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
        <script>
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
