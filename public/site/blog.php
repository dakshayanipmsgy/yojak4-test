<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Blog';

    render_layout($title, function () {
        ?>
        <div class="card">
            <h1 style="margin-top:0;">Blog</h1>
            <p class="muted">Stories and guides were previously generated in Content Studio. That studio has been removed.</p>
            <div class="pill" style="display:inline-block;margin-top:8px;"><?= sanitize('Blog content is not available.'); ?></div>
            <div class="buttons" style="margin-top:12px;">
                <a class="btn" href="/site/index.php"><?= sanitize('Back to Home'); ?></a>
                <a class="btn secondary" href="/auth/login.php"><?= sanitize('Login'); ?></a>
            </div>
        </div>
        <?php
    });
});
