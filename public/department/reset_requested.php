<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Reset Requested';
    $message = get_flash('success') ?: ['type' => 'success', 'message' => 'If the account exists, the reset request has been received.'];
    render_layout($title, function () use ($message) {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Reset Request Submitted'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('Superadmin will approve and send a temporary password. You must reset it on first login.'); ?></p>
            <?php if ($message): ?>
                <div class="flash <?= sanitize($message['type']); ?>" style="margin:12px 0;"><?= sanitize($message['message']); ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn" href="/department/login.php"><?= sanitize('Back to Login'); ?></a>
                <a class="btn secondary" href="/department/forgot_password.php"><?= sanitize('Submit another request'); ?></a>
            </div>
        </div>
        <?php
    });
});
