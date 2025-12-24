<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('superadmin');
    if (!empty($user['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $title = get_app_config()['appName'] . ' | ' . t('dashboard');
    render_layout($title, function () use ($user) {
        ?>
        <div class="card">
            <h2><?= sanitize(t('dashboard')); ?></h2>
            <p class="muted"><?= sanitize('Welcome back, ' . $user['username'] . '.'); ?></p>
            <div class="pill"><?= sanitize('Future metrics will appear here.'); ?></div>
        </div>
        <?php
    });
});
