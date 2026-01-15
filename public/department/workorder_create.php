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
    require_department_permission($user, 'manage_workorders');

    $errors = [];
    $tenders = load_department_tenders($deptId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $title = trim($_POST['title'] ?? '');
        $tenderId = trim($_POST['tenderId'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            $errors[] = 'Title required.';
        }
        if ($tenderId !== '' && !load_department_tender($deptId, $tenderId)) {
            $errors[] = 'Tender not found.';
        }

        if (!$errors) {
            $workorder = [
                'woId' => '',
                'deptId' => $deptId,
                'tenderId' => $tenderId,
                'title' => $title,
                'description' => $description,
                'status' => 'active',
                'createdAt' => now_kolkata()->format(DateTime::ATOM),
                'updatedAt' => now_kolkata()->format(DateTime::ATOM),
            ];
            save_department_workorder($deptId, $workorder);
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'workorder_created',
                'meta' => ['title' => $title],
            ]);
            set_flash('success', 'Workorder saved.');
            redirect('/department/workorders.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Create Workorder';
    render_layout($title, function () use ($errors, $tenders) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Create Workorder'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Link to tender if applicable.'); ?></p>
                </div>
                <a class="btn secondary" href="/department/workorders.php"><?= sanitize('Back'); ?></a>
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
                    <label for="tenderId"><?= sanitize('Tender'); ?></label>
                    <select id="tenderId" name="tenderId">
                        <option value=""><?= sanitize('None'); ?></option>
                        <?php foreach ($tenders as $tender): ?>
                            <option value="<?= sanitize($tender['id'] ?? ''); ?>"><?= sanitize(($tender['id'] ?? '') . ' - ' . ($tender['title'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="description"><?= sanitize('Description'); ?></label>
                    <textarea id="description" name="description" rows="3" style="width:100%;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:10px;padding:10px;"></textarea>
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Workorder'); ?></button>
            </form>
        </div>
        <?php
    });
});
