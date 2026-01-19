<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

safe_page(function () {
    $user = require_role('contractor');
    $yojId = $user['yojId'];
    ensure_packs_env($yojId);

    $packs = packs_index($yojId);
    usort($packs, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));

    $title = get_app_config()['appName'] . ' | Tender Packs';

    render_layout($title, function () use ($packs) {
        ?>
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;"><?= sanitize('Tender Packs'); ?></h2>
                    <p class="muted" style="margin:4px 0 0;"><?= sanitize('One-click packs for offline tenders. Upload items, generate letters, export ZIP.'); ?></p>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a class="btn secondary" href="/contractor/packs_library.php"><?= sanitize('Pack Blueprints'); ?></a>
                    <a class="btn secondary" href="/contractor/offline_tenders.php"><?= sanitize('Back to OFFTD'); ?></a>
                </div>
            </div>
        </div>
        <div style="display:grid; gap:12px; margin-top:12px;">
            <?php if (!$packs): ?>
                <div class="card">
                    <p class="muted" style="margin:0;"><?= sanitize('No tender packs yet. Create from an offline tender.'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($packs as $pack): ?>
                <div class="card" style="display:grid; gap:8px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start;">
                        <div>
                            <h3 style="margin:0;"><?= sanitize($pack['title'] ?? 'Tender Pack'); ?></h3>
                            <p class="muted" style="margin:4px 0 0;">
                                <?= sanitize($pack['packId'] ?? ''); ?> • <?= sanitize($pack['status'] ?? 'Pending'); ?>
                                <?php if (!empty($pack['sourceTender']['id'])): ?>
                                    • <?= sanitize($pack['sourceTender']['type'] ?? ''); ?> <?= sanitize($pack['sourceTender']['id']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn secondary" href="/contractor/pack_view.php?packId=<?= sanitize($pack['packId']); ?>"><?= sanitize('Open'); ?></a>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <div style="flex:1; min-width:220px;">
                            <?php
                            $required = (int)($pack['requiredItems'] ?? 0);
                            $done = (int)($pack['doneRequired'] ?? 0);
                            $progress = $required > 0 ? (int)round(($done / max(1, $required)) * 100) : 0;
                            ?>
                            <div style="position:relative; background:var(--surface); border:1px solid var(--border); border-radius:12px; height:12px; overflow:hidden;">
                                <div style="width:<?= $progress; ?>%; background:var(--primary); height:100%;"></div>
                            </div>
                        </div>
                        <div class="pill"><?= sanitize(($pack['doneRequired'] ?? 0) . '/' . ($pack['requiredItems'] ?? 0) . ' required'); ?></div>
                        <div class="pill"><?= sanitize(($pack['generatedDocs'] ?? 0) . ' generated'); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    });
});
