<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $slug = $_GET['slug'] ?? '';
    if ($slug === '') {
        render_error_page('Missing slug.');
        return;
    }
    $item = load_content_by_slug('blog', $slug);
    if (!$item) {
        render_error_page('Blog not found.');
        return;
    }

    $title = get_app_config()['appName'] . ' | ' . ($item['title'] ?? 'Blog');

    render_layout($title, function () use ($item) {
        ?>
        <article class="card">
            <p class="pill" style="display:inline-block;margin:0 0 8px 0;">Blog</p>
            <h1 style="margin-top:0;"><?= sanitize($item['title'] ?? ''); ?></h1>
            <?php if (!empty($item['coverImagePath'])): ?>
                <img src="<?= sanitize($item['coverImagePath']); ?>" alt="Cover" style="max-width:100%;border-radius:12px;border:1px solid #30363d;margin:10px 0;">
            <?php endif; ?>
            <div class="muted" style="margin-bottom:10px;">Published: <?= sanitize($item['publishedAt'] ?? $item['createdAt'] ?? ''); ?></div>
            <div style="line-height:1.6;"><?= $item['bodyHtml'] ?? ''; ?></div>
        </article>
        <?php
    });
});
