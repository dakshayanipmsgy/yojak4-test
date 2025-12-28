<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | Blog';
    http_response_code(404);

    render_layout($title, function () {
        ?>
        <div class="card error-card">
            <p class="pill" style="display:inline-block;margin:0 0 8px 0;">Blog</p>
            <h1 style="margin-top:0;"><?= sanitize('Not Found'); ?></h1>
            <p class="muted">Blog posts are not available in this app.</p>
            <div class="buttons" style="margin-top:10px;">
                <a class="btn" href="/site/blog.php"><?= sanitize('Back to Blog'); ?></a>
                <a class="btn secondary" href="/site/index.php"><?= sanitize('Home'); ?></a>
            </div>
        </div>
        <?php
    });
});
