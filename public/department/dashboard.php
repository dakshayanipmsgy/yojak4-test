<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $role = find_department_role($user['deptId'] ?? '', $user['roleId'] ?? '');
    $title = get_app_config()['appName'] . ' | Department Dashboard';
    render_layout($title, function () use ($user, $role) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:6px;"><?= sanitize('Department Dashboard'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize('Welcome back, ' . ($user['displayName'] ?? $user['username'] ?? '')); ?></p>
                </div>
                <div class="pill" style="background:#eef2ff;color:#1f2a44;font-weight:600;">
                    <?= sanitize('Role: ' . ($role['roleId'] ?? ($user['roleId'] ?? 'Unknown'))); ?>
                </div>
            </div>
            <div style="display:grid;gap:12px;margin-top:12px;">
                <div class="pill" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <span><?= sanitize('Department: ' . ($user['deptId'] ?? '')); ?></span>
                    <span style="opacity:0.7;">•</span>
                    <span><?= sanitize('User ID: ' . ($user['username'] ?? '')); ?></span>
                </div>
                <div class="pill secondary" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <span><?= sanitize('Last login: ' . (($user['lastLoginAt'] ?? '') !== '' ? ($user['lastLoginAt']) : 'First login')); ?></span>
                    <?php if (!empty($role['nameEn'])): ?>
                        <span style="opacity:0.7;">•</span>
                        <span><?= sanitize('Role Name: ' . ($role['nameEn'] ?? '')); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="muted" style="margin-top:14px;"><?= sanitize('Use the navigation to manage roles, users, tenders, and documents based on your permissions.'); ?></p>
        </div>
        <?php
    });
});
