<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $sessionUser = require_role('superadmin');
    if (!empty($sessionUser['mustResetPassword'])) {
        redirect('/auth/force_reset.php');
    }
    $record = get_user_record($sessionUser['username']);
    $title = get_app_config()['appName'] . ' | ' . t('profile');
    render_layout($title, function () use ($record) {
        ?>
        <div class="card">
            <h2><?= sanitize(t('superadmin_profile')); ?></h2>
            <p class="muted"><?= sanitize('Core account details for auditing.'); ?></p>
            <ul>
                <li><strong><?= sanitize(t('username')); ?>:</strong> <?= sanitize($record['username'] ?? ''); ?></li>
                <li><strong><?= sanitize(t('status_active')); ?>:</strong> <?= sanitize($record['status'] ?? ''); ?></li>
                <li><strong><?= sanitize(t('last_login')); ?>:</strong> <?= sanitize($record['lastLoginAt'] ?? '—'); ?></li>
                <li><strong><?= sanitize(t('must_reset')); ?>:</strong> <?= !empty($record['mustResetPassword']) ? 'Yes' : 'No'; ?></li>
            </ul>
        </div>
        <?php
    });
});
