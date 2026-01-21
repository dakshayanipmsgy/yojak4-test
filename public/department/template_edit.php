<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_templates');

    $templateId = trim($_GET['id'] ?? '');
    if ($templateId === '') {
        render_error_page('Template not found.');
        return;
    }

    $path = department_template_path($deptId, $templateId);
    if (!file_exists($path)) {
        render_error_page('Template not found.');
        return;
    }

    $template = readJson($path);
    if (!$template) {
        render_error_page('Template invalid.');
        return;
    }
    $migrationStats = [];
    $templateBody = (string)($template['bodyHtml'] ?? '');
    $migratedBody = migrate_placeholders_to_canonical($templateBody, $migrationStats);
    $template['bodyHtml'] = $migratedBody;
    $wasMigrated = $migratedBody !== $templateBody;

    $errors = [];
    $placeholderOptions = department_template_placeholders();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $template['title'] = trim($_POST['title'] ?? '');
        $template['bodyHtml'] = trim($_POST['bodyHtml'] ?? '');
        $template['placeholders'] = $_POST['placeholders'] ?? [];

        if ($template['title'] === '') {
            $errors[] = 'Title required.';
        }
        if ($template['bodyHtml'] === '') {
            $errors[] = 'Body required.';
        }

        if (!$errors) {
            $stats = [];
            $template['bodyHtml'] = migrate_placeholders_to_canonical($template['bodyHtml'], $stats);
            $validation = validate_placeholders($template['bodyHtml'], placeholder_registry());
            if (!empty($validation['invalidTokens'])) {
                $errors[] = 'Invalid placeholders: ' . implode(', ', $validation['invalidTokens']);
            }
            if (!empty($validation['unknownKeys'])) {
                $errors[] = 'Unknown fields: ' . implode(', ', $validation['unknownKeys']);
            }
        }

        if (!$errors) {
            $template['placeholders'] = validate_template_placeholders(is_array($template['placeholders']) ? $template['placeholders'] : []);
            save_department_template($deptId, $template);
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'template_updated',
                'meta' => ['templateId' => $templateId],
            ]);
            set_flash('success', 'Template updated.');
            redirect('/department/templates.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Edit Template';
    render_layout($title, function () use ($template, $errors, $placeholderOptions, $wasMigrated) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Edit Template'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Update template content.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/templates.php"><?= sanitize('Back'); ?></a>
        </div>
        <?php if ($wasMigrated): ?>
            <div class="card" style="margin-top:12px;border-color:#fbbf24;background:#fff8e1;">
                <strong>Template upgraded:</strong> Placeholder syntax has been migrated to the canonical format. Review and save to keep the changes.
            </div>
        <?php endif; ?>
        <?php if ($errors): ?>
            <div class="flashes" style="margin-top:12px;">
                <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label><?= sanitize('Template ID'); ?></label>
                    <div class="pill"><?= sanitize($template['templateId'] ?? ''); ?></div>
                </div>
                <div style="display:grid; gap:12px; grid-template-columns:minmax(0,2fr) minmax(0,1fr);">
                    <div>
                        <div class="field">
                            <label for="title"><?= sanitize('Title'); ?></label>
                            <input id="title" name="title" value="<?= sanitize($template['title'] ?? ''); ?>" required>
                        </div>
                        <div class="field">
                            <label for="bodyHtml"><?= sanitize('Body'); ?></label>
                            <textarea id="bodyHtml" name="bodyHtml" rows="8" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:12px;"><?= htmlspecialchars($template['bodyHtml'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                    <div class="card" style="padding:12px;">
                        <strong>Insert Field</strong>
                        <p class="muted">Click a field to insert placeholder.</p>
                        <input type="search" id="field-search" placeholder="Search fields..." style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);margin-bottom:8px;">
                        <div style="display:flex; flex-wrap:wrap; gap:6px;">
                            <?php foreach ($placeholderOptions as $ph): ?>
                                <button type="button" class="btn secondary field-btn" data-token="<?= sanitize($ph); ?>">
                                    <?= sanitize($ph); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label><?= sanitize('Placeholders'); ?></label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($placeholderOptions as $ph): ?>
                            <label style="display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="placeholders[]" value="<?= sanitize($ph); ?>" <?= in_array($ph, $template['placeholders'] ?? [], true) ? 'checked' : ''; ?>>
                                <span class="pill"><?= sanitize($ph); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Update'); ?></button>
                    <a class="btn secondary" href="/department/templates.php"><?= sanitize('Cancel'); ?></a>
                </div>
            </form>
        </div>
        <script>
            const fieldButtons = document.querySelectorAll('.field-btn');
            const bodyEl = document.getElementById('bodyHtml');
            const searchInput = document.getElementById('field-search');
            fieldButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!bodyEl) return;
                    const token = btn.dataset.token || '';
                    const start = bodyEl.selectionStart || 0;
                    const end = bodyEl.selectionEnd || 0;
                    const text = bodyEl.value || '';
                    bodyEl.value = text.slice(0, start) + token + text.slice(end);
                    const pos = start + token.length;
                    bodyEl.focus();
                    bodyEl.setSelectionRange(pos, pos);
                });
            });
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    const term = (searchInput.value || '').toLowerCase();
                    fieldButtons.forEach(btn => {
                        const text = (btn.dataset.token || '').toLowerCase();
                        btn.style.display = term === '' || text.includes(term) ? '' : 'none';
                    });
                });
            }
        </script>
        <?php
    });
});
