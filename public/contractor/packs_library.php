<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_contractor_pack_blueprints_env($yojId);
    ensure_template_pack_library_env();

    $tab = trim((string)($_GET['tab'] ?? 'default'));

    $globalPacks = array_values(array_filter(load_global_packs(), fn($pack) => !empty($pack['published'])));
    usort($globalPacks, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $myPacks = array_values(array_filter(load_contractor_pack_blueprints($yojId), fn($pack) => empty($pack['deletedAt'])));
    usort($myPacks, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $requests = array_values(array_filter(load_requests_index(), fn($req) => ($req['yojId'] ?? '') === $yojId && ($req['type'] ?? '') === 'pack'));
    usort($requests, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Pack Blueprints';

    render_layout($title, function () use ($tab, $globalPacks, $myPacks, $requests) {
        $tabs = [
            'default' => 'YOJAK Packs (Default)',
            'mine' => 'My Pack Blueprints',
            'requests' => 'Requests',
        ];
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Pack Blueprints</h2>
                    <p class="muted" style="margin:4px 0 0;">Reusable pack definitions for checklists, templates, and uploads.</p>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn" href="/contractor/pack_blueprint_new.php">Create Blueprint</a>
                    <a class="btn secondary" href="/contractor/packs.php">Back to Packs</a>
                </div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="btn <?= $tab === $key ? '' : 'secondary'; ?>" href="/contractor/packs_library.php?tab=<?= sanitize($key); ?>"><?= sanitize($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($tab === 'default'): ?>
            <div style="display:grid; gap:12px; margin-top:12px;">
                <?php if (!$globalPacks): ?>
                    <div class="card"><p class="muted" style="margin:0;">No global pack blueprints yet.</p></div>
                <?php endif; ?>
                <?php foreach ($globalPacks as $pack): ?>
                    <div class="card" style="display:grid; gap:10px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                            <div>
                                <h3 style="margin:0;"><?= sanitize($pack['title'] ?? 'Pack Blueprint'); ?></h3>
                                <p class="muted" style="margin:4px 0 0;"><?= sanitize($pack['id'] ?? ''); ?></p>
                            </div>
                            <form method="post" action="/contractor/pack_blueprint_copy_from_global.php">
                                <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
                                <input type="hidden" name="packId" value="<?= sanitize($pack['id'] ?? ''); ?>">
                                <button class="btn" type="submit">Copy &amp; Customize</button>
                            </form>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize($pack['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($tab === 'requests'): ?>
            <div style="margin-top:12px; display:grid; gap:12px;">
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div>
                            <h3 style="margin:0;">Request a Pack Blueprint</h3>
                            <p class="muted" style="margin:4px 0 0;">Upload the tender PDF and explain what should be included in the pack.</p>
                        </div>
                        <a class="btn" href="/contractor/request_new.php?type=pack">New Request</a>
                    </div>
                </div>
                <?php if (!$requests): ?>
                    <div class="card"><p class="muted" style="margin:0;">No requests yet.</p></div>
                <?php endif; ?>
                <?php foreach ($requests as $req): ?>
                    <div class="card" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div>
                            <h4 style="margin:0;"><?= sanitize($req['title'] ?? 'Pack Request'); ?></h4>
                            <p class="muted" style="margin:4px 0 0;"><?= sanitize(($req['id'] ?? '') . ' • ' . ($req['status'] ?? 'new')); ?></p>
                        </div>
                        <a class="btn secondary" href="/contractor/request_view.php?id=<?= sanitize($req['id'] ?? ''); ?>">View</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="display:grid; gap:12px; margin-top:12px;">
                <?php if (!$myPacks): ?>
                    <div class="card">
                        <p class="muted" style="margin:0;">No pack blueprints yet. Create one or copy from YOJAK defaults.</p>
                    </div>
                <?php endif; ?>
                <?php foreach ($myPacks as $pack): ?>
                    <div class="card" style="display:grid; gap:10px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                            <div>
                                <h3 style="margin:0;"><?= sanitize($pack['title'] ?? 'Pack Blueprint'); ?></h3>
                                <p class="muted" style="margin:4px 0 0;"><?= sanitize($pack['id'] ?? ''); ?></p>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <a class="btn secondary" href="/contractor/pack_blueprint_edit.php?id=<?= sanitize($pack['id'] ?? ''); ?>">Edit</a>
                            </div>
                        </div>
                        <p class="muted" style="margin:0;"><?= sanitize($pack['description'] ?? ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    });
});
