<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $employees = [];
    foreach (staff_employee_index() as $entry) {
        $record = load_employee($entry['empId'] ?? '');
        if ($record) {
            $employees[] = $record;
        }
    }

    $title = get_app_config()['appName'] . ' | Employees';
    render_layout($title, function () use ($employees) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Yojak Employees'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Superadmin-created internal employees with strict RBAC.'); ?></p>
                </div>
                <a class="btn" href="/superadmin/employee_create.php"><?= sanitize('Create Employee'); ?></a>
            </div>
            <div class="pill" style="margin-top:10px;"><?= sanitize('Employees cannot access department document or template contents.'); ?></div>
            <table>
                <thead>
                    <tr>
                        <th><?= sanitize('Employee'); ?></th>
                        <th><?= sanitize('Role'); ?></th>
                        <th><?= sanitize('Permissions'); ?></th>
                        <th><?= sanitize('Status'); ?></th>
                        <th><?= sanitize('Last Login'); ?></th>
                        <th><?= sanitize('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$employees): ?>
                        <tr><td colspan="6" class="muted"><?= sanitize('No employees yet.'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td>
                                    <div><?= sanitize($emp['username'] ?? ''); ?></div>
                                    <div class="muted"><?= sanitize($emp['empId'] ?? ''); ?></div>
                                    <div class="muted"><?= sanitize('Created: ' . (($emp['createdAt'] ?? '') ? (new DateTime($emp['createdAt']))->format('d M Y H:i') : '')); ?></div>
                                </td>
                                <td><span class="tag"><?= sanitize(ucfirst($emp['role'] ?? '')); ?></span></td>
                                <td>
                                    <?php foreach (($emp['permissions'] ?? []) as $perm): ?>
                                        <span class="tag"><?= sanitize($perm); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><span class="tag <?= ($emp['status'] ?? '') === 'active' ? 'success' : ''; ?>"><?= sanitize(ucfirst($emp['status'] ?? '')); ?></span></td>
                                <td><?= sanitize($emp['lastLoginAt'] ? (new DateTime($emp['lastLoginAt']))->format('d M Y H:i') : 'Never'); ?></td>
                                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <form method="post" action="/superadmin/employee_update.php">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="empId" value="<?= sanitize($emp['empId'] ?? ''); ?>">
                                        <input type="hidden" name="action" value="<?= ($emp['status'] ?? '') === 'active' ? 'suspend' : 'activate'; ?>">
                                        <button class="btn <?= ($emp['status'] ?? '') === 'active' ? 'secondary' : ''; ?>" type="submit">
                                            <?= sanitize(($emp['status'] ?? '') === 'active' ? 'Suspend' : 'Activate'); ?>
                                        </button>
                                    </form>
                                    <form method="post" action="/superadmin/employee_update.php" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                        <input type="hidden" name="empId" value="<?= sanitize($emp['empId'] ?? ''); ?>">
                                        <input type="hidden" name="action" value="change_role">
                                        <select name="role" style="min-width:130px;">
                                            <?php foreach (['support','content','approvals','auditor'] as $role): ?>
                                                <option value="<?= sanitize($role); ?>" <?= ($emp['role'] ?? '') === $role ? 'selected' : ''; ?>><?= sanitize(ucfirst($role)); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn secondary" type="submit"><?= sanitize('Update Role'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    });
});
