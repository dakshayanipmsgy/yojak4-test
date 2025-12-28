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
    require_department_permission($user, 'manage_requirements');

    $sets = load_requirement_sets($deptId);
    $title = get_app_config()['appName'] . ' | Requirement Sets';

    render_layout($title, function () use ($sets) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 6px 0;"><?= sanitize('Contractor Requirement Sets'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Define official checklists shared with contractors.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/tenders.php"><?= sanitize('Back to Tenders'); ?></a>
            </div>
        </div>

        <div class="card" style="margin-top:12px;display:grid;gap:10px;">
            <h3 style="margin:0;"><?= sanitize('Create Requirement Set'); ?></h3>
            <form method="post" action="/department/requirement_set_save.php" style="display:grid;gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="name-new"><?= sanitize('Name'); ?></label>
                    <input id="name-new" name="name" required>
                </div>
                <div class="field">
                    <label for="description-new"><?= sanitize('Description (optional)'); ?></label>
                    <textarea id="description-new" name="description" rows="2" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"></textarea>
                </div>
                <div class="field" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="visible-new" name="visibleToContractors" checked style="width:auto;">
                    <label for="visible-new" style="margin:0;"><?= sanitize('Visible to contractors'); ?></label>
                </div>
                <div class="field">
                    <label for="items-new"><?= sanitize('Items (one per line, use "|description|category|required" optional segments)'); ?></label>
                    <textarea id="items-new" name="items" rows="3" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"></textarea>
                </div>
                <button class="btn" type="submit"><?= sanitize('Create'); ?></button>
            </form>
        </div>

        <div style="display:grid;gap:12px;margin-top:12px;">
            <?php if (!$sets): ?>
                <div class="card">
                    <p class="muted" style="margin:0;"><?= sanitize('No requirement sets yet.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($sets as $set): ?>
                <div class="card" style="display:grid;gap:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($set['name'] ?? $set['title'] ?? $set['setId']); ?></h3>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize($set['setId'] ?? ''); ?></p>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <span class="pill <?= !empty($set['visibleToContractors']) ? 'success' : ''; ?>"><?= !empty($set['visibleToContractors']) ? sanitize('Visible') : sanitize('Hidden'); ?></span>
                        </div>
                    </div>
                    <form method="post" action="/department/requirement_set_save.php" style="display:grid;gap:10px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="setId" value="<?= sanitize($set['setId'] ?? ''); ?>">
                        <div class="field">
                            <label><?= sanitize('Name'); ?></label>
                            <input name="name" value="<?= sanitize($set['name'] ?? $set['title'] ?? ''); ?>" required>
                        </div>
                        <div class="field">
                            <label><?= sanitize('Description'); ?></label>
                            <textarea name="description" rows="2" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"><?= htmlspecialchars($set['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="field" style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="visibleToContractors" <?= !empty($set['visibleToContractors']) ? 'checked' : ''; ?> style="width:auto;">
                            <span><?= sanitize('Visible to contractors'); ?></span>
                        </div>
                        <div class="field">
                            <label><?= sanitize('Items'); ?></label>
                            <textarea name="items" rows="3" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"><?php
                                $lines = [];
                                foreach ($set['items'] ?? [] as $item) {
                                    $line = ($item['title'] ?? '');
                                    $desc = $item['description'] ?? '';
                                    $category = $item['category'] ?? '';
                                    $required = !empty($item['required']) ? 'required' : 'optional';
                                    $segments = array_filter([$line, $desc, $category, $required], fn($p) => $p !== '');
                                    $lines[] = implode('|', $segments);
                                }
                                echo htmlspecialchars(implode(PHP_EOL, $lines), ENT_QUOTES, 'UTF-8');
                            ?></textarea>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize('Format: Title|Description|Category|required/optional'); ?></p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <button class="btn secondary" type="submit"><?= sanitize('Save'); ?></button>
                        </div>
                    </form>
                    <form method="post" action="/department/requirement_set_delete.php" onsubmit="return confirm('Delete this set?');" style="margin-top:6px;">
                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                        <input type="hidden" name="setId" value="<?= sanitize($set['setId'] ?? ''); ?>">
                        <button class="btn" type="submit"><?= sanitize('Delete'); ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
