<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    $contractor = load_contractor($yojId) ?? [];
    $placeholders = template_placeholder_groups($contractor, $yojId, null);

    $title = get_app_config()['appName'] . ' | Create Template';
    render_layout($title, function () use ($placeholders) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Create Template</h2>
                <p class="muted" style="margin:4px 0 0;">Use placeholders from the right panel—click to insert.</p>
            </div>
            <form method="post" action="/contractor/template_create.php" style="display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
                    <label>Title
                        <input name="title" required minlength="3" maxlength="80" placeholder="e.g., Affidavit for Non-Blacklisting">
                    </label>
                    <label>Category
                        <select name="category">
                            <?php foreach (['Affidavit','Letter','Declaration','Form','Other'] as $category): ?>
                                <option value="<?= sanitize($category); ?>"><?= sanitize($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>Description
                    <input name="description" maxlength="180" placeholder="Short note about when to use it">
                </label>
                <div class="template-editor" style="display:grid;gap:12px;grid-template-columns:minmax(0,2fr) minmax(240px,1fr);align-items:start;">
                    <div>
                        <label>Template Body
                            <textarea id="templateBody" name="bodyHtml" rows="16" required placeholder="Write the template body here. Use the placeholders from the right."></textarea>
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
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save Template</button>
                    <a class="btn secondary" href="/contractor/templates.php?tab=mine">Cancel</a>
                </div>
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
