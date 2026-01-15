<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    redirect('/department/requirement_sets.php');
    return;
    $deptId = $user['deptId'] ?? '';
    ensure_department_env($deptId);
    require_department_permission($user, 'manage_requirements');

    $sets = load_requirement_sets($deptId);
    $tenders = load_department_tenders($deptId);
    $title = get_app_config()['appName'] . ' | Requirements';

    render_layout($title, function () use ($sets, $tenders) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:4px;"><?= sanitize('Requirement Library'); ?></h2>
            <p class="muted" style="margin:0;"><?= sanitize('Reusable sets for tenders.'); ?></p>
            <form method="post" action="/department/requirement_set_create.php" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Set Title'); ?></label>
                    <input id="title" name="title" required>
                </div>
                <div class="field">
                    <label for="items"><?= sanitize('Items (one per line)'); ?></label>
                    <textarea id="items" name="items" rows="3" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"></textarea>
                </div>
                <button class="btn" type="submit"><?= sanitize('Create Set'); ?></button>
            </form>

            <h3 style="margin-top:16px;"><?= sanitize('Existing Sets'); ?></h3>
            <?php if (!$sets): ?>
                <p class="muted"><?= sanitize('No requirement sets.'); ?></p>
            <?php else: ?>
                <?php foreach ($sets as $set): ?>
                    <div class="card" style="margin-top:12px;background:var(--surface-2);">
                        <form method="post" action="/department/requirement_set_update.php" style="display:grid;gap:8px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="setId" value="<?= sanitize($set['setId'] ?? ''); ?>">
                            <div class="field">
                                <label><?= sanitize('Set ID'); ?></label>
                                <div class="pill"><?= sanitize($set['setId'] ?? ''); ?></div>
                            </div>
                            <div class="field">
                                <label for="title-<?= sanitize($set['setId'] ?? ''); ?>"><?= sanitize('Title'); ?></label>
                                <input id="title-<?= sanitize($set['setId'] ?? ''); ?>" name="title" value="<?= sanitize($set['title'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label><?= sanitize('Items'); ?></label>
                                <textarea name="items" rows="3" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"><?= htmlspecialchars(implode(PHP_EOL, $set['items'] ?? []), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="buttons">
                                <button class="btn secondary" type="submit"><?= sanitize('Update'); ?></button>
                            </div>
                        </form>
                        <form method="post" action="/department/requirement_set_attach_to_tender.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px;">
                            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                            <input type="hidden" name="setId" value="<?= sanitize($set['setId'] ?? ''); ?>">
                            <select name="tenderId" required>
                                <option value=""><?= sanitize('Select tender'); ?></option>
                                <?php foreach ($tenders as $tender): ?>
                                    <option value="<?= sanitize($tender['id'] ?? ''); ?>"><?= sanitize(($tender['id'] ?? '') . ' - ' . ($tender['title'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn" type="submit"><?= sanitize('Attach to Tender'); ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    });
});
