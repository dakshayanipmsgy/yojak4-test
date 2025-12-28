<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Reset Requested';
    render_layout($title, function () {
        ?>
        <div class="card">
            <h2 style="margin-bottom:6px;"><?= sanitize('Request received'); ?></h2>
            <p class="muted" style="margin-top:0;"><?= sanitize('If your contractor account exists, the superadmin will review your reset request.'); ?></p>
            <div class="pill" style="margin:10px 0;"><?= sanitize('You will be given a temporary password once approved.'); ?></div>
            <a class="btn" href="/contractor/login.php"><?= sanitize('Back to login'); ?></a>
        </div>
        <?php
    });
});
