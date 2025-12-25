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

    $errors = [];
    $requirementSets = load_requirement_sets($deptId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $title = trim($_POST['title'] ?? '');
        $publish = trim($_POST['publish'] ?? '');
        $submission = trim($_POST['submission'] ?? '');
        $opening = trim($_POST['opening'] ?? '');
        $completionMonths = trim($_POST['completionMonths'] ?? '');
        $paymentSteps = array_filter(array_map('trim', preg_split('/\r?\n/', (string)($_POST['paymentSteps'] ?? '')) ?: []));
        $emdText = trim($_POST['emdText'] ?? '');
        $sdPercent = trim($_POST['sdPercent'] ?? '');
        $pgPercent = trim($_POST['pgPercent'] ?? '');
        $reqIds = $_POST['requirementSetIds'] ?? [];

        if ($title === '') {
            $errors[] = 'Title required.';
        }

        $validReqs = [];
        foreach ($requirementSets as $set) {
            if (in_array($set['setId'] ?? '', $reqIds, true)) {
                $validReqs[] = $set['setId'];
            }
        }

        if (!$errors) {
            $tender = [
                'id' => '', // auto fill
                'title' => $title,
                'tenderNumberFormat' => [
                    'prefix' => trim($_POST['prefix'] ?? 'YTD-'),
                    'sequence' => max(1, (int)($_POST['sequence'] ?? 1)),
                    'postfix' => trim($_POST['postfix'] ?? ''),
                ],
                'dates' => [
                    'publish' => $publish,
                    'submission' => $submission,
                    'opening' => $opening,
                ],
                'completionMonths' => $completionMonths !== '' ? (int)$completionMonths : null,
                'paymentSteps' => array_values($paymentSteps),
                'emdText' => $emdText,
                'sdPercent' => $sdPercent,
                'pgPercent' => $pgPercent,
                'requirementSetIds' => $validReqs,
                'createdAt' => now_kolkata()->format(DateTime::ATOM),
                'updatedAt' => now_kolkata()->format(DateTime::ATOM),
                'status' => 'draft',
            ];
            save_department_tender($deptId, $tender);
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'tender_created',
                'meta' => ['title' => $title],
            ]);
            set_flash('success', 'Tender saved.');
            redirect('/department/tenders.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Create Tender';
    render_layout($title, function () use ($errors, $requirementSets) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Create Tender'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('YTD auto generated with department isolation.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/tenders.php"><?= sanitize('Back'); ?></a>
            </div>
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
                    <label for="title"><?= sanitize('Title'); ?></label>
                    <input id="title" name="title" required>
                </div>
                <div class="field">
                    <label><?= sanitize('Tender Number Format'); ?></label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <input name="prefix" placeholder="Prefix" style="flex:1;min-width:120px;">
                        <input name="sequence" type="number" min="1" value="1" style="flex:1;min-width:120px;">
                        <input name="postfix" placeholder="Postfix" style="flex:1;min-width:120px;">
                    </div>
                </div>
                <div class="field">
                    <label><?= sanitize('Dates'); ?></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;">
                        <input type="date" name="publish" placeholder="Publish date">
                        <input type="datetime-local" name="submission" placeholder="Submission deadline">
                        <input type="datetime-local" name="opening" placeholder="Opening date">
                    </div>
                </div>
                <div class="field">
                    <label for="completionMonths"><?= sanitize('Completion Months'); ?></label>
                    <input id="completionMonths" name="completionMonths" type="number" min="0">
                </div>
                <div class="field">
                    <label for="paymentSteps"><?= sanitize('Payment Steps (one per line)'); ?></label>
                    <textarea id="paymentSteps" name="paymentSteps" rows="3" style="width:100%;background:#0d1117;color:#e6edf3;border:1px solid #30363d;border-radius:10px;padding:10px;"></textarea>
                </div>
                <div class="field">
                    <label for="emdText"><?= sanitize('EMD Text'); ?></label>
                    <input id="emdText" name="emdText">
                </div>
                <div class="field" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:150px;">
                        <label for="sdPercent"><?= sanitize('SD %'); ?></label>
                        <input id="sdPercent" name="sdPercent">
                    </div>
                    <div style="flex:1;min-width:150px;">
                        <label for="pgPercent"><?= sanitize('PG %'); ?></label>
                        <input id="pgPercent" name="pgPercent">
                    </div>
                </div>
                <div class="field">
                    <label><?= sanitize('Attach Requirement Sets'); ?></label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($requirementSets as $set): ?>
                            <label style="display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="requirementSetIds[]" value="<?= sanitize($set['setId'] ?? ''); ?>">
                                <span class="pill"><?= sanitize($set['title'] ?? ''); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Tender'); ?></button>
            </form>
        </div>
        <?php
    });
});
