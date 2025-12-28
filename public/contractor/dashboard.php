<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $title = get_app_config()['appName'] . ' | Contractor Dashboard';

    render_layout($title, function () use ($user) {
        ?>
        <div class="card">
            <h2><?= sanitize('Welcome, ' . ($user['displayName'] ?? $user['username'])); ?></h2>
            <p class="muted"><?= sanitize('Manage your profile, documents, and upcoming tools.'); ?></p>
            <div class="buttons">
                <a class="btn" href="/contractor/vault.php"><?= sanitize('Open Vault'); ?></a>
                <a class="btn secondary" href="/contractor/profile.php"><?= sanitize('Edit Profile'); ?></a>
            </div>
        </div>
        <div class="card">
            <h3><?= sanitize('Shortcuts'); ?></h3>
            <div class="buttons">
                <a class="btn" href="/contractor/vault_upload.php"><?= sanitize('Upload Document'); ?></a>
                <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Offline Tenders'); ?></a>
                <span class="btn secondary"><?= sanitize('Reminders'); ?></span>
                <a class="btn secondary" href="/contractor/support.php"><?= sanitize('Report Issue'); ?></a>
            </div>
        </div>
        <?php
    });
});
