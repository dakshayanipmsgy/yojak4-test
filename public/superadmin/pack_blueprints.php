<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_superadmin_or_permission('pack_blueprints_manage');
    $blueprints = pack_blueprint_list('global');
    $requests = request_list('pack');
    $title = get_app_config()['appName'] . ' | Global Pack Blueprints';

    render_layout($title, function () use ($blueprints, $requests) {
        ?>
        <div class="card" style="display:grid;gap:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Global Pack Blueprints'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Manage default pack blueprints for all contractors.'); ?></p>
                </div>
                <a class="btn" href="/superadmin/pack_blueprint_edit.php"><?= sanitize('New Pack Blueprint'); ?></a>
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));">
                <?php if (!$blueprints): ?>
                    <div class="card" style="background:var(--surface-2);border:1px dashed var(--border);">
                        <p class="muted" style="margin:0;"><?= sanitize('No global pack blueprints yet.'); ?></p>
                    </div>
                <?php endif; ?>
                <?php foreach ($blueprints as $bp): ?>
                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;display:grid;gap:8px;background:var(--surface-2);">
                        <div>
                            <h3 style="margin:0 0 4px 0;"><?= sanitize($bp['title'] ?? 'Pack'); ?></h3>
                            <p class="muted" style="margin:0;"><?= sanitize($bp['description'] ?? ''); ?></p>
                        </div>
                        <span class="pill"><?= sanitize(count($bp['items']['checklist'] ?? []) . ' checklist items'); ?></span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn secondary" href="/superadmin/pack_blueprint_edit.php?id=<?= sanitize($bp['id'] ?? ''); ?>"><?= sanitize('Edit'); ?></a>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize('Updated: ' . ($bp['updatedAt'] ?? '')); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" style="margin-top:16px;display:grid;gap:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;"><?= sanitize('Pack Requests Queue'); ?></h3>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('Contractor submissions awaiting staff action.'); ?></p>
                </div>
                <a class="btn secondary" href="/superadmin/requests.php?type=packs"><?= sanitize('Open queue'); ?></a>
            </div>
            <?php if (!$requests): ?>
                <p class="muted"><?= sanitize('No pending pack requests.'); ?></p>
            <?php else: ?>
                <div style="display:grid;gap:8px;">
                    <?php foreach (array_slice($requests, 0, 5) as $req): ?>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;display:grid;gap:4px;">
                            <strong><?= sanitize($req['title'] ?? 'Request'); ?></strong>
                            <span class="muted"><?= sanitize('Status: ' . request_status_label((string)($req['status'] ?? 'new'))); ?></span>
                            <span class="muted"><?= sanitize('Updated: ' . ($req['updatedAt'] ?? '')); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    });
});
