<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    $yojId = $_GET['yojId'] ?? '';
    $contractor = $yojId ? load_contractor($yojId) : null;
    if (!$contractor) {
        render_error_page('Contractor not found.');
        return;
    }
    $title = get_app_config()['appName'] . ' | Contractor ' . $yojId;
    $vault = contractor_vault_index($contractor['yojId']);

    render_layout($title, function () use ($contractor, $vault) {
        ?>
        <div class="card">
            <h2><?= sanitize('Contractor Profile'); ?></h2>
            <p class="muted"><?= sanitize('YOJ ID: ' . ($contractor['yojId'] ?? '')); ?></p>
            <div style="display:grid; gap:8px;">
                <div class="pill"><?= sanitize('Mobile: ' . ($contractor['mobile'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('Name: ' . ($contractor['name'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('Firm: ' . ($contractor['firmName'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('Status: ' . ($contractor['status'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('Approved: ' . ($contractor['approvedAt'] ?? '')); ?></div>
                <div class="pill"><?= sanitize('Last Login: ' . ($contractor['lastLoginAt'] ?? 'Never')); ?></div>
                <div class="pill"><?= sanitize('Vault files: ' . count($vault)); ?></div>
            </div>
        </div>
        <?php
    });
});
