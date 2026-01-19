<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $actor = require_superadmin_or_permission('pack_manager');
    $yojId = trim((string)($_GET['yojId'] ?? ''));
    $globalPacks = array_values(array_filter(
        list_pack_template_records('global', null),
        fn($pack) => ($pack['status'] ?? 'active') === 'active'
    ));
    $contractorPacks = $yojId !== ''
        ? list_pack_template_records('contractor', $yojId)
        : [];

    $title = get_app_config()['appName'] . ' | Packs Manager';
    render_layout($title, function () use ($yojId, $globalPacks, $contractorPacks) {
        ?>
        <style>
            .tab-buttons { display:flex; gap:10px; flex-wrap:wrap; }
            .tab-buttons button { border:1px solid var(--border); background:var(--surface); color:var(--text); padding:8px 14px; border-radius:999px; cursor:pointer; }
            .tab-buttons button.active { background:var(--primary); color:#fff; border-color:transparent; }
            .tab-panel { display:none; margin-top:16px; }
            .tab-panel.active { display:block; }
            .pack-grid { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
            .pack-card { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--surface-2); display:grid; gap:8px; }
        </style>
        <div class="card" style="display:grid;gap:16px;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Pack Templates Manager</h2>
                    <p class="muted" style="margin:4px 0 0;">Manage global pack templates and contractor packs.</p>
                </div>
                <a class="btn" href="/superadmin/pack_template_new.php?scope=global">Create Global Pack Template</a>
            </div>
            <div class="tab-buttons" role="tablist">
                <button type="button" class="active" data-tab="global">Global Pack Templates</button>
                <button type="button" data-tab="contractor">Contractor Pack Templates</button>
            </div>
            <div class="tab-panel active" data-panel="global">
                <?php if (!$globalPacks): ?>
                    <div class="card" style="border:1px dashed var(--border);background:var(--surface-2);">
                        <p class="muted" style="margin:0;">No global pack templates created yet.</p>
                    </div>
                <?php else: ?>
                    <div class="pack-grid">
                        <?php foreach ($globalPacks as $pack): ?>
                            <div class="pack-card">
                                <div>
                                    <h3 style="margin:0 0 6px 0;"><?= sanitize($pack['title'] ?? 'Pack'); ?></h3>
                                    <p class="muted" style="margin:0;"><?= sanitize($pack['packTemplateId'] ?? ''); ?></p>
                                </div>
                                <p class="muted" style="margin:0;max-height:140px;overflow:hidden;">
                                    <?= sanitize(mb_substr($pack['description'] ?? '', 0, 220)); ?>
                                </p>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a class="btn secondary" href="/superadmin/pack_template_edit.php?scope=global&packTemplateId=<?= sanitize($pack['packTemplateId'] ?? ''); ?>">Edit</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="tab-panel" data-panel="contractor">
                <form method="get" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:end;">
                    <label style="display:grid;gap:6px;">
                        <span class="muted">Contractor YOJ ID</span>
                        <input class="input" type="text" name="yojId" value="<?= sanitize($yojId); ?>" placeholder="YOJ-XXXXX">
                    </label>
                    <button class="btn secondary" type="submit">Load Pack Templates</button>
                </form>
                <?php if ($yojId !== ''): ?>
                    <div style="margin-top:10px;">
                        <a class="btn" href="/superadmin/pack_template_new.php?scope=contractor&yojId=<?= sanitize($yojId); ?>">Create Contractor Pack Template</a>
                    </div>
                <?php endif; ?>
                <?php if ($yojId !== '' && !$contractorPacks): ?>
                    <div class="card" style="margin-top:12px;border:1px dashed var(--border);background:var(--surface-2);">
                        <p class="muted" style="margin:0;">No pack templates found for this contractor.</p>
                    </div>
                <?php endif; ?>
                <?php if ($contractorPacks): ?>
                    <div class="pack-grid" style="margin-top:12px;">
                        <?php foreach ($contractorPacks as $pack): ?>
                            <div class="pack-card">
                                <div>
                                    <h3 style="margin:0 0 6px 0;"><?= sanitize($pack['title'] ?? 'Pack'); ?></h3>
                                    <p class="muted" style="margin:0;"><?= sanitize($pack['packTemplateId'] ?? ''); ?></p>
                                </div>
                                <p class="muted" style="margin:0;max-height:140px;overflow:hidden;">
                                    <?= sanitize(mb_substr($pack['description'] ?? '', 0, 220)); ?>
                                </p>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    <a class="btn secondary" href="/superadmin/pack_template_edit.php?scope=contractor&yojId=<?= sanitize($yojId); ?>&packTemplateId=<?= sanitize($pack['packTemplateId'] ?? ''); ?>">Edit</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
            document.querySelectorAll('.tab-buttons button').forEach(button => {
                button.addEventListener('click', () => {
                    document.querySelectorAll('.tab-buttons button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
                    button.classList.add('active');
                    const target = button.getAttribute('data-tab');
                    const panel = document.querySelector(`.tab-panel[data-panel="${target}"]`);
                    if (panel) {
                        panel.classList.add('active');
                    }
                });
            });
        </script>
        <?php
    });
});
