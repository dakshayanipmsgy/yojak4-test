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
    require_department_permission($user, 'manage_tenders');

    $ytdId = trim($_GET['ytdId'] ?? ($_POST['ytdId'] ?? ''));
    if ($ytdId === '') {
        render_error_page('Missing tender id.');
        return;
    }

    $tender = load_department_tender($deptId, $ytdId);
    if (!$tender) {
        render_error_page('Tender not found.');
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $setId = trim($_POST['setId'] ?? '');
        $sets = load_requirement_sets($deptId);
        $valid = null;
        foreach ($sets as $set) {
            if (($set['setId'] ?? '') === $setId) {
                $valid = $setId;
                break;
            }
        }
        $tender['requirementSetId'] = $valid;
        $tender['updatedAt'] = now_kolkata()->format(DateTime::ATOM);
        save_department_tender($deptId, $tender);
        set_flash('success', 'Requirement set saved for tender.');
        redirect('/department/tender_requirements.php?ytdId=' . urlencode($ytdId));
        return;
    }

    $sets = load_requirement_sets($deptId);
    $title = get_app_config()['appName'] . ' | Tender Requirements';

    render_layout($title, function () use ($tender, $sets) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 4px 0;"><?= sanitize($tender['title'] ?? 'Tender'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize($tender['id'] ?? ''); ?></p>
                </div>
                <a class="btn secondary" href="/department/tender_view.php?id=<?= urlencode($tender['id'] ?? ''); ?>"><?= sanitize('Back to Tender'); ?></a>
            </div>
            <form method="post" style="display:grid;gap:10px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="ytdId" value="<?= sanitize($tender['id'] ?? ''); ?>">
                <div class="field">
                    <label for="setId"><?= sanitize('Select Requirement Set'); ?></label>
                    <select id="setId" name="setId">
                        <option value=""><?= sanitize('None'); ?></option>
                        <?php foreach ($sets as $set): ?>
                            <option value="<?= sanitize($set['setId'] ?? ''); ?>" <?= ($tender['requirementSetId'] ?? '') === ($set['setId'] ?? '') ? 'selected' : ''; ?>>
                                <?= sanitize(($set['name'] ?? $set['title'] ?? '') ?: ($set['setId'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Contractors will see and optionally auto-apply this checklist.'); ?></p>
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Requirement Set'); ?></button>
            </form>
        </div>

        <?php if ($tender['requirementSetId'] ?? null): ?>
            <?php
            $active = null;
            foreach ($sets as $set) {
                if (($set['setId'] ?? '') === ($tender['requirementSetId'] ?? '')) {
                    $active = $set;
                    break;
                }
            }
            ?>
            <?php if ($active): ?>
                <div class="card" style="margin-top:12px;">
                    <h3 style="margin:0 0 8px 0;"><?= sanitize('Preview: ' . (($active['name'] ?? $active['title'] ?? '') ?: $active['setId'])); ?></h3>
                    <div style="display:grid;gap:8px;">
                        <?php foreach ($active['items'] ?? [] as $item): ?>
                            <div class="pill" style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                <span><?= sanitize($item['title'] ?? ''); ?></span>
                                <span class="muted"><?= sanitize(!empty($item['required']) ? 'Required' : 'Optional'); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($active['items'])): ?>
                            <p class="muted" style="margin:0;"><?= sanitize('No items yet.'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    });
});
