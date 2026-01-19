<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_staff_actor();
    $globalIndex = load_packtpl_index('global');

    $globalPresets = [];
    foreach ($globalIndex as $entry) {
        $record = load_packtpl_record('global', $entry['packTplId'] ?? '');
        if ($record) {
            $globalPresets[] = $record;
        }
    }

    $title = get_app_config()['appName'] . ' | Pack Presets';
    render_layout($title, function () use ($globalPresets, $actor) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Global Pack Presets</h2>
                    <p class="muted" style="margin:4px 0 0;">Manage default pack blueprints.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn" href="/superadmin/packtpl_new.php">Create Preset</a>
                    <?php if (($actor['type'] ?? '') === 'superadmin'): ?>
                        <a class="btn secondary" href="/superadmin/dashboard.php">Back</a>
                    <?php else: ?>
                        <a class="btn secondary" href="/staff/dashboard.php">Back</a>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                <?php if (!$globalPresets): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;">No global presets yet.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($globalPresets as $preset): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                        <div>
                            <h4 style="margin:0 0 4px 0;"><?= sanitize($preset['title'] ?? 'Preset'); ?></h4>
                            <p class="muted" style="margin:0;"><?= sanitize($preset['description'] ?? ''); ?></p>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn" href="/superadmin/packtpl_edit.php?id=<?= sanitize($preset['packTplId'] ?? ''); ?>">Edit</a>
                        </div>
                        <p class="muted" style="margin:0;">Updated: <?= sanitize($preset['updatedAt'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    });
});
