<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = current_user();
    $title = get_app_config()['appName'] . ' | ' . t('welcome_title');
    $lang = get_language();
    $blogs = list_content('blog', ['published']);
    $news = list_content('news', ['published']);
    render_layout($title, function () use ($user, $blogs, $news, $lang) {
        ?>
        <section class="hero">
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <h1 style="margin:0;" id="hero-title-hi">दस्तावेज़ मिनटों में—दिनों में नहीं।</h1>
                    <div class="pill" id="hero-toggle" style="cursor:pointer;">हिन्दी / English</div>
                </div>
                <p class="muted" id="hero-support-hi">कॉपी-पेस्ट छोड़िए, काम तेज़ कीजिए।</p>
                <div id="hero-en" style="display:none;">
                    <h1 style="margin:0;">Documents in minutes—not days.</h1>
                    <p class="muted">Skip copy-paste, speed up your work.</p>
                </div>
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
            <?php if ($user && ($user['type'] ?? '') === 'superadmin'): ?>
                <div class="card">
                    <h3><?= sanitize('Superadmin tools'); ?></h3>
                    <p class="muted"><?= sanitize('Configure AI providers, models, and keys in AI Studio.'); ?></p>
                    <div class="buttons">
                        <a class="btn" href="/superadmin/ai_studio.php"><?= sanitize('AI Studio'); ?></a>
                        <a class="btn secondary" href="/superadmin/dashboard.php"><?= sanitize(t('dashboard')); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <section class="card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Latest Blog</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <?php foreach (array_slice($blogs, 0, 3) as $item): ?>
                    <a href="/site/blog_view.php?slug=<?= urlencode($item['slug']); ?>" class="card" style="text-decoration:none;background:#0f1520;border:1px solid #30363d;">
                        <?php if (!empty($item['coverImagePath'])): ?>
                            <img src="<?= sanitize($item['coverImagePath']); ?>" alt="Cover" style="width:100%;border-radius:10px;">
                        <?php endif; ?>
                        <h4 style="margin-bottom:4px;"><?= sanitize($item['title'] ?? ''); ?></h4>
                        <p class="muted" style="margin:0;"><?= sanitize($item['excerpt'] ?? ''); ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (!$blogs): ?>
                    <p class="muted" style="margin:0;">No blog posts yet.</p>
                <?php endif; ?>
            </div>
        </section>
        <section class="card" style="margin-top:16px;">
            <h3 style="margin-top:0;">Latest News</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <?php foreach (array_slice($news, 0, 3) as $item): ?>
                    <a href="/site/news_view.php?slug=<?= urlencode($item['slug']); ?>" class="card" style="text-decoration:none;background:#0f1520;border:1px solid #30363d;">
                        <?php if (!empty($item['coverImagePath'])): ?>
                            <img src="<?= sanitize($item['coverImagePath']); ?>" alt="Cover" style="width:100%;border-radius:10px;">
                        <?php endif; ?>
                        <h4 style="margin-bottom:4px;"><?= sanitize($item['title'] ?? ''); ?></h4>
                        <p class="muted" style="margin:0;"><?= sanitize($item['excerpt'] ?? ''); ?></p>
                    </a>
                <?php endforeach; ?>
                <?php if (!$news): ?>
                    <p class="muted" style="margin:0;">No news yet.</p>
                <?php endif; ?>
            </div>
        </section>
        <script>
            const toggle = document.getElementById('hero-toggle');
            const hiBlock = document.getElementById('hero-title-hi').parentElement;
            const hiSupport = document.getElementById('hero-support-hi');
            const enBlock = document.getElementById('hero-en');
            if (toggle && hiBlock && hiSupport && enBlock) {
                const startWithEn = <?= $lang === 'en' ? 'true' : 'false'; ?>;
                if (startWithEn) {
                    enBlock.style.display = 'block';
                    hiBlock.style.display = 'none';
                    hiSupport.style.display = 'none';
                    toggle.textContent = 'English / हिन्दी';
                }
                toggle.addEventListener('click', () => {
                    const showingHi = enBlock.style.display === 'none';
                    enBlock.style.display = showingHi ? 'block' : 'none';
                    hiBlock.style.display = showingHi ? 'none' : 'flex';
                    hiSupport.style.display = showingHi ? 'none' : 'block';
                    toggle.textContent = showingHi ? 'English / हिन्दी' : 'हिन्दी / English';
                });
            }
        </script>
        <?php
    });
});
