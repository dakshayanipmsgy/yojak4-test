<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_scheme_builder();
    $schemeId = trim($_GET['schemeId'] ?? '');
    if ($schemeId === '') {
        render_error_page('Scheme ID missing.');
        return;
    }
    $scheme = scheme_load_metadata($schemeId);
    if (!$scheme) {
        render_error_page('Scheme not found.');
        return;
    }
    $templateSets = scheme_template_sets($schemeId);

    $title = get_app_config()['appName'] . ' | Scheme Templates';
    render_layout($title, function () use ($scheme, $schemeId, $templateSets) {
        ?>
        <div class="card" style="display:grid;gap:14px;">
            <div>
                <h2 style="margin:0;">Template Sets</h2>
                <p class="muted" style="margin:6px 0 0;">Scheme: <?= sanitize($scheme['name'] ?? ''); ?> (<?= sanitize($schemeId); ?>)</p>
            </div>
            <?php if (!$templateSets): ?>
                <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                    <p class="muted" style="margin:0;">No template sets available yet.</p>
                </div>
            <?php endif; ?>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php foreach ($templateSets as $set): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-2);display:grid;gap:8px;">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize($set['name'] ?? ($set['templateSetId'] ?? 'Template Set')); ?></h3>
                            <p class="muted" style="margin:0;">ID: <?= sanitize($set['templateSetId'] ?? ''); ?></p>
                        </div>
                        <p class="muted" style="margin:0;white-space:pre-wrap;max-height:120px;overflow:auto;"><?= sanitize($set['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
