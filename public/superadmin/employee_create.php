<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $errors = [];
    $username = '';
    $role = 'support';
    $permissions = employee_default_permissions($role);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'support';
        $permissions = $_POST['permissions'] ?? [];

        if ($username === '' || $password === '') {
            $errors[] = 'Username and password are required.';
        }
        if (!in_array($role, ['support', 'content', 'approvals', 'auditor'], true)) {
            $errors[] = 'Invalid role selected.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if (!$errors) {
            try {
                $employee = create_employee($username, $password, $role, $permissions);
                set_flash('success', 'Employee created: ' . ($employee['empId'] ?? ''));
                redirect('/superadmin/employees.php');
            } catch (Throwable $e) {
                $errors[] = 'Unable to create employee: ' . $e->getMessage();
            }
        }
    }

    $title = get_app_config()['appName'] . ' | Create Employee';
    $permissionCatalog = employee_permission_catalog();
    render_layout($title, function () use ($errors, $username, $role, $permissions, $permissionCatalog) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Create Yojak Employee'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('Internal staff with RBAC. They cannot access department document contents.'); ?></p>
            <?php if ($errors): ?>
                <div class="flashes">
                    <?php foreach ($errors as $error): ?>
                        <div class="flash error"><?= sanitize($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/superadmin/employee_create.php">
                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                <div class="field">
                    <label for="username"><?= sanitize('Username'); ?></label>
                    <input id="username" name="username" value="<?= sanitize($username); ?>" required>
                </div>
                <div class="field">
                    <label for="password"><?= sanitize('Password'); ?></label>
                    <input id="password" name="password" type="password" required>
                </div>
                <div class="field">
                    <label for="role"><?= sanitize('Role'); ?></label>
                    <select id="role" name="role">
                        <?php foreach (['support','content','approvals','auditor'] as $opt): ?>
                            <option value="<?= sanitize($opt); ?>" <?= $role === $opt ? 'selected' : ''; ?>><?= sanitize(ucfirst($opt)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label><?= sanitize('Permissions'); ?></label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;">
                        <?php foreach ($permissionCatalog as $key => $label): ?>
                            <label style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="permissions[]" value="<?= sanitize($key); ?>" <?= in_array($key, $permissions, true) ? 'checked' : ''; ?>>
                                <span><?= sanitize($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="muted" style="margin-top:6px;"><?= sanitize('Defaults will auto-fill for the selected role if nothing is chosen.'); ?></p>
                </div>
                <div class="buttons">
                    <button class="btn" type="submit"><?= sanitize('Create Employee'); ?></button>
                    <a class="btn secondary" href="/superadmin/employees.php"><?= sanitize('Back'); ?></a>
                </div>
            </form>
        </div>
        <?php
    });
});
