<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('department');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $title = get_app_config()['appName'] . ' | Department Dashboard';
    render_layout($title, function () use ($user) {
        ?>
        <div class="card">
            <h2><?= sanitize('Department Dashboard'); ?></h2>
            <p class="muted"><?= sanitize('Welcome, ' . ($user['displayName'] ?? $user['username'] ?? '')); ?></p>
            <div class="pill"><?= sanitize('Department: ' . ($user['deptId'] ?? '')); ?></div>
            <p class="muted" style="margin-top:12px;"><?= sanitize('Department content will appear here. Superadmin cannot view sensitive documents.'); ?></p>
        </div>
        <?php
    });
});
