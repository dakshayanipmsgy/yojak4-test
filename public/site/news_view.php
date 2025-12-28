<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | News';
    http_response_code(404);

    render_layout($title, function () {
        ?>
        <div class="card error-card">
            <p class="pill" style="display:inline-block;margin:0 0 8px 0;">News</p>
            <h1 style="margin-top:0;"><?= sanitize('Not Found'); ?></h1>
            <p class="muted">News posts are no longer available because Content Studio has been removed.</p>
            <div class="buttons" style="margin-top:10px;">
                <a class="btn" href="/site/news.php"><?= sanitize('Back to News'); ?></a>
                <a class="btn secondary" href="/site/index.php"><?= sanitize('Home'); ?></a>
            </div>
        </div>
        <?php
    });
});
