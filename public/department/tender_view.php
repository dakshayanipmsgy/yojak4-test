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

    $tenderId = trim($_GET['id'] ?? '');
    if ($tenderId === '') {
        render_error_page('Tender not found.');
        return;
    }
    $tender = load_department_tender($deptId, $tenderId);
    if (!$tender) {
        render_error_page('Tender not found.');
        return;
    }
    $requirementSets = load_requirement_sets($deptId);
    $title = get_app_config()['appName'] . ' | ' . ($tender['id'] ?? 'Tender');

    render_layout($title, function () use ($tender, $requirementSets) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize($tender['title'] ?? 'Tender'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize($tender['id'] ?? ''); ?></p>
                </div>
                <a class="btn secondary" href="/department/tenders.php"><?= sanitize('Back'); ?></a>
            </div>
            <form method="post" action="/department/tender_update.php" style="margin-top:12px;display:grid;gap:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?= sanitize($tender['id'] ?? ''); ?>">
                <div class="field">
                    <label for="title"><?= sanitize('Title'); ?></label>
                    <input id="title" name="title" value="<?= sanitize($tender['title'] ?? ''); ?>" required>
                </div>
                <div class="field">
                    <label><?= sanitize('Tender Number Format'); ?></label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input name="prefix" value="<?= sanitize($tender['tenderNumberFormat']['prefix'] ?? ''); ?>" placeholder="Prefix" style="flex:1;min-width:120px;">
                        <input name="sequence" type="number" min="1" value="<?= sanitize((string)($tender['tenderNumberFormat']['sequence'] ?? 1)); ?>" style="flex:1;min-width:120px;">
                        <input name="postfix" value="<?= sanitize($tender['tenderNumberFormat']['postfix'] ?? ''); ?>" placeholder="Postfix" style="flex:1;min-width:120px;">
                    </div>
                </div>
                <div class="field">
                    <label><?= sanitize('Dates'); ?></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;">
                        <input type="date" name="publish" value="<?= sanitize($tender['dates']['publish'] ?? ''); ?>">
                        <input type="datetime-local" name="submission" value="<?= sanitize($tender['dates']['submission'] ?? ''); ?>">
                        <input type="datetime-local" name="opening" value="<?= sanitize($tender['dates']['opening'] ?? ''); ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="completionMonths"><?= sanitize('Completion Months'); ?></label>
                    <input id="completionMonths" name="completionMonths" type="number" min="0" value="<?= sanitize((string)($tender['completionMonths'] ?? '')); ?>">
                </div>
                <div class="field">
                    <label for="paymentSteps"><?= sanitize('Payment Steps'); ?></label>
                    <textarea id="paymentSteps" name="paymentSteps" rows="3" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"><?= htmlspecialchars(implode(PHP_EOL, $tender['paymentSteps'] ?? []), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="field">
                    <label for="emdText"><?= sanitize('EMD Text'); ?></label>
                    <input id="emdText" name="emdText" value="<?= sanitize($tender['emdText'] ?? ''); ?>">
                </div>
                <div class="field" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:150px;">
                        <label for="sdPercent"><?= sanitize('SD %'); ?></label>
                        <input id="sdPercent" name="sdPercent" value="<?= sanitize($tender['sdPercent'] ?? ''); ?>">
                    </div>
                    <div style="flex:1;min-width:150px;">
                        <label for="pgPercent"><?= sanitize('PG %'); ?></label>
                        <input id="pgPercent" name="pgPercent" value="<?= sanitize($tender['pgPercent'] ?? ''); ?>">
                    </div>
                </div>
                <div class="field" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" id="publishedToContractors" name="publishedToContractors" style="width:auto;" <?= !empty($tender['publishedToContractors']) ? 'checked' : ''; ?>>
                    <label for="publishedToContractors" style="margin:0;"><?= sanitize('Publish to linked contractors'); ?></label>
                    <?php if (!empty($tender['publishedAt'])): ?>
                        <span class="pill"><?= sanitize('Published at ' . ($tender['publishedAt'] ?? '')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label><?= sanitize('Requirement Sets'); ?></label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($requirementSets as $set): ?>
                            <label style="display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="requirementSetIds[]" value="<?= sanitize($set['setId'] ?? ''); ?>" <?= in_array($set['setId'] ?? '', $tender['requirementSetIds'] ?? [], true) ? 'checked' : ''; ?>>
                                <span class="pill"><?= sanitize($set['title'] ?? ''); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Update Tender'); ?></button>
                    <a class="btn secondary" href="/department/tenders.php"><?= sanitize('Close'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
