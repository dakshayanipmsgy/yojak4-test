<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    require_role('superadmin');
    ensure_template_pack_library_env();

    $packs = load_global_packs();
    usort($packs, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $requests = array_values(array_filter(load_requests_index(), fn($req) => ($req['type'] ?? '') === 'pack'));
    usort($requests, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Global Packs';

    render_layout($title, function () use ($packs, $requests) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">YOJAK Global Packs</h2>
                    <p class="muted" style="margin:4px 0 0;">Manage default pack blueprints available to all contractors.</p>
                </div>
                <a class="btn" href="/superadmin/pack_edit.php">Create Pack Blueprint</a>
            </div>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$packs): ?>
                <div class="card"><p class="muted" style="margin:0;">No global pack blueprints yet.</p></div>
            <?php endif; ?>
            <?php foreach ($packs as $pack): ?>
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h3 style="margin:0;"><?= sanitize($pack['title'] ?? 'Pack Blueprint'); ?></h3>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize($pack['id'] ?? ''); ?></p>
                    </div>
                    <a class="btn secondary" href="/superadmin/pack_edit.php?id=<?= sanitize($pack['id'] ?? ''); ?>">Edit</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h3 style="margin:0;">Contractor Requests</h3>
                    <p class="muted" style="margin:4px 0 0;">Review and deliver pack blueprint requests.</p>
                </div>
            </div>
        </div>

        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$requests): ?>
                <div class="card"><p class="muted" style="margin:0;">No pack requests.</p></div>
            <?php endif; ?>
            <?php foreach ($requests as $req): ?>
                <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <h4 style="margin:0;"><?= sanitize($req['title'] ?? 'Pack Request'); ?></h4>
                        <p class="muted" style="margin:4px 0 0;"><?= sanitize(($req['id'] ?? '') . ' • ' . ($req['status'] ?? 'new')); ?></p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a class="btn secondary" href="/superadmin/request_view.php?id=<?= sanitize($req['id'] ?? ''); ?>">View</a>
                        <a class="btn" href="/superadmin/pack_edit.php?requestId=<?= sanitize($req['id'] ?? ''); ?>">Deliver</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
