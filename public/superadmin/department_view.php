<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }

    $deptId = normalize_dept_id($_GET['deptId'] ?? '');
    if (!is_valid_dept_id($deptId)) {
        render_error_page('Department not found.');
        return;
    }

    $department = load_department($deptId);
    if (!$department) {
        render_error_page('Department not found.');
        return;
    }
    $activeAdmin = $department['activeAdminUserId'] ? load_active_department_user($department['activeAdminUserId']) : null;

    $title = get_app_config()['appName'] . ' | Department ' . $deptId;
    render_layout($title, function () use ($department, $activeAdmin) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize('Department: ' . $department['deptId']); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Superadmin can view metadata only. Documents/templates remain hidden.'); ?></p>
                </div>
                <a class="btn" href="/superadmin/departments.php"><?= sanitize('Back to list'); ?></a>
            </div>
            <div class="pill" style="margin-top:12px;"><?= sanitize('Names'); ?>: <?= sanitize($department['nameEn']); ?> / <?= sanitize($department['nameHi']); ?></div>
            <?php if (!empty($department['address'])): ?>
                <div class="pill" style="margin-top:8px;"><?= sanitize('Address'); ?>: <?= sanitize($department['address']); ?></div>
            <?php endif; ?>
            <?php if (!empty($department['contactEmail'])): ?>
                <div class="pill" style="margin-top:8px;"><?= sanitize('Email'); ?>: <?= sanitize($department['contactEmail']); ?></div>
            <?php endif; ?>
            <div style="margin-top:12px;display:flex;gap:12px;flex-wrap:wrap;">
                <div class="tag <?= ($department['status'] ?? '') === 'active' ? 'success' : ''; ?>"><?= sanitize(ucfirst($department['status'] ?? 'active')); ?></div>
                <div class="tag"><?= sanitize('Created ' . (new DateTime($department['createdAt']))->format('d M Y')); ?></div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin-bottom:4px;"><?= sanitize('Department Admin'); ?></h3>
                    <p class="muted" style="margin:0;"><?= sanitize('Only one active admin. Previous admins are archived.'); ?></p>
                </div>
                <a class="btn" href="/superadmin/department_admin_create.php?deptId=<?= urlencode($department['deptId']); ?>"><?= sanitize($activeAdmin ? 'Replace admin' : 'Create admin'); ?></a>
            </div>
            <?php if ($activeAdmin): ?>
                <div class="pill" style="margin-top:10px;"><?= sanitize('Active Admin ID'); ?>: <?= sanitize($activeAdmin['fullUserId']); ?></div>
                <div class="pill" style="margin-top:8px;"><?= sanitize('Display Name'); ?>: <?= sanitize($activeAdmin['displayName']); ?></div>
                <div class="pill" style="margin-top:8px;"><?= sanitize('Status'); ?>: <?= sanitize(ucfirst($activeAdmin['status'])); ?></div>
            <?php else: ?>
                <p class="muted" style="margin-top:10px;"><?= sanitize('No admin assigned yet.'); ?></p>
            <?php endif; ?>
            <?php if (!empty($department['adminHistory'])): ?>
                <div style="margin-top:14px;">
                    <div class="muted" style="margin-bottom:6px;"><?= sanitize('Archived admin IDs:'); ?></div>
                    <div class="pill"><?= sanitize(implode(', ', $department['adminHistory'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
