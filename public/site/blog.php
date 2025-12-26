<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $items = list_content('blog', ['published']);
    $title = get_app_config()['appName'] . ' | Blog';

    render_layout($title, function () use ($items) {
        ?>
        <div class="card">
            <h1 style="margin-top:0;">Blog</h1>
            <p class="muted">Stories, guides, and platform highlights.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:12px;">
                <?php foreach ($items as $item): ?>
                    <a href="/site/blog_view.php?slug=<?= urlencode($item['slug']); ?>" class="card" style="text-decoration:none;background:#0f1520;border:1px solid #30363d;display:flex;flex-direction:column;gap:8px;">
                        <?php if (!empty($item['coverImagePath'])): ?>
                            <img src="<?= sanitize($item['coverImagePath']); ?>" alt="Cover" style="width:100%;border-radius:10px;">
                        <?php endif; ?>
                        <h3 style="margin:0;"><?= sanitize($item['title'] ?? ''); ?></h3>
                        <p class="muted" style="margin:0;"><?= sanitize($item['excerpt'] ?? ''); ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <p class="muted">No blog posts yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    });
});
