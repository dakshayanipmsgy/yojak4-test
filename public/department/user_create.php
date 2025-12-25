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
    require_department_permission($user, 'manage_users');

    $roles = load_department_roles($deptId);
    $errors = [];
    $data = [
        'userShortId' => '',
        'roleId' => '',
        'displayName' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $data['userShortId'] = strtolower(trim($_POST['userShortId'] ?? ''));
        $data['roleId'] = strtolower(trim($_POST['roleId'] ?? ''));
        $data['displayName'] = trim($_POST['displayName'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[a-z0-9]{3,12}$/', $data['userShortId'])) {
            $errors[] = 'User ID must be 3-12 lowercase letters or numbers.';
        }
        if (!preg_match('/^[a-z0-9_]{3,20}$/', $data['roleId'])) {
            $errors[] = 'Role ID invalid.';
        }
        $role = find_department_role($deptId, $data['roleId']);
        if (!$role) {
            $errors[] = 'Role not found.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($data['displayName'] === '') {
            $errors[] = 'Display name required.';
        }

        $fullUserId = $data['userShortId'] . '.' . $data['roleId'] . '.' . $deptId;
        if (file_exists(department_user_path($deptId, $fullUserId, false))) {
            $errors[] = 'User already exists.';
        }

        if (!$errors) {
            $now = now_kolkata()->format(DateTime::ATOM);
            $record = [
                'type' => 'department',
                'deptId' => $deptId,
                'userShortId' => $data['userShortId'],
                'roleId' => $data['roleId'],
                'fullUserId' => $fullUserId,
                'displayName' => $data['displayName'],
                'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
                'createdAt' => $now,
                'updatedAt' => $now,
                'mustResetPassword' => true,
            ];
            save_department_user($record, false);
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'user_created',
                'meta' => ['userId' => $fullUserId],
            ]);
            set_flash('success', 'User created. Password reset required on first login.');
            redirect('/department/users.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Create Department User';
    render_layout($title, function () use ($errors, $data, $roles) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Create User'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('User ID format: usershort.role.dept'); ?></p>
                </div>
                <a class="btn secondary" href="/department/users.php"><?= sanitize('Back'); ?></a>
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
                    <label for="userShortId"><?= sanitize('User Short ID (3-12 lowercase letters/numbers)'); ?></label>
                    <input id="userShortId" name="userShortId" value="<?= sanitize($data['userShortId']); ?>" required minlength="3" maxlength="12" pattern="[a-z0-9]+">
                </div>
                <div class="field">
                    <label for="roleId"><?= sanitize('Role'); ?></label>
                    <select id="roleId" name="roleId" required>
                        <option value=""><?= sanitize('Select role'); ?></option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= sanitize($role['roleId'] ?? ''); ?>" <?= ($data['roleId'] === ($role['roleId'] ?? '')) ? 'selected' : ''; ?>>
                                <?= sanitize(($role['roleId'] ?? '') . ' - ' . ($role['nameEn'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="displayName"><?= sanitize('Display Name'); ?></label>
                    <input id="displayName" name="displayName" value="<?= sanitize($data['displayName']); ?>" required>
                </div>
                <div class="field">
                    <label for="password"><?= sanitize('Temporary Password (min 8 chars)'); ?></label>
                    <input id="password" type="password" name="password" required minlength="8">
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Create User'); ?></button>
                    <a class="btn secondary" href="/department/users.php"><?= sanitize('Cancel'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
