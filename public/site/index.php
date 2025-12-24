<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $title = get_app_config()['appName'] . ' | ' . t('welcome_title');
    render_layout($title, function () {
        ?>
        <section class="hero">
            <div class="card">
                <h1><?= sanitize(t('welcome_title')); ?></h1>
                <p class="muted"><?= sanitize(t('welcome_body')); ?></p>
                <p class="pill"><?= sanitize(t('home_tagline')); ?></p>
                <div class="buttons">
                    <a class="btn" href="/auth/login.php"><?= sanitize(t('login')); ?></a>
                    <a class="btn secondary" href="/health.php"><?= sanitize('Health Check'); ?></a>
                </div>
            </div>
            <div class="card">
                <h3><?= sanitize('Highlights'); ?></h3>
                <ul>
                    <li><?= sanitize('Session-based auth with CSRF protection'); ?></li>
                    <li><?= sanitize('Per-device rate limiting for secure logins'); ?></li>
                    <li><?= sanitize('Language toggle (English / Hindi) that persists'); ?></li>
                    <li><?= sanitize('Safe pages with friendly error handling and logging'); ?></li>
                </ul>
            </div>
        </section>
        <?php
    });
});
