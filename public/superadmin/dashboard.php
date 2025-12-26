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
        <div class="hero">
            <div class="card">
                <h2><?= sanitize(t('dashboard')); ?></h2>
                <p class="muted"><?= sanitize('Welcome back, ' . $user['username'] . '.'); ?></p>
                <div class="pill"><?= sanitize('Future metrics will appear here.'); ?></div>
            </div>
            <div class="card" style="display:flex;flex-direction:column;gap:10px;justify-content:space-between;min-height:180px;">
                <div>
                    <div class="pill" style="display:inline-flex;align-items:center;gap:6px;"><?= sanitize('Content Studio'); ?></div>
                    <h3 style="margin:8px 0 6px;"><?= sanitize('Create and manage publications'); ?></h3>
                    <p class="muted" style="margin:0;"><?= sanitize('Generate blog/news drafts, preview, schedule, and publish.'); ?></p>
                </div>
                <div class="buttons">
                    <a class="btn" href="/superadmin/content_studio.php"><?= sanitize('Open Content Studio'); ?></a>
                </div>
            </div>
        </div>
        <?php
    });
});
