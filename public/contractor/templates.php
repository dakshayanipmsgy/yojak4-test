<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_templates_env($yojId);

    $templates = load_contractor_templates_full($yojId);
    $title = get_app_config()['appName'] . ' | Tender Templates';

    render_layout($title, function () use ($templates) {
        ?>
        <div class="card" style="display:grid;gap:10px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Default Tender Templates</h2>
                    <p class="muted" style="margin:4px 0 0;">Common tender letters without any bid values. Use them inside packs.</p>
                </div>
                <form method="post" action="/contractor/templates_seed_defaults.php">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                    <button class="btn" type="submit">Add default templates</button>
                </form>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$templates): ?>
                    <div class="card" style="background:#0f1520;border:1px dashed #30363d;">
                        <p class="muted" style="margin:0;">No templates yet. Seed defaults to get started.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($templates as $tpl): ?>
                    <div style="border:1px solid #30363d;border-radius:12px;padding:12px;display:grid;gap:8px;background:#0f1520;">
                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 4px 0;"><?= sanitize($tpl['name'] ?? 'Template'); ?></h3>
                                <p class="muted" style="margin:0;"><?= sanitize(ucfirst($tpl['category'] ?? 'tender')); ?> • <?= sanitize($tpl['language'] ?? 'en'); ?></p>
                            </div>
                            <?php if (!empty($tpl['isDefaultSeeded'])): ?>
                                <span class="pill" style="border-color:#2ea043;color:#8ce99a;">Default</span>
                            <?php endif; ?>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:160px;overflow:auto;"><?= sanitize(mb_substr($tpl['body'] ?? '', 0, 500)); ?></p>
                        <?php if (!empty($tpl['placeholders'])): ?>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php foreach ($tpl['placeholders'] as $ph): ?>
                                    <span class="tag"><?= sanitize($ph); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($tpl['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
