<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    $schemes = scheme_list_all();
    usort($schemes, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
    $pendingRequests = count(scheme_pending_requests());
    $title = get_app_config()['appName'] . ' | Schemes';

    render_layout($title, function () use ($schemes, $pendingRequests, $user) {
        ?>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Scheme Builder</h2>
                    <p class="muted" style="margin:6px 0 0;">Create JSON-driven schemes and manage vendor access requests.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/superadmin/scheme_new.php">Create Scheme</a>
                    <a class="btn secondary" href="/superadmin/scheme_activation_requests.php">Activation Requests (<?= sanitize((string)$pendingRequests); ?>)</a>
                </div>
            </div>

            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <?php if (!$schemes): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No schemes created yet. Start with a new scheme shell.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($schemes as $scheme): ?>
                    <div style="border:1px solid var(--border);border-radius:14px;padding:14px;display:grid;gap:8px;background:var(--surface-2);">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($scheme['name'] ?? 'Scheme'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize($scheme['schemeId'] ?? ''); ?> • <?= sanitize($scheme['category'] ?? ''); ?></p>
                            </div>
                            <span class="pill" style="border-color:var(--border);color:var(--text);">
                                <?= sanitize(ucfirst((string)($scheme['status'] ?? 'draft'))); ?>
                            </span>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize($scheme['shortDescription'] ?? ''); ?></p>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/superadmin/scheme_sections.php?schemeId=<?= urlencode((string)($scheme['schemeId'] ?? '')); ?>">Sections</a>
                            <a class="btn secondary" href="/superadmin/scheme_import.php?schemeId=<?= urlencode((string)($scheme['schemeId'] ?? '')); ?>">Legacy Import</a>
                            <a class="btn secondary" href="/superadmin/scheme_templates.php?schemeId=<?= urlencode((string)($scheme['schemeId'] ?? '')); ?>">Template Sets</a>
                            <form method="post" action="/superadmin/scheme_recompile.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="schemeId" value="<?= sanitize((string)($scheme['schemeId'] ?? '')); ?>">
                                <button class="btn secondary" type="submit">Recompile</button>
                            </form>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($scheme['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
