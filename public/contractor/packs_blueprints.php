<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_packtpl_env($yojId);

    $tab = trim((string)($_GET['tab'] ?? 'default'));
    $globalIndex = load_packtpl_index('global');
    $contractorIndex = load_packtpl_index('contractor', $yojId);

    $globalPresets = [];
    foreach ($globalIndex as $entry) {
        $record = load_packtpl_record('global', $entry['packTplId'] ?? '');
        if ($record) {
            $globalPresets[] = $record;
        }
    }

    $contractorPresets = [];
    foreach ($contractorIndex as $entry) {
        $record = load_packtpl_record('contractor', $entry['packTplId'] ?? '', $yojId);
        if ($record) {
            $contractorPresets[] = $record;
        }
    }

    $title = get_app_config()['appName'] . ' | Pack Presets';

    render_layout($title, function () use ($tab, $globalPresets, $contractorPresets) {
        $tabs = [
            'default' => 'Default Pack Presets',
            'mine' => 'My Pack Presets',
        ];
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div>
                <h2 style="margin:0;">Pack Presets</h2>
                <p class="muted" style="margin:4px 0 0;">Reuse checklist + template bundles in new tender packs.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="btn <?= $tab === $key ? '' : 'secondary'; ?>" href="/contractor/packs_blueprints.php?tab=<?= sanitize($key); ?>"><?= sanitize($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($tab === 'default'): ?>
            <div class="card" style="margin-top:12px;display:grid;gap:12px;">
                <div>
                    <h3 style="margin:0;">YOJAK Pack Presets</h3>
                    <p class="muted" style="margin:4px 0 0;">Read-only presets prepared by staff.</p>
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
                            <span class="pill">Sections: <?= sanitize((string)count($preset['sections'] ?? [])); ?></span>
                            <p class="muted" style="margin:0;">Updated: <?= sanitize($preset['updatedAt'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card" style="margin-top:12px;display:grid;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;">My Pack Presets</h3>
                        <p class="muted" style="margin:4px 0 0;">Create your own preset checklists and template lists.</p>
                    </div>
                    <a class="btn" href="/contractor/packtpl_new.php">Create Preset</a>
                </div>
                <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
                    <?php if (!$contractorPresets): ?>
                        <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                            <p class="muted" style="margin:0;">No custom presets yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($contractorPresets as $preset): ?>
                        <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                            <div>
                                <h4 style="margin:0 0 4px 0;"><?= sanitize($preset['title'] ?? 'Preset'); ?></h4>
                                <p class="muted" style="margin:0;"><?= sanitize($preset['description'] ?? ''); ?></p>
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a class="btn" href="/contractor/packtpl_edit.php?id=<?= sanitize($preset['packTplId'] ?? ''); ?>">Edit</a>
                                <form method="post" action="/contractor/packtpl_delete.php" onsubmit="return confirm('Delete this preset?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                    <input type="hidden" name="id" value="<?= sanitize($preset['packTplId'] ?? ''); ?>">
                                    <button class="btn secondary" type="submit">Delete</button>
                                </form>
                            </div>
                            <p class="muted" style="margin:0;">Updated: <?= sanitize($preset['updatedAt'] ?? ''); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    });
});
