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

    $woId = trim($_GET['id'] ?? '');
    if ($woId === '') {
        render_error_page('Workorder not found.');
        return;
    }
    $workorder = load_department_workorder($deptId, $woId);
    if (!$workorder) {
        render_error_page('Workorder not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($workorder['woId'] ?? 'Workorder');
    render_layout($title, function () use ($workorder) {
        ?>
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-bottom:4px;"><?= sanitize($workorder['title'] ?? 'Workorder'); ?></h2>
                    <p class="muted" style="margin:0;"><?= sanitize($workorder['woId'] ?? ''); ?></p>
                </div>
                <a class="btn secondary" href="/department/workorders.php"><?= sanitize('Back'); ?></a>
            </div>
            <div style="margin-top:12px;display:grid;gap:8px;">
                <div class="pill"><?= sanitize('Tender: ' . ($workorder['tenderId'] ?? 'N/A')); ?></div>
                <?php if (!empty($workorder['description'])): ?>
                    <div class="card" style="background:#0f1625;">
                        <strong><?= sanitize('Description'); ?></strong>
                        <p class="muted"><?= nl2br(sanitize($workorder['description'] ?? '')); ?></p>
                    </div>
                <?php endif; ?>
                <div class="pill"><?= sanitize('Status: ' . ($workorder['status'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('Updated: ' . ($workorder['updatedAt'] ?? '')); ?></div>
            </div>
        </div>
        <?php
    });
});
