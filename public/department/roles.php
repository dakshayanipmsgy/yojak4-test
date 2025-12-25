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
    require_department_permission($user, 'manage_roles');

    $errors = [];
    $roles = load_department_roles($deptId);
    $permissionOptions = department_permission_keys();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $roleId = strtolower(trim($_POST['roleId'] ?? ''));
        $nameEn = trim($_POST['nameEn'] ?? '');
        $nameHi = trim($_POST['nameHi'] ?? '');
        $permissions = $_POST['permissions'] ?? [];

        if (!preg_match('/^[a-z0-9_]{3,20}$/', $roleId)) {
            $errors[] = 'Role ID must be 3-20 lowercase letters, numbers, or underscore.';
        }
        if ($nameEn === '') {
            $errors[] = 'Role name (English) is required.';
        }

        $cleanPerms = sanitize_permissions(is_array($permissions) ? $permissions : []);
        if (!$cleanPerms) {
            $errors[] = 'Select at least one permission.';
        }

        if (!$errors) {
            $now = now_kolkata()->format(DateTime::ATOM);
            $updated = false;
            foreach ($roles as &$role) {
                if (($role['roleId'] ?? '') === $roleId) {
                    $role['nameEn'] = $nameEn;
                    $role['nameHi'] = $nameHi;
                    $role['permissions'] = $roleId === 'admin' ? ['*'] : $cleanPerms;
                    $role['updatedAt'] = $now;
                    $updated = true;
                    break;
                }
            }
            unset($role);
            if (!$updated) {
                $roles[] = [
                    'roleId' => $roleId,
                    'nameEn' => $nameEn,
                    'nameHi' => $nameHi,
                    'permissions' => $roleId === 'admin' ? ['*'] : $cleanPerms,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ];
            }
            save_department_roles($deptId, $roles);
            append_department_audit($deptId, [
                'by' => $user['username'] ?? '',
                'action' => 'role_saved',
                'meta' => ['roleId' => $roleId],
            ]);
            set_flash('success', 'Role saved.');
            redirect('/department/roles.php');
        }
    }

    $title = get_app_config()['appName'] . ' | Department Roles';
    render_layout($title, function () use ($roles, $errors, $permissionOptions) {
        ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Roles'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Manage department RBAC. Admin role always retains full access.'); ?></p>
                </div>
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
                    <label for="roleId"><?= sanitize('Role ID (lowercase, unique)'); ?></label>
                    <input id="roleId" name="roleId" required minlength="3" maxlength="20" pattern="[a-z0-9_]+">
                </div>
                <div class="field">
                    <label for="nameEn"><?= sanitize('Name (English)'); ?></label>
                    <input id="nameEn" name="nameEn" required>
                </div>
                <div class="field">
                    <label for="nameHi"><?= sanitize('Name (Hindi)'); ?></label>
                    <input id="nameHi" name="nameHi">
                </div>
                <div class="field">
                    <label><?= sanitize('Permissions'); ?></label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <?php foreach ($permissionOptions as $perm): ?>
                            <label style="display:flex;align-items:center;gap:6px;">
                                <input type="checkbox" name="permissions[]" value="<?= sanitize($perm); ?>">
                                <span class="pill"><?= sanitize($perm); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="btn" type="submit"><?= sanitize('Save Role'); ?></button>
            </form>
            <table style="margin-top:16px;">
                <thead>
                    <tr>
                        <th><?= sanitize('Role'); ?></th>
                        <th><?= sanitize('Permissions'); ?></th>
                        <th><?= sanitize('Updated'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$roles): ?>
                        <tr><td colspan="3" class="muted"><?= sanitize('No roles yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <div><?= sanitize($role['roleId'] ?? ''); ?></div>
                                    <div class="muted"><?= sanitize($role['nameEn'] ?? ''); ?></div>
                                </td>
                                <td>
                                    <?php foreach (($role['permissions'] ?? []) as $perm): ?>
                                        <span class="tag"><?= sanitize($perm); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= sanitize(isset($role['updatedAt']) ? (new DateTime($role['updatedAt']))->format('d M Y H:i') : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
