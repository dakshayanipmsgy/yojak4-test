<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $deptId = normalize_dept_id($_GET['deptId'] ?? '');
    if (!is_valid_dept_id($deptId) || !department_exists($deptId)) {
        render_error_page('Department not found.');
        return;
    }

    $department = load_department($deptId);
    if (!$department) {
        render_error_page('Department not found.');
        return;
    }

    $errors = [];
    $data = [
        'adminShortId' => '',
        'displayName' => '',
        'password' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $data['adminShortId'] = strtolower(trim($_POST['adminShortId'] ?? ''));
        $data['displayName'] = trim($_POST['displayName'] ?? '');
        $data['password'] = $_POST['password'] ?? '';

        if (!is_valid_admin_short_id($data['adminShortId'])) {
            $errors[] = 'Admin ID must be 3-12 lowercase letters or numbers.';
        }
        if (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($data['displayName'] === '') {
            $errors[] = 'Display name is required.';
        }

        if (!$errors) {
            $userRecord = create_department_admin($deptId, $data['adminShortId'], $data['password'], $data['displayName']);
            set_flash('success', 'Department admin saved. Admin must reset password on first login.');
            redirect('/superadmin/department_view.php?deptId=' . urlencode($deptId));
        }
    }

    $title = get_app_config()['appName'] . ' | Department Admin';
    render_layout($title, function () use ($department, $errors, $data) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Department Admin for ' . $department['deptId']); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Create or replace the active department admin.'); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/department_view.php?deptId=<?= urlencode($department['deptId']); ?>"><?= sanitize('Back'); ?></a>
            </div>
            <?php if ($errors): ?>
                <div class="flashes" style="margin-top:10px;">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/superadmin/department_admin_create.php?deptId=<?= urlencode($department['deptId']); ?>" style="margin-top:12px;">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="adminShortId"><?= sanitize('Admin Short ID (3-12 lowercase letters/numbers)'); ?></label>
                    <input id="adminShortId" name="adminShortId" value="<?= sanitize($data['adminShortId']); ?>" required minlength="3" maxlength="12" pattern="[a-z0-9]+">
                </div>
                <div class="field">
                    <label for="displayName"><?= sanitize('Display Name'); ?></label>
                    <input id="displayName" name="displayName" value="<?= sanitize($data['displayName']); ?>" required>
                </div>
                <div class="field">
                    <label for="password"><?= sanitize('Temporary Password (min 8 chars)'); ?></label>
                    <input id="password" name="password" type="password" required minlength="8">
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Save Admin'); ?></button>
                    <a class="btn secondary" href="/superadmin/department_view.php?deptId=<?= urlencode($department['deptId']); ?>"><?= sanitize('Cancel'); ?></a>
                </div>
            </form>
            <?php if (!empty($department['activeAdminUserId'])): ?>
                <p class="muted" style="margin-top:12px;"><?= sanitize('Current admin will be archived automatically.'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    });
});
